<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneybirdSetting extends Model
{
    protected $fillable = [
        'configuration',
    ];

    protected $hidden = [
        'configuration',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'encrypted:array',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? new static;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolved(): array
    {
        return array_replace([
            'connected' => false,
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
            'administration_id' => null,
            'workflow_id' => null,
            'document_style_id' => null,
            'ledger_account_id' => null,
            'auto_send_invoice_email' => false,
            'mollie_invoice_status' => null,
            'invoice_payment_status' => null,
        ], (array) static::query()->first()?->configuration);
    }
}
