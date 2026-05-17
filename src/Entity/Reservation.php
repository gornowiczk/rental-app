<?php

namespace App\Entity;

use App\Entity\Car;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Reservation
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Car $car = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $endDate;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $totalPrice = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $pricePerDay = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $rentalLocation = null;

    #[ORM\Column(type: 'boolean')]
    private bool $wantsInvoice = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $invoiceName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $invoiceAddress = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $invoiceNip = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $invoiceNumber = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $cancelledBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $cancellationFeeGross = null;

    #[ORM\Column(length:255, nullable:true)]
    private ?string $invoiceStreet = null;

    #[ORM\Column(length:20, nullable:true)]
    private ?string $invoicePostalCode = null;

    #[ORM\Column(length:120, nullable:true)]
    private ?string $invoiceCity = null;

    

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCar(): ?Car
    {
        return $this->car;
    }

    public function setCar(?Car $car): self
    {
        $this->car = $car;
        return $this;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        $this->recalculateTotalPrice();
        return $this;
    }

    public function getEndDate(): \DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        $this->recalculateTotalPrice();
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $allowed = [
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];

        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf('Nieprawidłowy status: %s', $status));
        }

        $this->status = $status;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment !== null ? trim($comment) : null;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string|int|float $totalPrice): self
    {
        $this->totalPrice = number_format((float) $totalPrice, 2, '.', '');
        return $this;
    }

    public function getPricePerDay(): ?string
    {
        return $this->pricePerDay;
    }

    public function setPricePerDay(?string $pricePerDay): self
    {
        $this->pricePerDay = $pricePerDay !== null
            ? number_format((float) $pricePerDay, 2, '.', '')
            : null;

        $this->recalculateTotalPrice();
        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber !== null ? trim($phoneNumber) : null;
        return $this;
    }

    public function getRentalLocation(): ?string
    {
        return $this->rentalLocation;
    }

    public function setRentalLocation(?string $rentalLocation): self
    {
        $this->rentalLocation = $rentalLocation !== null ? trim($rentalLocation) : null;
        return $this;
    }

    public function wantsInvoice(): bool
    {
        return $this->wantsInvoice;
    }

    public function setWantsInvoice(bool $wantsInvoice): self
    {
        $this->wantsInvoice = $wantsInvoice;
        return $this;
    }

    public function getInvoiceName(): ?string
    {
        return $this->invoiceName;
    }

    public function setInvoiceName(?string $invoiceName): self
    {
        $this->invoiceName = $invoiceName !== null ? trim($invoiceName) : null;
        return $this;
    }

    public function getInvoiceAddress(): ?string
    {
        return $this->invoiceAddress;
    }

    public function setInvoiceAddress(?string $invoiceAddress): self
    {
        $this->invoiceAddress = $invoiceAddress !== null ? trim($invoiceAddress) : null;
        return $this;
    }

    public function getInvoiceNip(): ?string
    {
        return $this->invoiceNip;
    }

    public function setInvoiceNip(?string $invoiceNip): self
    {
        $this->invoiceNip = $invoiceNip !== null ? trim($invoiceNip) : null;
        return $this;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber !== null ? trim($invoiceNumber) : null;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): self
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getCancelledBy(): ?string
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?string $cancelledBy): self
    {
        $this->cancelledBy = $cancelledBy !== null ? trim($cancelledBy) : null;
        return $this;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function setCancelReason(?string $cancelReason): self
    {
        $this->cancelReason = $cancelReason !== null ? trim($cancelReason) : null;
        return $this;
    }

    public function getCancellationFeeGross(): ?string
    {
        return $this->cancellationFeeGross;
    }

    public function setCancellationFeeGross(?string $cancellationFeeGross): self
    {
        $this->cancellationFeeGross = $cancellationFeeGross !== null
            ? number_format((float) $cancellationFeeGross, 2, '.', '')
            : null;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canGenerateInvoice(): bool
    {
        return $this->isCompleted() && $this->wantsInvoice();
    }

    public function getDurationInDays(): int
    {
        if (!isset($this->startDate, $this->endDate)) {
            return 1;
        }

        $start = \DateTimeImmutable::createFromInterface($this->startDate)->setTime(0, 0);
        $end = \DateTimeImmutable::createFromInterface($this->endDate)->setTime(0, 0);

        $days = max(1, (int) $start->diff($end)->format('%a'));

        return max(1, $days);
    }

    public function recalculateTotalPrice(): self
    {
        if ($this->pricePerDay === null || !isset($this->startDate, $this->endDate)) {
            return $this;
        }

        $total = $this->getDurationInDays() * (float) $this->pricePerDay;
        $this->totalPrice = number_format($total, 2, '.', '');

        return $this;
    }

    public function getInvoiceStreet(): ?string
    {
        return $this->invoiceStreet;
    }

    public function setInvoiceStreet(?string $street): self
    {
        $this->invoiceStreet = $street;
        return $this;
    }

    public function getInvoicePostalCode(): ?string
    {
        return $this->invoicePostalCode;
    }

    public function setInvoicePostalCode(?string $code): self
    {
        $this->invoicePostalCode = $code;
        return $this;
    }

    public function getInvoiceCity(): ?string
    {
        return $this->invoiceCity;
    }

    public function setInvoiceCity(?string $city): self
    {
        $this->invoiceCity = $city;
        return $this;
    }
}