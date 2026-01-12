<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PickListRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PickListRepository::class)]
#[ORM\Table(name: 'pick_lists')]
class PickList
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $pickListNumber;

    #[ORM\OneToOne(inversedBy: 'pickList', targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\OneToMany(mappedBy: 'pickList', targetEntity: PickListItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['shelfLocation' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->pickListNumber = 'PL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPickListNumber(): string
    {
        return $this->pickListNumber;
    }

    public function setPickListNumber(string $pickListNumber): self
    {
        $this->pickListNumber = $pickListNumber;
        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
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

    public function addItem(PickListItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setPickList($this);
        }
        return $this;
    }

    public function removeItem(PickListItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getPickList() === $this) {
                $item->setPickList(null);
            }
        }
        return $this;
    }

    public function start(): self
    {
        $this->status = self::STATUS_IN_PROGRESS;
        $this->startedAt = new \DateTimeImmutable();
        return $this;
    }

    public function complete(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
        return $this;
    }

    public function cancel(): self
    {
        $this->status = self::STATUS_CANCELLED;
        return $this;
    }

    public function isComplete(): bool
    {
        foreach ($this->items as $item) {
            if (!$item->isPicked()) {
                return false;
            }
        }
        return true;
    }

    public function getProgress(): float
    {
        if ($this->items->isEmpty()) {
            return 0.0;
        }

        $picked = 0;
        foreach ($this->items as $item) {
            if ($item->isPicked()) {
                $picked++;
            }
        }

        return ($picked / $this->items->count()) * 100;
    }
}

