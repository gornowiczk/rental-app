<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_log')]
class AdminLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    // ✅ NULLABLE: bo przy login failure nie mamy usera
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'admin_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $admin = null;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $action = '';

    #[ORM\Column(type: Types::STRING, length: 60, nullable: true)]
    private ?string $entity = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $details = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->details = [];
    }

    public function getId(): ?int { return $this->id; }

    public function getAdmin(): ?User { return $this->admin; }
    public function setAdmin(?User $admin): self { $this->admin = $admin; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $action): self { $this->action = $action; return $this; }

    public function getEntity(): ?string { return $this->entity; }
    public function setEntity(?string $entity): self { $this->entity = $entity; return $this; }

    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(?int $entityId): self { $this->entityId = $entityId; return $this; }

    public function getIp(): ?string { return $this->ip; }
    public function setIp(?string $ip): self { $this->ip = $ip; return $this; }

    public function getDetails(): array { return $this->details ?? []; }
    public function setDetails(?array $details): self { $this->details = $details ?? []; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
