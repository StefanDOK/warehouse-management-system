<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PickListItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PickListItemRepository::class)]
#[ORM\Table(name: 'pick_list_items')]
class PickListItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PickList::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PickList $pickList = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Shelf::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Shelf $shelf;

    #[ORM\Column(type: 'string', length: 100)]
    private string $shelfLocation;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(type: 'integer')]
    private int $pickedQuantity = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $scannedBarcode = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $pickedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPickList(): ?PickList
    {
        return $this->pickList;
    }

    public function setPickList(?PickList $pickList): self
    {
        $this->pickList = $pickList;
        return $this;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): self
    {
        $this->product = $product;
        return $this;
    }

    public function getShelf(): Shelf
    {
        return $this->shelf;
    }

    public function setShelf(Shelf $shelf): self
    {
        $this->shelf = $shelf;
        $this->shelfLocation = $shelf->getFullLocation();
        return $this;
    }

    public function getShelfLocation(): string
    {
        return $this->shelfLocation;
    }

    public function setShelfLocation(string $shelfLocation): self
    {
        $this->shelfLocation = $shelfLocation;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPickedQuantity(): int
    {
        return $this->pickedQuantity;
    }

    public function setPickedQuantity(int $pickedQuantity): self
    {
        $this->pickedQuantity = $pickedQuantity;
        return $this;
    }

    public function getScannedBarcode(): ?string
    {
        return $this->scannedBarcode;
    }

    public function setScannedBarcode(?string $scannedBarcode): self
    {
        $this->scannedBarcode = $scannedBarcode;
        return $this;
    }

    public function getPickedAt(): ?\DateTimeImmutable
    {
        return $this->pickedAt;
    }

    public function setPickedAt(?\DateTimeImmutable $pickedAt): self
    {
        $this->pickedAt = $pickedAt;
        return $this;
    }

    public function pick(int $quantity, string $barcode): self
    {
        $this->pickedQuantity = $quantity;
        $this->scannedBarcode = $barcode;
        $this->pickedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isPicked(): bool
    {
        return $this->pickedQuantity >= $this->quantity;
    }

    public function getRemainingQuantity(): int
    {
        return max(0, $this->quantity - $this->pickedQuantity);
    }
}

