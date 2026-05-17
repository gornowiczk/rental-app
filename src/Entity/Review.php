<?php

namespace App\Entity;

use App\Entity\Car;
use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Car $car = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type:'smallint')]
    #[Assert\Range(min: 1, max: 5)]
    private int $rating = 5;

    #[ORM\Column(type:'text')]
    #[Assert\NotBlank]
    private string $content;

    #[ORM\Column(type:'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getCar(): ?Car { return $this->car; }
    public function setCar(?Car $car): self { $this->car = $car; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getRating(): int { return $this->rating; }
    public function setRating(int $rating): self { $this->rating = $rating; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): self { $this->content = $content; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
