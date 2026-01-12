<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductReturnRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductReturnRepository::class)]
#[ORM\Table(name: 'product_returns')]
class ProductReturn
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_INSPECTING = 'inspecting';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    public const REASON_CUSTOMER_RETURN = 'customer_return';
    public const REASON_DAMAGED = 'damaged';
    public const REASON_WRONG_ITEM = 'wrong_item';
    public const REASON_PICKING_ERROR = 'picking_error';
    public const REASON_OTHER = 'other';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $returnNumber;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Order $originalOrder = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $reason = self::REASON_OTHER;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\OneToMany(mappedBy: 'productReturn', targetEntity: ProductReturnItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->returnNumber = 'RET-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReturnNumber(): string
    {
        return $this->returnNumber;
    }

    public function setReturnNumber(string $returnNumber): self
    {
        $this->returnNumber = $returnNumber;
        return $this;
    }

    public function getOriginalOrder(): ?Order
    {
        return $this->originalOrder;
    }

    public function setOriginalOrder(?Order $originalOrder): self
    {
        $this->originalOrder = $originalOrder;
        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
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

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ProductReturnItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setProductReturn($this);
        }
        return $this;
    }

    public function removeItem(ProductReturnItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getProductReturn() === $this) {
                $item->setProductReturn(null);
            }
        }
        return $this;
    }

    public function startInspection(): self
    {
        $this->status = self::STATUS_INSPECTING;
        return $this;
    }

    public function complete(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
        return $this;
    }

    public function reject(): self
    {
        $this->status = self::STATUS_REJECTED;
        $this->completedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getTotalItems(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getQuantity();
        }
        return $total;
    }
}

