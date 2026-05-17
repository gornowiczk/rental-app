<?php

namespace App\Entity;

use App\Entity\User;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\CarImage;
use App\Entity\CarServiceLog;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Car
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'cars')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $brand;

    #[ORM\Column(type: 'string', length: 100)]
    private string $model;

    #[ORM\Column(type: 'integer')]
    private int $year;

    #[ORM\Column(type: 'string', length: 20)]
    private string $registrationNumber;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $pricePerDay;

    #[ORM\Column(type: 'string', length: 120, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable = true;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $mainImage = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $gallery = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $pausedUntil = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $vin = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $mileage = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $fuelType = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $transmission = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $insuranceValidUntil = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $inspectionValidUntil = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastServiceDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(mappedBy: 'car', targetEntity: Reservation::class, cascade: ['remove'])]
    private Collection $reservations;

    /** @var Collection<int, Review> */
    #[ORM\OneToMany(mappedBy: 'car', targetEntity: Review::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $reviews;

    /** @var Collection<int, CarImage> */
    #[ORM\OneToMany(mappedBy: 'car', targetEntity: CarImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $images;

    /** @var Collection<int, CarServiceLog> */
    #[ORM\OneToMany(mappedBy: 'car', targetEntity: CarServiceLog::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $serviceLogs;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
        $this->gallery = [];
        $this->reviews = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->serviceLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): self
    {
        $this->brand = $brand;
        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;
        return $this;
    }

    public function getRegistrationNumber(): string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(string $registrationNumber): self
    {
        $this->registrationNumber = $registrationNumber;
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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): self
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    public function getMainImage(): ?string
    {
        return $this->mainImage;
    }

    public function setMainImage(?string $mainImage): self
    {
        $this->mainImage = $mainImage;
        return $this;
    }

    public function getGallery(): array
    {
        return $this->gallery ?? [];
    }

    public function setGallery(?array $gallery): self
    {
        $this->gallery = $gallery ?? [];
        return $this;
    }

    public function getPausedUntil(): ?\DateTimeInterface
    {
        return $this->pausedUntil;
    }

    public function setPausedUntil(?\DateTimeInterface $pausedUntil): self
    {
        $this->pausedUntil = $pausedUntil;
        return $this;
    }

    public function getVin(): ?string
    {
        return $this->vin;
    }

    public function setVin(?string $vin): self
    {
        $this->vin = $vin;
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

    public function getFuelType(): ?string
    {
        return $this->fuelType;
    }

    public function setFuelType(?string $fuelType): self
    {
        $this->fuelType = $fuelType;
        return $this;
    }

    public function getTransmission(): ?string
    {
        return $this->transmission;
    }

    public function setTransmission(?string $transmission): self
    {
        $this->transmission = $transmission;
        return $this;
    }

    public function getInsuranceValidUntil(): ?\DateTimeInterface
    {
        return $this->insuranceValidUntil;
    }

    public function setInsuranceValidUntil(?\DateTimeInterface $insuranceValidUntil): self
    {
        $this->insuranceValidUntil = $insuranceValidUntil;
        return $this;
    }

    public function getInspectionValidUntil(): ?\DateTimeInterface
    {
        return $this->inspectionValidUntil;
    }

    public function setInspectionValidUntil(?\DateTimeInterface $inspectionValidUntil): self
    {
        $this->inspectionValidUntil = $inspectionValidUntil;
        return $this;
    }

    public function getLastServiceDate(): ?\DateTimeInterface
    {
        return $this->lastServiceDate;
    }

    public function setLastServiceDate(?\DateTimeInterface $lastServiceDate): self
    {
        $this->lastServiceDate = $lastServiceDate;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function isTemporarilyPaused(): bool
    {
        return $this->pausedUntil !== null && $this->pausedUntil > new \DateTime();
    }

    public function isRentable(): bool
    {
        return $this->isAvailable() && !$this->isTemporarilyPaused();
    }

    /** @return Collection<int, Reservation> */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): self
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setCar($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): self
    {
        if ($this->reservations->removeElement($reservation) && $reservation->getCar() === $this) {
            $reservation->setCar(null);
        }

        return $this;
    }

    /** @return Collection<int, Review> */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setCar($this);
        }

        return $this;
    }

    public function removeReview(Review $review): self
    {
        if ($this->reviews->removeElement($review) && $review->getCar() === $this) {
            $review->setCar(null);
        }

        return $this;
    }

    /** @return Collection<int, CarImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(CarImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setCar($this);
        }

        return $this;
    }

    public function removeImage(CarImage $image): self
    {
        if ($this->images->removeElement($image) && $image->getCar() === $this) {
            $image->setCar(null);
        }

        return $this;
    }

    /** @return Collection<int, CarServiceLog> */
    public function getServiceLogs(): Collection
    {
        return $this->serviceLogs;
    }

    public function addServiceLog(CarServiceLog $serviceLog): self
    {
        if (!$this->serviceLogs->contains($serviceLog)) {
            $this->serviceLogs->add($serviceLog);
            $serviceLog->setCar($this);
        }

        return $this;
    }

    public function removeServiceLog(CarServiceLog $serviceLog): self
    {
        if ($this->serviceLogs->removeElement($serviceLog)) {
            if ($serviceLog->getCar() === $this) {
                $serviceLog->setCar(null);
            }
        }

        return $this;
    }
}