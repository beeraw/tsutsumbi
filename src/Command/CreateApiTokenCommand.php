<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ApiToken;
use App\Enum\ApiTokenScope;
use App\Security\ApiTokenGenerator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function is_string;
use function sprintf;

/**
 * Mints a machine token. The interface will offer the same thing once it
 * exists; until then this is how an agent gets its credentials.
 */
#[AsCommand(
    name: 'app:token:create',
    description: 'Create an API token for an agent and print it once',
)]
final class CreateApiTokenCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiTokenGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Human-readable label, e.g. "laptop / claude-code"')
            ->addOption('agent', null, InputOption::VALUE_REQUIRED, 'Agent slug the token acts as, e.g. claude-code')
            ->addOption(
                'scope',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Granted scope, repeatable. Defaults to every scope.',
            )
            ->addOption('expires', null, InputOption::VALUE_REQUIRED, 'Expiry date, any format strtotime understands')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $label = $this->stringOption($input, 'label') ?? $io->ask('Label');
        $agentSlug = $this->stringOption($input, 'agent') ?? $io->ask('Agent slug', 'claude-code');

        if (!is_string($label) || '' === trim($label) || !is_string($agentSlug) || '' === trim($agentSlug)) {
            $io->error('A label and an agent slug are both required.');

            return Command::INVALID;
        }

        /** @var list<string> $requested */
        $requested = $input->getOption('scope');
        $scopes = [];

        foreach ([] === $requested ? ApiTokenScope::values() : $requested as $value) {
            $scope = ApiTokenScope::tryFrom($value);

            if (null === $scope) {
                $io->error(sprintf('Unknown scope "%s". Available: %s', $value, implode(', ', ApiTokenScope::values())));

                return Command::INVALID;
            }

            $scopes[] = $scope;
        }

        $expiresAt = null;
        $expires = $this->stringOption($input, 'expires');

        if (null !== $expires) {
            try {
                $expiresAt = new DateTimeImmutable($expires);
            } catch (Exception) {
                $io->error(sprintf('Could not read "%s" as a date.', $expires));

                return Command::INVALID;
            }
        }

        $generated = $this->generator->generate();

        $this->entityManager->persist(new ApiToken(
            trim($label),
            trim($agentSlug),
            $scopes,
            $generated->selector,
            $generated->verifierHash,
            expiresAt: $expiresAt,
        ));
        $this->entityManager->flush();

        $io->success('Token created.');
        $io->writeln($generated->plain);
        $io->warning('This is the only time the token is shown. Store it now.');

        return Command::SUCCESS;
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && '' !== trim($value) ? $value : null;
    }
}
