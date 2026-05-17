<?php

namespace App\Entity;

use App\Repository\ReservationStatusLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationStatusLogRepository::class)]
#[ORM\Table(name: 'reservation_status_log')]
#[ORM\Index(name: 'idx_rsl_reservation', columns: ['reservation_id'])]
#[ORM\Index(name: 'idx_rsl_changed_at', columns: ['changed_at'])]
class ReservationStatusLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(name: 'old_status', type: Types::STRING, length: 32, nullable: true)]
    private ?string $oldStatus = null;

    #[ORM\Column(name: 'new_status', type: Types::STRING, length: 32)]
    private string $newStatus;

    #[ORM\Column(name: 'changed_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $changedAt;

    #[ORM\Column(name: 'ip_address', type: Types::STRING, length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', type: Types::STRING, length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'note', type: Types::STRING, length: 255, nullable: true)]
    private ?string $note = null;

    public function __construct()
    {
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(Reservation $reservation): self { $this->reservation = $reservation; return $this; }

    public function getActor(): ?User { return $this->actor; }
    public function setActor(?User $actor): self { $this->actor = $actor; return $this; }

    public function getOldStatus(): ?string { return $this->oldStatus; }
    public function setOldStatus(?string $oldStatus): self { $this->oldStatus = $oldStatus; return $this; }

    public function getNewStatus(): string { return $this->newStatus; }
    public function setNewStatus(string $newStatus): self { $this->newStatus = $newStatus; return $this; }

    public function getChangedAt(): \DateTimeImmutable { return $this->changedAt; }
    public function setChangedAt(\DateTimeImmutable $changedAt): self { $this->changedAt = $changedAt; return $this; }

    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): self { $this->ipAddress = $ipAddress; return $this; }

    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $userAgent): self { $this->userAgent = $userAgent; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): self { $this->note = $note; return $this; }
}
