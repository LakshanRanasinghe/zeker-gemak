<?php

declare(strict_types=1);
use App\Models\MasterProduct;
use App\Models\Material;
use App\Models\Post;
use App\Models\Product;

return [
    /*
     * Explorer uses the Elasticsearch PHP SDK under the hood. All host configuration
     * options of the SDK are applicable here. See:
     * https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/configuration.html
     */
    'connection' => array_filter([
        'host' => env('ELASTIC_HOST', 'localhost'),
        'port' => env('ELASTIC_PORT', '9200'),
        'scheme' => env('ELASTIC_SCHEME', 'http'),
        'auth' => env('ELASTIC_USERNAME') ? [
            'username' => env('ELASTIC_USERNAME'),
            'password' => env('ELASTIC_PASSWORD', ''),
        ] : null,
    ]),

    /*
     * Default index settings applied when creating a new index. Can be overridden
     * per-index by implementing the IndexSettings interface on the model.
     */
    'default_index_settings' => [],

    /*
     * Models listed here are registered as Explorer-managed indexes.
     * Each model should implement the Explored interface and define mappableAs().
     */
    'indexes' => [
        'bbnl_products' => Product::class,
        'bbnl_master_products' => MasterProduct::class,
        'bbnl_materials' => Material::class,
        'bbnl_printers' => Post::class,
        // GroupProduct uses the same Scout index as Product (catalog_products_simple).
        // It is indexed there automatically via Scout; no separate Explorer entry needed.
    ],

    /*
     * Whether to keep old indices after an alias is pointed to a new index.
     * Only applies to models implementing the Aliased interface.
     */
    'prune_old_aliases' => true,

    /*
     * Set to true to send Elasticsearch SDK logs to a PSR-3 logger.
     * Disabled by default for performance.
     */
    'logging' => env('EXPLORER_ELASTIC_LOGGER_ENABLED', false),
    'logger' => null,

    /*
     * When true, refreshes the index after every bulk operation.
     * Useful for testing; keep false in production.
     */
    'bulk_refresh' => env('EXPLORER_ELASTIC_BULK_REFRESH', false),
];
