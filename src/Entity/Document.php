<?php

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Document
{
    public const TYPE_CONTRACT = 'contract';
    public const TYPE_INVOICE  = 'invoice';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Reservation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Reservation $reservation = null;

    // contract|invoice
    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    // NIE zapisujemy plików – generujesz on-the-fly.
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\PrePersist]
    public function onCreate(): void { $this->createdAt = new \DateTimeImmutable(); }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(Reservation $r): self { $this->reservation = $r; return $this; }

    public function getType(): string { return $this->type; }
    public function setType(string $t): self { $this->type = $t; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
