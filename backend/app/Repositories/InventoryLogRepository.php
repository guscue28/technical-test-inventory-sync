<?php

namespace App\Repositories;

use App\Models\InventoryLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class InventoryLogRepository
{
    protected $model;

    public function __construct(InventoryLog $model)
    {
        $this->model = $model;
    }

    /**
     * Get filtered inventory logs with pagination
     */
    public function getFilteredLogs(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = $this->model->with('product')
                            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['product_id'])) {
            $query->forProduct($filters['product_id']);
        }

        if (isset($filters['date_from']) || isset($filters['date_to'])) {
            $query->dateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        }

        if (isset($filters['user_source'])) {
            $query->where('user_source', 'LIKE', "%{$filters['user_source']}%");
        }

        return $query->paginate($perPage);
    }

    /**
     * Create inventory log
     */
    public function create(array $data): InventoryLog
    {
        return $this->model->create($data);
    }

    /**
     * Get logs for specific product
     */
    public function getLogsForProduct(int $productId, int $limit = 20): Collection
    {
        return $this->model->where('product_id', $productId)
                          ->with('product')
                          ->orderBy('created_at', 'desc')
                          ->limit($limit)
                          ->get();
    }

    /**
     * Get recent logs across all products
     */
    public function getRecentLogs(int $limit = 50): Collection
    {
        return $this->model->with('product')
                          ->orderBy('created_at', 'desc')
                          ->limit($limit)
                          ->get();
    }

    /**
     * Delete all logs for a specific product
     */
    public function deleteByProductId(int $productId): bool
    {
        return $this->model->where('product_id', $productId)->delete();
    }

    /**
     * Get statistics for date range
     */
    public function getStatistics(string $startDate = null, string $endDate = null): array
    {
        $query = $this->model->query();

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $totalLogs = $query->count();
        $totalStockIncreases = $query->where('change_amount', '>', 0)->sum('change_amount');
        $totalStockDecreases = abs($query->where('change_amount', '<', 0)->sum('change_amount'));

        return [
            'total_logs' => $totalLogs,
            'total_stock_increases' => $totalStockIncreases,
            'total_stock_decreases' => $totalStockDecreases,
            'net_change' => $totalStockIncreases - $totalStockDecreases
        ];
    }
}
