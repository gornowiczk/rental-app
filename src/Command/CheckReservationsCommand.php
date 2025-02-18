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
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTime();

        // Pobieramy wszystkie rezerwacje o statusie "confirmed"
        $reservations = $this->entityManager->getRepository(Reservation::class)->findBy([
            'status' => 'confirmed'
        ]);

        foreach ($reservations as $reservation) {
            if ($reservation->getEndDate() < $today) {
                $reservation->setStatus('completed');
            }
        }

        $this->entityManager->flush();
        $output->writeln('Zaktualizowano zakończone rezerwacje.');

        return Command::SUCCESS;
    }
}
