<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StockMovementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockMovementRepository::class)]
#[ORM\Table(name: 'stock_movements')]
class StockMovement
{
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_PICK = 'pick';
    public const TYPE_RETURN = 'return';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_TRANSFER = 'transfer';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Shelf::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Shelf $fromShelf = null;

    #[ORM\ManyToOne(targetEntity: Shelf::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Shelf $toShelf = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $type;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'integer')]
    private int $previousQuantity = 0;

    #[ORM\Column(type: 'integer')]
    private int $newQuantity = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFromShelf(): ?Shelf
    {
        return $this->fromShelf;
    }

    public function setFromShelf(?Shelf $fromShelf): self
    {
        $this->fromShelf = $fromShelf;
        return $this;
    }

    public function getToShelf(): ?Shelf
    {
        return $this->toShelf;
    }

    public function setToShelf(?Shelf $toShelf): self
    {
        $this->toShelf = $toShelf;
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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPreviousQuantity(): int
    {
        return $this->previousQuantity;
    }

    public function setPreviousQuantity(int $previousQuantity): self
    {
        $this->previousQuantity = $previousQuantity;
        return $this;
    }

    public function getNewQuantity(): int
    {
        return $this->newQuantity;
    }

    public function setNewQuantity(int $newQuantity): self
    {
        $this->newQuantity = $newQuantity;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public static function createReceipt(Product $product, Shelf $shelf, int $quantity, string $reference): self
    {
        $movement = new self();
        $movement->setProduct($product);
        $movement->setToShelf($shelf);
        $movement->setType(self::TYPE_RECEIPT);
        $movement->setQuantity($quantity);
        $movement->setReference($reference);
        return $movement;
    }

    public static function createPick(Product $product, Shelf $shelf, int $quantity, string $reference): self
    {
        $movement = new self();
        $movement->setProduct($product);
        $movement->setFromShelf($shelf);
        $movement->setType(self::TYPE_PICK);
        $movement->setQuantity($quantity);
        $movement->setReference($reference);
        return $movement;
    }

    public static function createReturn(Product $product, Shelf $shelf, int $quantity, string $reference): self
    {
        $movement = new self();
        $movement->setProduct($product);
        $movement->setToShelf($shelf);
        $movement->setType(self::TYPE_RETURN);
        $movement->setQuantity($quantity);
        $movement->setReference($reference);
        return $movement;
    }
}

