<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Reservation;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class InvoiceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InvoiceRepository $invoiceRepo,
        private readonly ParameterBagInterface $params,
    ) {}

    /**
     * Tworzy fakturę (snapshot) jeśli:
     * - rezerwacja ma status completed
     * - wantsInvoice = true
     * - nie istnieje jeszcze Invoice dla tej rezerwacji
     */
    public function ensureInvoiceForReservation(Reservation $reservation): ?Invoice
    {
        if ((string) $reservation->getStatus() !== 'completed') {
            return null;
        }

        if (!method_exists($reservation, 'wantsInvoice') || !$reservation->wantsInvoice()) {
            return null;
        }

        $existing = $this->invoiceRepo->findOneByReservationId((int) $reservation->getId());
        if ($existing) {
            return $existing;
        }

        try {
            $invoice = $this->createInvoiceSnapshot($reservation);
            $this->em->persist($invoice);

            if (method_exists($reservation, 'setInvoiceNumber')) {
                $reservation->setInvoiceNumber($invoice->getInvoiceNumber());
            }

            $this->em->flush();

            return $invoice;
        } catch (UniqueConstraintViolationException) {
            $this->em->clear();

            return $this->invoiceRepo->findOneByReservationId((int) $reservation->getId());
        }
    }

    private function createInvoiceSnapshot(Reservation $reservation): Invoice
    {
        $invoice = new Invoice();
        $invoice->setReservation($reservation);

        $issuedAt = new \DateTimeImmutable('today');
        $saleDate = $reservation->getStartDate() instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($reservation->getStartDate())
            : new \DateTimeImmutable('today');

        $invoice->setIssuedAt($issuedAt);
        $invoice->setSaleDate($saleDate);

        $vatRateStr = (string) $this->getParam('vat_rate', '0.2300');
        $invoice->setVatRate($vatRateStr);

        $invoice->setSellerName((string) $this->getParam('seller_name', 'CarRental'));
        $invoice->setSellerAddress((string) $this->getParam('seller_address', ''));
        $invoice->setSellerNip((string) $this->getParam('seller_nip', ''));
        $invoice->setSellerBank($this->nullableString($this->getParam('seller_bank', null)));
        $invoice->setSellerIban($this->nullableString($this->getParam('seller_iban', null)));

        $buyerName = trim((string) $reservation->getInvoiceName());

        $street = method_exists($reservation, 'getInvoiceStreet')
            ? trim((string) $reservation->getInvoiceStreet())
            : '';

        $postalCode = method_exists($reservation, 'getInvoicePostalCode')
            ? trim((string) $reservation->getInvoicePostalCode())
            : '';

        $city = method_exists($reservation, 'getInvoiceCity')
            ? trim((string) $reservation->getInvoiceCity())
            : '';

        $buyerAddr = trim($street . ', ' . $postalCode . ' ' . $city);
        $buyerAddr = trim($buyerAddr, " ,");

        if ($buyerAddr === '') {
            $buyerAddr = trim((string) $reservation->getInvoiceAddress());
        }

        $buyerNip = trim((string) $reservation->getInvoiceNip());

        if ($buyerName === '' || $buyerAddr === '') {
            throw new \RuntimeException('Brak danych nabywcy do faktury.');
        }

        $invoice->setBuyerName($buyerName);
        $invoice->setBuyerAddress($buyerAddr);
        $invoice->setBuyerNip($buyerNip !== '' ? $buyerNip : null);

        $car = $reservation->getCar();
        $itemName = sprintf(
            'Wynajem samochodu %s %s (%s)',
            (string) $car->getBrand(),
            (string) $car->getModel(),
            (string) $car->getRegistrationNumber()
        );
        $invoice->setItemName($itemName);

        $days = method_exists($reservation, 'getDurationInDays')
            ? $reservation->getDurationInDays()
            : $this->rentalDays($reservation->getStartDate(), $reservation->getEndDate());

        $invoice->setDays($days);

        $pricePerDay = $reservation->getPricePerDay() !== null
            ? (float) $reservation->getPricePerDay()
            : (float) $car->getPricePerDay();

        $invoice->setPricePerDay($this->money($pricePerDay));

        $gross = $reservation->getTotalPrice() !== null
            ? (float) $reservation->getTotalPrice()
            : ($days * $pricePerDay);

        $vat = (float) $vatRateStr;
        $net = $gross / (1.0 + $vat);
        $vatAmount = $gross - $net;

        $invoice->setGrossTotal($this->money($gross));
        $invoice->setNetTotal($this->money($net));
        $invoice->setVatAmount($this->money($vatAmount));

        $invoiceNumber = $this->generateMonthlyNumber($issuedAt);
        $invoice->setInvoiceNumber($invoiceNumber);

        return $invoice;
    }

    private function generateMonthlyNumber(\DateTimeImmutable $date): string
    {
        $prefix = 'FV/'.$date->format('m/Y').'/';

        $last = $this->invoiceRepo->createQueryBuilder('i')
            ->select('i.invoiceNumber')
            ->andWhere('i.invoiceNumber LIKE :p')
            ->setParameter('p', $prefix.'%')
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $next = 1;

        if (is_array($last) && isset($last['invoiceNumber'])) {
            $num = (string) $last['invoiceNumber'];
            $parts = explode('/', $num);
            $tail = end($parts);
            if ($tail !== false && ctype_digit($tail)) {
                $next = ((int) $tail) + 1;
            }
        }

        return $prefix.sprintf('%04d', $next);
    }

   private function rentalDays(?\DateTimeInterface $start, ?\DateTimeInterface $end): int
    {
        if (!$start || !$end) {
            return 1;
        }

        $s = \DateTimeImmutable::createFromInterface($start)->setTime(0, 0);
        $e = \DateTimeImmutable::createFromInterface($end)->setTime(0, 0);

        $days = (int) (($e->getTimestamp() - $s->getTimestamp()) / 86400);

        return max(1, $days);
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    private function getParam(string $key, mixed $default = null): mixed
    {
        if (method_exists($this->params, 'has') && $this->params->has($key)) {
            return $this->params->get($key);
        }

        return $default;
    }

    private function nullableString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        $s = trim((string) $v);
        return $s === '' ? null : $s;
    }
}