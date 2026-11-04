<?php

declare(strict_types=1);

namespace App\Models;

/**
 * TaxCode Model
 * Represents sales/purchase tax rates (e.g. SST, VAT, GST).
 */
class TaxCode extends BaseModel
{
    protected static string $table      = 'tax_codes';
    protected static string $primaryKey = 'id';

    /**
     * Get all active tax codes for tenant.
     */
    public function getActive(): array
    {
        return $this->all(['is_active' => 1], 'code ASC');
    }

    /**
     * Calculate tax amount for a given taxable base.
     */
    public function calculateTax(float $amount, int $taxCodeId): float
    {
        $code = $this->find($taxCodeId);
        if (!$code) {
            return 0.0;
        }

        return round($amount * ((float)$code['rate']), 4);
    }
}
