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
    description: 'Marks accepted reservations as completed when endDate is in the past.'
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not write changes to DB')
            ->addOption('notify', null, InputOption::VALUE_NONE, 'Send notifications (if implemented)');
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
            if (!$r instanceof Reservation) continue;

            $old = $r->getStatus();
            $r->setStatus('completed');
            $count++;

            // ✅ AUDYT (cron)
            $this->statusLog->log(
                $r,
                null,
                $old,
                'completed',
                null,
                'cron',
                'Auto-completed by scheduler'
            );

            if ($notify && method_exists($this->notifier, 'reservationCompleted')) {
                $this->notifier->reservationCompleted($r);
            }
        }

        if ($count === 0) {
            $output->writeln('<info>No reservations to complete.</info>');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $output->writeln(sprintf('<comment>[DRY RUN]</comment> Would complete %d reservation(s).', $count));
            $this->em->clear();
            return Command::SUCCESS;
        }

        $this->em->flush();

        $output->writeln(sprintf('<info>Completed %d reservation(s).</info>', $count));
        return Command::SUCCESS;
    }
}
