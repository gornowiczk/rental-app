<?php

namespace App\Command;

use App\Entity\Reservation;
use App\Service\NotificationService;
use App\Service\ReservationStatusLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:reservations:complete',
    description: 'Oznacza zakończone rezerwacje jako zrealizowane.'
)]
final class CompleteReservationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationService $notifier,
        private readonly ReservationStatusLogService $statusLog,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Uruchamia komendę bez zapisu zmian w bazie')
            ->addOption('notify', null, InputOption::VALUE_NONE, 'Wysyła powiadomienia, jeśli są dostępne');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $notify = (bool) $input->getOption('notify');

        $today = new \DateTimeImmutable('today');

        $reservations = $this->em->getRepository(Reservation::class)->createQueryBuilder('r')
            ->andWhere('r.status = :accepted')
            ->andWhere('r.endDate < :today')
            ->setParameter('accepted', 'accepted')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        $count = 0;

        foreach ($reservations as $r) {
            if (!$r instanceof Reservation) {
                continue;
            }

            $old = $r->getStatus();
            $r->setStatus('completed');
            $count++;

            $this->statusLog->log(
                $r,
                null,
                $old,
                'completed',
                null,
                'cron',
                'Automatycznie zakończono przez harmonogram'
            );

            if ($notify && method_exists($this->notifier, 'reservationCompleted')) {
                $this->notifier->reservationCompleted($r);
            }
        }

        if ($count === 0) {
            $output->writeln('<info>Brak rezerwacji do zakończenia.</info>');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $output->writeln(sprintf('<comment>[TRYB TESTOWY]</comment> Liczba rezerwacji do zakończenia: %d.', $count));
            $this->em->clear();
            return Command::SUCCESS;
        }

        $this->em->flush();

        $output->writeln(sprintf('<info>Zakończono rezerwacji: %d.</info>', $count));
        return Command::SUCCESS;
    }
}
