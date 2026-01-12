<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShelfRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShelfRepository::class)]
#[ORM\Table(name: 'shelves')]
class Shelf
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank]
    private string $code;

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\NotBlank]
    private string $aisle;

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\NotBlank]
    private string $rack;

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\NotBlank]
    private string $level;

    #[ORM\Column(type: 'integer')]
    private int $maxCapacity = 100;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'shelf', targetEntity: Stock::class)]
    private Collection $stocks;

    public function __construct()
    {
        $this->stocks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getAisle(): string
    {
        return $this->aisle;
    }

    public function setAisle(string $aisle): self
    {
        $this->aisle = $aisle;
        return $this;
    }

    public function getRack(): string
    {
        return $this->rack;
    }

    public function setRack(string $rack): self
    {
        $this->rack = $rack;
        return $this;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): self
    {
        $this->level = $level;
        return $this;
    }

    public function getMaxCapacity(): int
    {
        return $this->maxCapacity;
    }

    public function setMaxCapacity(int $maxCapacity): self
    {
        $this->maxCapacity = $maxCapacity;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getStocks(): Collection
    {
        return $this->stocks;
    }

    public function getCurrentOccupancy(): int
    {
        $total = 0;
        foreach ($this->stocks as $stock) {
            $total += $stock->getQuantity();
        }
        return $total;
    }

    public function getAvailableCapacity(): int
    {
        return $this->maxCapacity - $this->getCurrentOccupancy();
    }

    public function hasAvailableSpace(int $quantity = 1): bool
    {
        return $this->getAvailableCapacity() >= $quantity;
    }

    public function getFullLocation(): string
    {
        return sprintf('%s-%s-%s', $this->aisle, $this->rack, $this->level);
    }
}

