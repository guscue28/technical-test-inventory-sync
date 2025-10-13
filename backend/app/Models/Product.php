<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'reference',
        'current_stock'
    ];

    protected $casts = [
        'current_stock' => 'integer',
    ];

    /**
     * Relationship with inventory logs
     */
    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class);
    }

    /**
     * Update stock with transaction logging
     */
    public function updateStock(int $newStock, string $userSource = 'system')
    {
        $previousStock = $this->current_stock;

        // Update current stock
        $this->current_stock = $newStock;
        $this->save();

        // Create inventory log
        $this->inventoryLogs()->create([
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'change_amount' => $newStock - $previousStock,
            'user_source' => $userSource
        ]);

        return $this;
    }

    /**
     * Get recent stock changes
     */
    public function getRecentChanges($limit = 10)
    {
        return $this->inventoryLogs()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
