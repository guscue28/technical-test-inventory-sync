<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'previous_stock',
        'new_stock',
        'change_amount',
        'user_source'
    ];

    protected $casts = [
        'product_id' => 'integer',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
        'change_amount' => 'integer',
    ];

    /**
     * Relationship with product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate = null, $endDate = null)
    {
        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return $query;
    }

    /**
     * Scope to filter by product ID
     */
    public function scopeForProduct($query, $productId = null)
    {
        if ($productId) {
            $query->where('product_id', $productId);
        }

        return $query;
    }

    /**
     * Get formatted change amount with sign
     */
    public function getFormattedChangeAttribute()
    {
        return $this->change_amount >= 0 ? '+' . $this->change_amount : (string) $this->change_amount;
    }
}
