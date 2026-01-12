<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\LowStockAlertRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LowStockAlertRepository::class)]
#[ORM\Table(name: 'low_stock_alerts')]
class LowStockAlert
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(type: 'integer')]
    private int $currentStock;

    #[ORM\Column(type: 'integer')]
    private int $minStockLevel;

    #[ORM\Column(type: 'string', length: 20)]
    private string $severity;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

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

    public function getCurrentStock(): int
    {
        return $this->currentStock;
    }

    public function setCurrentStock(int $currentStock): self
    {
        $this->currentStock = $currentStock;
        return $this;
    }

    public function getMinStockLevel(): int
    {
        return $this->minStockLevel;
    }

    public function setMinStockLevel(int $minStockLevel): self
    {
        $this->minStockLevel = $minStockLevel;
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;
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

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
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

    public function acknowledge(): self
    {
        $this->status = self::STATUS_ACKNOWLEDGED;
        $this->acknowledgedAt = new \DateTimeImmutable();
        return $this;
    }

    public function resolve(): self
    {
        $this->status = self::STATUS_RESOLVED;
        $this->resolvedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getDeficit(): int
    {
        return max(0, $this->minStockLevel - $this->currentStock);
    }

    public static function calculateSeverity(int $currentStock, int $minStock): string
    {
        if ($minStock <= 0) {
            return self::SEVERITY_LOW;
        }

        $ratio = $currentStock / $minStock;

        if ($ratio <= 0) {
            return self::SEVERITY_CRITICAL;
        }
        if ($ratio <= 0.25) {
            return self::SEVERITY_HIGH;
        }
        if ($ratio <= 0.5) {
            return self::SEVERITY_MEDIUM;
        }

        return self::SEVERITY_LOW;
    }
}

