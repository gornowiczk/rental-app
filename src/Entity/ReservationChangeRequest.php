<?php

namespace App\Entity;

use App\Repository\ReservationChangeRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationChangeRequestRepository::class)]
#[ORM\Table(name: 'reservation_change_request')]
#[ORM\Index(name: 'idx_rcr_status', columns: ['status'])]
#[ORM\Index(name: 'idx_rcr_reservation', columns: ['reservation_id'])]
class ReservationChangeRequest
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $requester = null;

    // Użytkownik, który rozpatrzył wniosek o zmianę terminu.
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $decidedBy = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $newStartDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $newEndDate = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $newPickupLocation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_PENDING;
    }

    public function getId(): ?int { return $this->id; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): self { $this->reservation = $reservation; return $this; }

    public function getRequester(): ?User { return $this->requester; }
    public function setRequester(?User $requester): self { $this->requester = $requester; return $this; }

    public function getDecidedBy(): ?User { return $this->decidedBy; }
    public function setDecidedBy(?User $decidedBy): self { $this->decidedBy = $decidedBy; return $this; }

    public function getNewStartDate(): ?\DateTimeImmutable { return $this->newStartDate; }
    public function setNewStartDate(\DateTimeImmutable $newStartDate): self { $this->newStartDate = $newStartDate; return $this; }

    public function getNewEndDate(): ?\DateTimeImmutable { return $this->newEndDate; }
    public function setNewEndDate(\DateTimeImmutable $newEndDate): self { $this->newEndDate = $newEndDate; return $this; }

    public function getNewPickupLocation(): ?string { return $this->newPickupLocation; }
    public function setNewPickupLocation(?string $newPickupLocation): self { $this->newPickupLocation = $newPickupLocation; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): self { $this->message = $message; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function setDecidedAt(?\DateTimeImmutable $decidedAt): self { $this->decidedAt = $decidedAt; return $this; }

    public function accept(User $by): self
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->decidedBy = $by;
        $this->decidedAt = new \DateTimeImmutable();
        return $this;
    }

    public function reject(User $by): self
    {
        $this->status = self::STATUS_REJECTED;
        $this->decidedBy = $by;
        $this->decidedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }
}