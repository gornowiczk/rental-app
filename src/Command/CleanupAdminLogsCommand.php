<?php

namespace App\Command;

use App\Entity\AdminLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:cleanup-admin-logs',
    description: 'Usuwa stare logi admina'
)]
class CleanupAdminLogsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_OPTIONAL,
            'Ile dni trzymać logi',
            90
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');

        $cutoff = new \DateTimeImmutable(sprintf('-%d days', $days));

        $qb = $this->em->createQueryBuilder();

        $query = $qb->delete(AdminLog::class, 'l')
            ->where('l.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery();

        $deleted = $query->execute();

        $output->writeln(sprintf(
            'Usunięto %d logów starszych niż %d dni (%s)',
            $deleted,
            $days,
            $cutoff->format('Y-m-d H:i')
        ));

        return Command::SUCCESS;
    }
}