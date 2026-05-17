<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:make-admin',
    description: 'Nadaje ROLE_ADMIN użytkownikowi po emailu.'
)]
class MakeAdminCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email użytkownika');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $output->writeln("<error>Nie znaleziono użytkownika: {$email}</error>");
            return Command::FAILURE;
        }

        $roles = $user->getRoles();
        if (!in_array('ROLE_ADMIN', $roles, true)) {
            $roles[] = 'ROLE_ADMIN';
        }
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        $user->setRoles(array_values(array_unique($roles)));
        $this->em->flush();

        $output->writeln("<info>OK: {$email} ma teraz ROLE_ADMIN</info>");
        return Command::SUCCESS;
    }
}
