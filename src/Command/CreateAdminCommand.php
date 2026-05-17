<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Tworzy nowego administratora (ROLE_ADMIN / ROLE_SUPER_ADMIN)'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        // EMAIL
        $emailQ = new Question('Email admina: ');
        $emailQ->setValidator(function ($value) {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Nieprawidłowy email.');
            }
            return strtolower(trim($value));
        });

        $email = $helper->ask($input, $output, $emailQ);

        // HASŁO
        $passQ = new Question('Hasło: ');
        $passQ->setHidden(true);
        $passQ->setHiddenFallback(false);
        $passQ->setValidator(function ($value) {
            if (strlen($value) < 6) {
                throw new \RuntimeException('Hasło musi mieć min. 6 znaków.');
            }
            return $value;
        });

        $password = $helper->ask($input, $output, $passQ);

        // ROLA
        $roleQ = new Question('Rola (admin/super) [admin]: ', 'admin');
        $roleQ->setValidator(function ($value) {
            if (!in_array($value, ['admin', 'super'], true)) {
                throw new \RuntimeException('Dozwolone: admin, super');
            }
            return $value;
        });

        $role = $helper->ask($input, $output, $roleQ);

        // Sprawdź czy istnieje
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln('<error>Użytkownik o tym emailu już istnieje.</error>');
            return Command::FAILURE;
        }

        // Tworzenie usera
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $password)
        );

        $roles = ['ROLE_USER', 'ROLE_ADMIN'];
        if ($role === 'super') {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        $user->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('');
        $output->writeln('<info>✅ Administrator utworzony!</info>');
        $output->writeln("Email: <comment>$email</comment>");
        $output->writeln('Role: ' . implode(', ', $roles));

        return Command::SUCCESS;
    }
}
