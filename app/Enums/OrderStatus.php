<?php

namespace App\Enums;

use Vanilo\Order\Models\OrderStatus as BaseOrderStatus;

class OrderStatus extends BaseOrderStatus
{
    public const SHIPPED = 'shipped';

    protected static function boot()
    {
        parent::boot();

        static::$labels[self::SHIPPED] = __('Shipped');
    }
}
