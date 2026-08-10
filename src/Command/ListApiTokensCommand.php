<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ApiToken;
use App\Repository\ApiTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

#[AsCommand(
    name: 'app:token:list',
    description: 'List API tokens by selector, without ever revealing a verifier',
)]
final class ListApiTokensCommand extends Command
{
    public function __construct(private readonly ApiTokenRepository $tokens)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tokens = $this->tokens->findBy([], ['createdAt' => 'DESC']);

        if ([] === $tokens) {
            $io->info('No token has been created yet.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Selector', 'Label', 'Agent', 'Scopes', 'State', 'Last used'],
            array_map(static fn (ApiToken $token): array => [
                $token->getSelector(),
                $token->getLabel(),
                $token->getAgentSlug(),
                implode(', ', $token->getScopes()),
                self::state($token),
                $token->getLastUsedAt()?->format('Y-m-d H:i') ?? 'never',
            ], $tokens),
        );

        return Command::SUCCESS;
    }

    private static function state(ApiToken $token): string
    {
        return match (true) {
            $token->isRevoked() => sprintf('revoked %s', $token->getRevokedAt()?->format('Y-m-d') ?? ''),
            $token->isExpired() => 'expired',
            null !== $token->getExpiresAt() => sprintf('expires %s', $token->getExpiresAt()->format('Y-m-d')),
            default => 'active',
        };
    }
}
