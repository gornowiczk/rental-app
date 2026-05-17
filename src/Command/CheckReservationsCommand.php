<?php

namespace App\Command;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:check-reservations')]
class CheckReservationsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTimeImmutable('today');

        // UWAGA: nie 'confirmed', tylko zaakceptowane
        $reservations = $this->em->getRepository(Reservation::class)->findBy(['status' => 'accepted']);

        foreach ($reservations as $r) {
            if ($r->getEndDate() < $today) {
                $r->setStatus('completed');
            }
        }

        $this->em->flush();
        $output->writeln('Zaktualizowano zakończone rezerwacje.');
        return Command::SUCCESS;
    }
}
