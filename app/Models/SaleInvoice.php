<?php

namespace App\Models;

use App\Services\Support\BusinessIdentifierGenerator;
use Illuminate\Database\Eloquent\Model;

class SaleInvoice extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            if (empty($invoice->invoice_uuid)) {
                $invoice->invoice_uuid = BusinessIdentifierGenerator::forSaleInvoice($invoice->invoice_id);
            }
        });
    }
}
