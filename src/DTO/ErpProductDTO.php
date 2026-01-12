<?php

declare(strict_types=1);

namespace App\DTO;

class ErpProductDTO
{
    public function __construct(
        public readonly string $sku,
        public readonly string $barcode,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly string $price = '0.00',
        public readonly int $minStockLevel = 0,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sku: $data['sku'] ?? '',
            barcode: $data['barcode'] ?? '',
            name: $data['name'] ?? '',
            description: $data['description'] ?? null,
            price: (string)($data['price'] ?? '0.00'),
            minStockLevel: (int)($data['minStockLevel'] ?? $data['min_stock_level'] ?? 0),
        );
    }
}

