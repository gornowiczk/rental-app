<?php

namespace App\Controller;

use App\Entity\AdminLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/logs')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class AdminLogController extends AbstractController
{
    #[Route('/logs', name: 'admin_logs', methods: ['GET'])]
    public function logs(Request $request, EntityManagerInterface $em): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $action = trim((string) $request->query->get('action', ''));
        $from = trim((string) $request->query->get('from', '')); // YYYY-MM-DD
        $to = trim((string) $request->query->get('to', ''));     // YYYY-MM-DD

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 50;

        $qb = $em->getRepository(\App\Entity\AdminLog::class)->createQueryBuilder('l')
            ->leftJoin('l.admin', 'a')->addSelect('a')
            ->orderBy('l.id', 'DESC');

        if ($action !== '') {
            $qb->andWhere('l.action = :act')->setParameter('act', $action);
        }

        if ($q !== '') {
            $qb->andWhere('l.action LIKE :q OR l.entity LIKE :q OR a.email LIKE :q OR l.ip LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        // daty: from/to (po createdAt)
        if ($from !== '') {
            try {
                $fromDt = new \DateTimeImmutable($from . ' 00:00:00');
                $qb->andWhere('l.createdAt >= :from')->setParameter('from', $fromDt);
            } catch (\Throwable $e) {
                // ignorujemy błędną datę
            }
        }

        if ($to !== '') {
            try {
                $toDt = new \DateTimeImmutable($to . ' 23:59:59');
                $qb->andWhere('l.createdAt <= :to')->setParameter('to', $toDt);
            } catch (\Throwable $e) {
                // ignorujemy błędną datę
            }
        }

        // COUNT (dla paginacji)
        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(l.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $logs = $qb
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        // lista akcji do selecta (distinct)
        $actions = $em->createQueryBuilder()
            ->select('DISTINCT l2.action')
            ->from(\App\Entity\AdminLog::class, 'l2')
            ->orderBy('l2.action', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->render('admin/logs.html.twig', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => [
                'q' => $q,
                'action' => $action,
                'from' => $from,
                'to' => $to,
            ],
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'perPage' => $perPage,
                'total' => $total,
            ],
        ]);
    }

}