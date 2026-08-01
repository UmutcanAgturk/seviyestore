<?php

declare(strict_types=1);

namespace Seviye\Depo\Domain;

final class Supplier
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $contactName,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $taxNumber,
        public readonly ?string $address,
        public readonly SupplierStatus $status
    ) {
    }
}
