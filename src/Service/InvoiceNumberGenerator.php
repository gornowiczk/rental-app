<?php

namespace App\Service;

use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;

final class InvoiceNumberGenerator
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Numeracja miesięczna: FV/YYYY/MM/0001
     * Przykład: FV/2026/01/0007
     */
    public function generateForReservation(Reservation $reservation): string
    {
        // Numerujemy wg miesiąca daty startu rezerwacji (możesz zmienić na "now")
        $baseDate = $reservation->getStartDate() ?? new \DateTimeImmutable();

        $year  = $baseDate->format('Y');
        $month = $baseDate->format('m');

        $prefix = sprintf('FV/%s/%s/', $year, $month); // np. FV/2026/01/

        // Szukamy maksymalnego invoiceNumber z tego miesiąca
        $max = $this->em->createQueryBuilder()
            ->select('MAX(r.invoiceNumber)')
            ->from(Reservation::class, 'r')
            ->andWhere('r.invoiceNumber LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult();

        $next = 1;

        if (is_string($max) && $max !== '') {
            // oczekujemy końcówki /0001
            if (preg_match('~^FV/\d{4}/\d{2}/(\d+)$~', $max, $m)) {
                $next = ((int) $m[1]) + 1;
            }
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
