<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password = '';

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $peselOrNip = null;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, cascade: ['remove'])]
    private Collection $notifications;

    /** @var Collection<int, Reservation> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Reservation::class, orphanRemoval: true)]
    private Collection $reservations;

    /** @var Collection<int, Car> */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Car::class, orphanRemoval: true)]
    private Collection $cars;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastSeenAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isBlocked = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $blockedReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $blockedAt = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $forcePasswordChange = false;

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->cars = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @deprecated */
    public function getUsername(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;

        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = array_values(array_unique($roles));
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        $fn = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
        return $fn !== '' ? $fn : $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getPeselOrNip(): ?string
    {
        return $this->peselOrNip;
    }

    public function setPeselOrNip(?string $peselOrNip): self
    {
        $this->peselOrNip = $peselOrNip;
        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeInterface
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeInterface $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;
        return $this;
    }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
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
            $reservation->setUser($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): self
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getUser() === $this) {
                $reservation->setUser(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, Car> */
    public function getCars(): Collection
    {
        return $this->cars;
    }

    public function addCar(Car $car): self
    {
        if (!$this->cars->contains($car)) {
            $this->cars->add($car);
            $car->setOwner($this);
        }

        return $this;
    }

    public function removeCar(Car $car): self
    {
        if ($this->cars->removeElement($car)) {
            if ($car->getOwner() === $this) {
                $car->setOwner(null);
            }
        }

        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function setIsBlocked(bool $isBlocked): self
    {
        $this->isBlocked = $isBlocked;
        return $this;
    }

    public function getBlockedReason(): ?string
    {
        return $this->blockedReason;
    }

    public function setBlockedReason(?string $blockedReason): self
    {
        $this->blockedReason = $blockedReason;
        return $this;
    }

    public function getBlockedAt(): ?\DateTimeInterface
    {
        return $this->blockedAt;
    }

    public function setBlockedAt(?\DateTimeInterface $blockedAt): self
    {
        $this->blockedAt = $blockedAt;
        return $this;
    }

    public function isForcePasswordChange(): bool
    {
        return $this->forcePasswordChange;
    }

    public function setForcePasswordChange(bool $forcePasswordChange): self
    {
        $this->forcePasswordChange = $forcePasswordChange;
        return $this;
    }
}