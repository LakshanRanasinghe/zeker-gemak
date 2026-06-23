<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Export extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'total_rows',
        'filters',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'filters' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('export')
            ->singleFile()
            ->useDisk('local');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function downloadUrl(): ?string
    {
        if (! $this->isCompleted()) {
            return null;
        }

        return route('exports.download', $this);
    }
}
