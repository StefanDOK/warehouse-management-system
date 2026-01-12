<?php

declare(strict_types=1);

namespace App\DTO;

class ErpOrderDTO
{
    public function __construct(
        public readonly string $externalOrderId,
        public readonly string $customerName,
        public readonly ?string $shippingAddress = null,
        public readonly int $priority = 0,
        public readonly array $items = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            externalOrderId: $data['externalOrderId'] ?? $data['order_id'] ?? '',
            customerName: $data['customerName'] ?? $data['customer_name'] ?? '',
            shippingAddress: $data['shippingAddress'] ?? $data['shipping_address'] ?? null,
            priority: (int)($data['priority'] ?? 0),
            items: $data['items'] ?? [],
        );
    }
}

