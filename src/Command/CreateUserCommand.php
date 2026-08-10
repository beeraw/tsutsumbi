<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function is_string;
use function sprintf;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a human user able to sign in to the interface',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address, used as the login')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Grant ROLE_ADMIN')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getArgument('email'));

        if ('' === $email) {
            $io->error('An email address is required.');

            return Command::INVALID;
        }

        if (null !== $this->users->findOneByEmail($email)) {
            $io->error(sprintf('A user already exists with the email "%s".', $email));

            return Command::INVALID;
        }

        // The fallback matters when there is no TTY — a provisioning script
        // piping the password in. Without it the command simply refuses to run.
        $question = (new Question('Password: '))->setHidden(true)->setHiddenFallback(true);
        $password = $io->askQuestion($question);

        if (!is_string($password) || '' === $password) {
            $io->error('A password is required.');

            return Command::INVALID;
        }

        $user = new User($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        if (true === $input->getOption('admin')) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('User "%s" created.', $email));

        return Command::SUCCESS;
    }
}
