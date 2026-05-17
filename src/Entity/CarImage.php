<?php

namespace App\Entity;

use App\Entity\Car;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class CarImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Car $car = null;

    // nazwa pliku w /public/uploads
    #[ORM\Column(type:'string', length:255)]
    #[Assert\NotBlank]
    private string $path;

    // czy to zdjęcie główne (opcjonalnie)
    #[ORM\Column(type:'boolean')]
    private bool $isPrimary = false;

    #[ORM\Column(type:'integer')]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getCar(): ?Car { return $this->car; }
    public function setCar(?Car $car): self { $this->car = $car; return $this; }

    public function getPath(): string { return $this->path; }
    public function setPath(string $path): self { $this->path = $path; return $this; }

    public function isPrimary(): bool { return $this->isPrimary; }
    public function setIsPrimary(bool $isPrimary): self { $this->isPrimary = $isPrimary; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): self { $this->position = $position; return $this; }
}
