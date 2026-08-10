<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\AttachmentRejectedException;
use App\Exception\InvalidProjectPathException;
use App\Exception\InvalidTicketStateException;
use App\Http\ProblemJsonResponseFactory;
use App\Http\ProblemType;
use App\Security\ApiActor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * Turns anything thrown under /api into an RFC 7807 payload.
 *
 * Priority 2 places it ahead of the firewall's own exception listener, so an
 * access denial answers in problem+json like every other error instead of the
 * default HTML page. Authentication failures are left alone: the authenticator
 * already renders those, and its entry point has to keep its say.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 2)]
final readonly class ApiExceptionListener
{
    public function __construct(
        private ProblemJsonResponseFactory $problems,
        private Security $security,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof AuthenticationException) {
            return;
        }

        // An access denial with nobody authenticated is a missing token, not an
        // insufficient one. Leaving it to the firewall is what turns it into the
        // entry point's 401 instead of a misleading 403.
        if ($throwable instanceof AccessDeniedException && !$this->security->getUser() instanceof ApiActor) {
            return;
        }

        $event->setResponse($this->toProblem($throwable));
    }

    private function toProblem(Throwable $throwable): JsonResponse
    {
        $validationFailure = $this->findValidationFailure($throwable);

        if (null !== $validationFailure) {
            return $this->problems->create(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ProblemType::VALIDATION_FAILED,
                'api.error.validation_failed',
                errors: $this->collectViolations($validationFailure),
            );
        }

        if ($throwable instanceof InvalidTicketStateException) {
            return $this->problems->create(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ProblemType::INVALID_TICKET_STATE,
                'api.error.invalid_ticket_state',
                $throwable->getMessage(),
            );
        }

        if ($throwable instanceof AttachmentRejectedException) {
            return $this->problems->create(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ProblemType::ATTACHMENT_REJECTED,
                'api.error.attachment_rejected',
                $throwable->getMessage(),
            );
        }

        if ($throwable instanceof InvalidProjectPathException) {
            return $this->problems->create(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ProblemType::INVALID_PROJECT_PATH,
                'api.error.invalid_project_path',
                $throwable->getMessage(),
            );
        }

        if ($throwable instanceof AccessDeniedException) {
            return $this->problems->create(
                Response::HTTP_FORBIDDEN,
                ProblemType::FORBIDDEN,
                'api.error.forbidden',
            );
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return $this->fromHttpException($throwable);
        }

        return $this->problems->create(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ProblemType::INTERNAL_ERROR,
            'api.error.internal',
            // Never leak an internal message in production: an exception text can
            // carry a query, a path or a value that has no business leaving.
            $this->debug ? $throwable->getMessage() : null,
        );
    }

    private function fromHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();

        [$type, $titleKey] = match ($status) {
            Response::HTTP_NOT_FOUND => [ProblemType::NOT_FOUND, 'api.error.not_found'],
            Response::HTTP_FORBIDDEN => [ProblemType::FORBIDDEN, 'api.error.forbidden'],
            Response::HTTP_UNAUTHORIZED => [ProblemType::UNAUTHORIZED, 'api.error.unauthorized'],
            Response::HTTP_TOO_MANY_REQUESTS => [ProblemType::RATE_LIMITED, 'api.error.rate_limited'],
            Response::HTTP_BAD_REQUEST => [ProblemType::MALFORMED_REQUEST, 'api.error.malformed_request'],
            default => [ProblemType::INTERNAL_ERROR, 'api.error.internal'],
        };

        /** @var array<string, string|int> $headers */
        $headers = $exception->getHeaders();

        return $this->problems->create(
            $status,
            $type,
            $titleKey,
            $this->debug ? $exception->getMessage() : null,
            headers: $headers,
        );
    }

    /**
     * MapRequestPayload reports a rejected body as an HTTP exception wrapping the
     * real violation list, so the useful part is one level down.
     */
    private function findValidationFailure(Throwable $throwable): ?ValidationFailedException
    {
        for ($candidate = $throwable; null !== $candidate; $candidate = $candidate->getPrevious()) {
            if ($candidate instanceof ValidationFailedException) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function collectViolations(ValidationFailedException $exception): array
    {
        $errors = [];

        foreach ($exception->getViolations() as $violation) {
            $field = $violation->getPropertyPath();
            $errors[$field][] = (string) $violation->getMessage();
        }

        return $errors;
    }
}
