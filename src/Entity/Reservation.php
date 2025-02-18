<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use App\Entity\Car;

#[ORM\Entity]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "reservations")]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user;

    #[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'reservations', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Car $car = null;

    #[ORM\Column(type: "datetime")]
    private \DateTime $startDate;

    #[ORM\Column(type: "datetime")]
    private \DateTime $endDate;

    #[ORM\Column(type: "boolean")]
    private bool $confirmed = false;

    #[ORM\Column(type: "string", length: 20, options: ["default" => "pending"])]
    private string $status = 'pending';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;


    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $rentalLocation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comments = null;

    public function getComments(): ?string
    {
        return $this->comments;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;
        return $this;
    }
    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getRentalLocation(): ?string
    {
        return $this->rentalLocation;
    }

    public function setRentalLocation(?string $rentalLocation): self
    {
        $this->rentalLocation = $rentalLocation;
        return $this;
    }

    // Gettery i Settery
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getRentalDays(): int
    {
        return $this->startDate->diff($this->endDate)->days;
    }


    public function markAsCompleted(): void
    {
        $this->status = 'completed';
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCar(): ?Car
    {
        return $this->car;
    }

    public function setCar(Car $car): self
    {
        $this->car = $car;
        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTime $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function setConfirmed(bool $confirmed): self
    {
        $this->confirmed = $confirmed;
        return $this;
    }
}
