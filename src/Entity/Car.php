<?php

namespace App\Entity;

use App\Repository\CarRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarRepository::class)]
#[ORM\Table(name: 'cars')]
class Car
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $brand = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $model = null;

    #[ORM\Column(type: 'integer')]
    private ?int $year = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $registrationNumber = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $pricePerDay = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $mainImage = null;

    #[ORM\OneToMany(mappedBy: 'car', targetEntity: CarImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $images;

    #[ORM\OneToMany(mappedBy: 'car', targetEntity: Reservation::class, cascade: ['remove'])]
    private Collection $reservations;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable = true;
    #[ORM\Column(type: 'json', nullable: true)]
    private array $gallery = [];


    public function __construct()
    {
        $this->gallery = [];
    }

    public function getGallery(): array
    {
        return $this->gallery ?? [];
    }

    public function setGallery(array $gallery): self
    {
        $this->gallery = $gallery;
        return $this;
    }





    public function getIsAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): self
    {
        $this->isAvailable = $isAvailable;
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



    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(string $brand): self
    {
        $this->brand = $brand;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;
        return $this;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(string $registrationNumber): self
    {
        $this->registrationNumber = $registrationNumber;
        return $this;
    }

    public function getPricePerDay(): ?float
    {
        return $this->pricePerDay;
    }

    public function setPricePerDay(float $price): self
    {
        $this->pricePerDay = (string) $price;
        return $this;
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

    public function getMainImage(): ?string
    {
        return $this->mainImage;
    }

    public function setMainImage(?string $mainImage): self
    {
        $this->mainImage = $mainImage;
        return $this;
    }

    /**
     * @return Collection<int, CarImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(CarImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images[] = $image;
            $image->setCar($this);
        }
        return $this;
    }

    public function removeImage(CarImage $image): self
    {
        if ($this->images->removeElement($image)) {
            if ($image->getCar() === $this) {
                $image->setCar(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): self
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations[] = $reservation;
            $reservation->setCar($this);
        }
        return $this;
    }

    public function removeReservation(Reservation $reservation): self
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getCar() === $this) {
                $reservation->setCar(null);
            }
        }
        return $this;
    }


    public function addImageToGallery(string $imagePath): self
    {
        if (!in_array($imagePath, $this->gallery)) {
            $this->gallery[] = $imagePath;
        }
        return $this;
    }

    public function removeImageFromGallery(string $imagePath): self
    {
        if (($key = array_search($imagePath, $this->gallery)) !== false) {
            unset($this->gallery[$key]);
        }
        return $this;
    }
}
