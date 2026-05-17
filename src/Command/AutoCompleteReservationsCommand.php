<?php

namespace App\Command;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Service\ReservationStatusLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:auto-complete-reservations',
    description: 'Automatycznie kończy zaakceptowane rezerwacje po dacie zakończenia.'
)]
class AutoCompleteReservationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationStatusLogService $statusLog,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $today = new \DateTimeImmutable('today');

        $reservations = $this->reservationRepository->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.endDate <= :today')
            ->setParameter('status', Reservation::STATUS_ACCEPTED)
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        $count = 0;

        foreach ($reservations as $reservation) {
            if (!$reservation instanceof Reservation) {
                continue;
            }

            $old = $reservation->getStatus();
            $reservation->setStatus(Reservation::STATUS_COMPLETED);

            $this->statusLog->log(
                $reservation,
                null,
                $old,
                Reservation::STATUS_COMPLETED,
                null,
                'cron',
                'Auto completed by cron after end date'
            );

            $count++;
        }

        $this->em->flush();

        $output->writeln('Zakończono rezerwacji: ' . $count);

        return Command::SUCCESS;
    }
}
