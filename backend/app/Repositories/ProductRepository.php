<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductRepository
{
    protected $model;

    public function __construct(Product $model)
    {
        $this->model = $model;
    }

    /**
     * Find product by ID
     */
    public function findById(int $id): Product
    {
        $product = $this->model->find($id);

        if (!$product) {
            throw new ModelNotFoundException("Product with ID {$id} not found");
        }

        return $product;
    }

    /**
     * Find product by reference
     */
    public function findByReference(string $reference): ?Product
    {
        return $this->model->where('reference', $reference)->first();
    }

    /**
     * Get all products
     */
    public function getAll(): Collection
    {
        return $this->model->all();
    }

    /**
     * Create a new product
     */
    public function create(array $data): Product
    {
        return $this->model->create($data);
    }

    /**
     * Update product
     */
    public function update(int $id, array $data): Product
    {
        $product = $this->findById($id);
        $product->update($data);
        return $product;
    }

    /**
     * Delete product
     */
    public function delete(int $id): bool
    {
        $product = $this->findById($id);
        return $product->delete();
    }

    /**
     * Get products with low stock
     */
    public function getLowStockProducts(int $threshold = 10): Collection
    {
        return $this->model->where('current_stock', '<=', $threshold)->get();
    }

    /**
     * Get filtered products with pagination
     */
    public function getFiltered(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['search']) && $filters['search']) {
            $searchTerm = $filters['search'];
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('reference', 'LIKE', "%{$searchTerm}%");
            });
        }

        if (isset($filters['name']) && $filters['name']) {
            $query->where('name', 'LIKE', "%{$filters['name']}%");
        }

        if (isset($filters['reference']) && $filters['reference']) {
            $query->where('reference', 'LIKE', "%{$filters['reference']}%");
        }

        if (isset($filters['min_stock'])) {
            $query->where('current_stock', '>=', $filters['min_stock']);
        }

        if (isset($filters['max_stock'])) {
            $query->where('current_stock', '<=', $filters['max_stock']);
        }

        // Get paginated results
        $total = $query->count();
        $products = $query->orderBy('id', 'desc')
                         ->skip(($page - 1) * $perPage)
                         ->take($perPage)
                         ->get();

        // Build pagination info
        $lastPage = ceil($total / $perPage);
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = min($from + $perPage - 1, $total);

        return [
            'data' => $products,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
                'has_more_pages' => $page < $lastPage
            ]
        ];
    }

    /**
     * Search products by name or reference
     */
    public function search(string $query): Collection
    {
        return $this->model->where('name', 'LIKE', "%{$query}%")
                          ->orWhere('reference', 'LIKE', "%{$query}%")
                          ->get();
    }
}
