<?php
namespace ExcelleInsights\QuickBooks\Entities;

final class Customer
{
    public function __construct(
        public string $displayName,
        public ?string $email = null,
        public ?string $phone = null,
        public bool $active = true,
        public ?string $qboId = null,
        public ?string $syncToken = null
    ) {}
}
