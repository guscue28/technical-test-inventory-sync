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
     * Search products by name or reference
     */
    public function search(string $query): Collection
    {
        return $this->model->where('name', 'LIKE', "%{$query}%")
                          ->orWhere('reference', 'LIKE', "%{$query}%")
                          ->get();
    }
}
