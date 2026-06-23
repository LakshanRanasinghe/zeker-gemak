<?php

namespace App\Services;

use App\Contracts\CatalogSearchGateway;
use JeroenG\Explorer\Infrastructure\Elastic\ElasticClientFactory;

class ElasticCatalogSearchGateway implements CatalogSearchGateway
{
    public function __construct(
        protected ElasticClientFactory $clientFactory,
    ) {}

    public function search(array $payload): array
    {
        return $this->clientFactory
            ->client()
            ->search($payload)
            ->asArray();
    }
}
