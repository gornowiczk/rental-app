<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoice')]
#[ORM\UniqueConstraint(name: 'uniq_invoice_number', columns: ['invoice_number'])]
#[ORM\UniqueConstraint(name: 'uniq_invoice_reservation', columns: ['reservation_id'])]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Jedna faktura do jednej rezerwacji (snapshot)
     * Nie musisz dodawać nic w Reservation.php
     */
    #[ORM\OneToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reservation $reservation = null;

    #[ORM\Column(name: 'invoice_number', type: Types::STRING, length: 64)]
    private string $invoiceNumber;

    #[ORM\Column(name: 'issued_at', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(name: 'sale_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $saleDate;

    // --- Sprzedawca (CarRental) snapshot ---
    #[ORM\Column(name: 'seller_name', type: Types::STRING, length: 255)]
    private string $sellerName;

    #[ORM\Column(name: 'seller_address', type: Types::STRING, length: 255)]
    private string $sellerAddress;

    #[ORM\Column(name: 'seller_nip', type: Types::STRING, length: 32)]
    private string $sellerNip;

    #[ORM\Column(name: 'seller_bank', type: Types::STRING, length: 255, nullable: true)]
    private ?string $sellerBank = null;

    #[ORM\Column(name: 'seller_iban', type: Types::STRING, length: 64, nullable: true)]
    private ?string $sellerIban = null;

    // --- Nabywca snapshot ---
    #[ORM\Column(name: 'buyer_name', type: Types::STRING, length: 255)]
    private string $buyerName;

    #[ORM\Column(name: 'buyer_address', type: Types::STRING, length: 255)]
    private string $buyerAddress;

    #[ORM\Column(name: 'buyer_nip', type: Types::STRING, length: 32, nullable: true)]
    private ?string $buyerNip = null;

    // --- Pozycja (na start: 1 pozycja = wynajem auta) ---
    #[ORM\Column(name: 'item_name', type: Types::STRING, length: 255)]
    private string $itemName;

    #[ORM\Column(name: 'days', type: Types::INTEGER)]
    private int $days = 1;

    #[ORM\Column(name: 'price_per_day', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $pricePerDay;

    // --- Podsumowania ---
    #[ORM\Column(name: 'vat_rate', type: Types::DECIMAL, precision: 5, scale: 4)]
    private string $vatRate = '0.2300'; // 23% = 0.2300

    #[ORM\Column(name: 'net_total', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $netTotal;

    #[ORM\Column(name: 'vat_amount', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $vatAmount;

    #[ORM\Column(name: 'gross_total', type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $grossTotal;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->issuedAt  = new \DateTimeImmutable();
        $this->saleDate  = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(Reservation $reservation): self
    {
        $this->reservation = $reservation;
        return $this;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;
        return $this;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(\DateTimeImmutable $issuedAt): self
    {
        $this->issuedAt = $issuedAt;
        return $this;
    }

    public function getSaleDate(): \DateTimeImmutable
    {
        return $this->saleDate;
    }

    public function setSaleDate(\DateTimeImmutable $saleDate): self
    {
        $this->saleDate = $saleDate;
        return $this;
    }

    public function getSellerName(): string
    {
        return $this->sellerName;
    }

    public function setSellerName(string $sellerName): self
    {
        $this->sellerName = $sellerName;
        return $this;
    }

    public function getSellerAddress(): string
    {
        return $this->sellerAddress;
    }

    public function setSellerAddress(string $sellerAddress): self
    {
        $this->sellerAddress = $sellerAddress;
        return $this;
    }

    public function getSellerNip(): string
    {
        return $this->sellerNip;
    }

    public function setSellerNip(string $sellerNip): self
    {
        $this->sellerNip = $sellerNip;
        return $this;
    }

    public function getSellerBank(): ?string
    {
        return $this->sellerBank;
    }

    public function setSellerBank(?string $sellerBank): self
    {
        $this->sellerBank = $sellerBank;
        return $this;
    }

    public function getSellerIban(): ?string
    {
        return $this->sellerIban;
    }

    public function setSellerIban(?string $sellerIban): self
    {
        $this->sellerIban = $sellerIban;
        return $this;
    }

    public function getBuyerName(): string
    {
        return $this->buyerName;
    }

    public function setBuyerName(string $buyerName): self
    {
        $this->buyerName = $buyerName;
        return $this;
    }

    public function getBuyerAddress(): string
    {
        return $this->buyerAddress;
    }

    public function setBuyerAddress(string $buyerAddress): self
    {
        $this->buyerAddress = $buyerAddress;
        return $this;
    }

    public function getBuyerNip(): ?string
    {
        return $this->buyerNip;
    }

    public function setBuyerNip(?string $buyerNip): self
    {
        $this->buyerNip = $buyerNip;
        return $this;
    }

    public function getItemName(): string
    {
        return $this->itemName;
    }

    public function setItemName(string $itemName): self
    {
        $this->itemName = $itemName;
        return $this;
    }

    public function getDays(): int
    {
        return $this->days;
    }

    public function setDays(int $days): self
    {
        $this->days = max(1, $days);
        return $this;
    }

    public function getPricePerDay(): string
    {
        return $this->pricePerDay;
    }

    public function setPricePerDay(string $pricePerDay): self
    {
        $this->pricePerDay = $pricePerDay;
        return $this;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): self
    {
        $this->vatRate = $vatRate;
        return $this;
    }

    public function getNetTotal(): string
    {
        return $this->netTotal;
    }

    public function setNetTotal(string $netTotal): self
    {
        $this->netTotal = $netTotal;
        return $this;
    }

    public function getVatAmount(): string
    {
        return $this->vatAmount;
    }

    public function setVatAmount(string $vatAmount): self
    {
        $this->vatAmount = $vatAmount;
        return $this;
    }

    public function getGrossTotal(): string
    {
        return $this->grossTotal;
    }

    public function setGrossTotal(string $grossTotal): self
    {
        $this->grossTotal = $grossTotal;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
