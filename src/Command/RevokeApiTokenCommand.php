<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * Revoking takes the selector, which app:token:list shows — the full token is
 * never needed again after its one and only display.
 */
#[AsCommand(
    name: 'app:token:revoke',
    description: 'Revoke an API token by its selector',
)]
final class RevokeApiTokenCommand extends Command
{
    public function __construct(
        private readonly ApiTokenRepository $tokens,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('selector', InputArgument::REQUIRED, 'The 12-character selector, as listed by app:token:list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $selector = trim((string) $input->getArgument('selector'));
        $token = $this->tokens->findOneBySelector($selector);

        if (null === $token) {
            $io->error(sprintf('No token carries the selector "%s".', $selector));

            return Command::FAILURE;
        }

        if ($token->isRevoked()) {
            $io->info(sprintf('Token "%s" was already revoked.', $selector));

            return Command::SUCCESS;
        }

        $token->revoke();
        $this->entityManager->flush();

        $io->success(sprintf('Token "%s" (%s) is revoked.', $selector, $token->getLabel()));

        return Command::SUCCESS;
    }
}
