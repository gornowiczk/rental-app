<?php

namespace App\Command;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:reservations:tick', description: 'Auto: accepted->active, active->completed')]
final class ReservationsTickCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $today = $now->setTime(0, 0);

        // accepted -> active (gdy startDate <= dziś)
        $accepted = $this->em->getRepository(Reservation::class)->createQueryBuilder('r')
            ->andWhere('r.status = :st')->setParameter('st', 'accepted')
            ->andWhere('r.startDate <= :today')->setParameter('today', $today)
            ->getQuery()->getResult();

        $toActive = 0;
        foreach ($accepted as $r) {
            if ($r instanceof Reservation) {
                $r->setStatus('active');
                $toActive++;
            }
        }

        // active -> completed (gdy endDate < dziś)
        $active = $this->em->getRepository(Reservation::class)->createQueryBuilder('r')
            ->andWhere('r.status = :st')->setParameter('st', 'active')
            ->andWhere('r.endDate < :today')->setParameter('today', $today)
            ->getQuery()->getResult();

        $toCompleted = 0;
        foreach ($active as $r) {
            if ($r instanceof Reservation) {
                $r->setStatus('completed');
                $toCompleted++;
            }
        }

        $this->em->flush();

        $output->writeln(sprintf('OK: active=%d, completed=%d', $toActive, $toCompleted));
        return Command::SUCCESS;
    }
}
