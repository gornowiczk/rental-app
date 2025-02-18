<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CarImage
{
#[ORM\Id]
#[ORM\GeneratedValue]
#[ORM\Column(type: 'integer')]
private ?int $id = null;

#[ORM\Column(type: 'string', length: 255)]
private string $imagePath;

#[ORM\ManyToOne(targetEntity: Car::class, inversedBy: 'images')]
#[ORM\JoinColumn(nullable: false)]
private ?Car $car = null;

public function getId(): ?int
{
return $this->id;
}

public function getImagePath(): ?string
{
return $this->imagePath;
}

public function setImagePath(string $imagePath): self
{
$this->imagePath = $imagePath;
return $this;
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
}
