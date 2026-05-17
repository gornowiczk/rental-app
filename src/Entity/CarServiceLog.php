<?php

namespace App\Entity;

use App\Repository\CarServiceLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarServiceLogRepository::class)]
class CarServiceLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'serviceLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Car $car = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type = 'service';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $serviceDate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $mileage = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $cost = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->serviceDate = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getServiceDate(): ?\DateTimeInterface
    {
        return $this->serviceDate;
    }

    public function setServiceDate(?\DateTimeInterface $serviceDate): self
    {
        $this->serviceDate = $serviceDate;
        return $this;
    }

    public function getMileage(): ?int
    {
        return $this->mileage;
    }

    public function setMileage(?int $mileage): self
    {
        $this->mileage = $mileage;
        return $this;
    }

    public function getCost(): ?string
    {
        return $this->cost;
    }

    public function setCost(?string $cost): self
    {
        $this->cost = $cost;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'service' => 'Serwis',
            'repair' => 'Naprawa',
            'inspection' => 'Przegląd',
            'insurance' => 'Ubezpieczenie',
            'tires' => 'Opony',
            default => 'Inne',
        };
    }
}