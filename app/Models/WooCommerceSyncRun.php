<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WooCommerceSyncRun extends Model
{
    protected $table = 'woocommerce_sync_runs';

    protected $fillable = [
        'mode',
        'domain',
        'status',
        'current_page',
        'total_pages',
        'processed',
        'created',
        'updated',
        'disabled',
        'failed',
        'media_pending',
        'media_processed',
        'media_failed',
        'options',
        'error',
        'requested_since',
        'started_at',
        'heartbeat_at',
        'completed_at',
        'failed_at',
        'reindex_queued_at',
    ];

    protected $attributes = [
        'status' => 'pending',
        'current_page' => 1,
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'disabled' => 0,
        'failed' => 0,
        'media_pending' => 0,
        'media_processed' => 0,
        'media_failed' => 0,
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'requested_since' => 'datetime',
            'started_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'reindex_queued_at' => 'datetime',
        ];
    }
}
