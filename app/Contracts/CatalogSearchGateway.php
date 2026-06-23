<?php

namespace App\Contracts;

interface CatalogSearchGateway
{
    public function search(array $payload): array;
}
