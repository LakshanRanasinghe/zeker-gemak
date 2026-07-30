<?php

namespace App\Exceptions;

use RuntimeException;

class ShippingDocumentException extends RuntimeException
{
    public static function failed(\Throwable $previous): self
    {
        return new self(
            'Shipping documents could not be generated. Check the DHL/Dropbox settings and order address; the order was not changed.',
            0,
            $previous,
        );
    }
}
