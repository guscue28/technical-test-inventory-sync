<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Repositories\InventoryLogRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;

class InventoryService
{
    protected $productRepository;
    protected $inventoryLogRepository;

    public function __construct(
        ProductRepository $productRepository,
        InventoryLogRepository $inventoryLogRepository
    ) {
        $this->productRepository = $productRepository;
        $this->inventoryLogRepository = $inventoryLogRepository;
    }

    /**
     * Get all products
     */
    public function getAllProducts()
    {
        return $this->productRepository->getAll();
    }

    /**
     * Update product stock with ACID transaction
     */
    public function updateProductStock(int $productId, int $newStock, string $userSource = 'system'): array
    {
        // Validation
        if ($newStock < 0) {
            throw ValidationException::withMessages([
                'stock' => 'Stock cannot be negative'
            ]);
        }

        DB::beginTransaction();

        try {
            // Find product
            $product = $this->productRepository->findById($productId);
            $previousStock = $product->current_stock;

            // Update product stock
            $product->current_stock = $newStock;
            $product->save();

            // Create inventory log
            $log = $this->inventoryLogRepository->create([
                'product_id' => $productId,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'change_amount' => $newStock - $previousStock,
                'user_source' => $userSource
            ]);

            DB::commit();

            return [
                'success' => true,
                'product' => $product->fresh(),
                'log' => $log,
                'change_amount' => $newStock - $previousStock
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update stock: ' . $e->getMessage());
        }
    }

    /**
     * Get filtered inventory logs
     */
    public function getInventoryLogs(array $filters = [], int $perPage = 50)
    {
        return $this->inventoryLogRepository->getFilteredLogs($filters, $perPage);
    }

    /**
     * Get inventory statistics
     */
    public function getInventoryStatistics(string $startDate = null, string $endDate = null): array
    {
        return $this->inventoryLogRepository->getStatistics($startDate, $endDate);
    }

    /**
     * Bulk stock update with validation
     */
    public function bulkStockUpdate(array $updates, string $userSource = 'bulk_import'): array
    {
        $results = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($updates as $update) {
                if (!isset($update['product_id']) || !isset($update['stock'])) {
                    $errors[] = "Invalid update data: missing product_id or stock";
                    continue;
                }

                try {
                    $result = $this->updateProductStock(
                        $update['product_id'],
                        $update['stock'],
                        $userSource
                    );
                    $results[] = $result;
                } catch (Exception $e) {
                    $errors[] = "Product {$update['product_id']}: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }

            DB::commit();

            return [
                'success' => true,
                'updated_count' => count($results),
                'results' => $results
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Bulk update failed: ' . $e->getMessage());
        }
    }

    /**
     * Get product by ID
     */
    public function getProductById(int $productId)
    {
        return $this->productRepository->findById($productId);
    }

    /**
     * Create new product
     */
    public function createProduct(array $data)
    {
        DB::beginTransaction();

        try {
            $product = $this->productRepository->create($data);

            // Create initial inventory log if stock is provided
            if (isset($data['current_stock']) && $data['current_stock'] > 0) {
                $this->inventoryLogRepository->create([
                    'product_id' => $product->id,
                    'previous_stock' => 0,
                    'new_stock' => $data['current_stock'],
                    'change_amount' => $data['current_stock'],
                    'user_source' => 'creation'
                ]);
            }

            DB::commit();
            return $product;

        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create product: ' . $e->getMessage());
        }
    }

    /**
     * Update product (excluding stock updates)
     */
    public function updateProduct(int $productId, array $data)
    {
        // Remove stock from update data as it should use updateProductStock
        unset($data['current_stock']);

        $product = $this->productRepository->findById($productId);

        if (!$product) {
            return null;
        }

        return $this->productRepository->update($productId, $data);
    }

    /**
     * Delete product
     */
    public function deleteProduct(int $productId): bool
    {
        DB::beginTransaction();

        try {
            $product = $this->productRepository->findById($productId);

            if (!$product) {
                return false;
            }

            // Delete related inventory logs first
            $this->inventoryLogRepository->deleteByProductId($productId);

            // Delete the product
            $result = $this->productRepository->delete($productId);

            DB::commit();
            return $result;

        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to delete product: ' . $e->getMessage());
        }
    }

    /**
     * Get low stock alert
     */
    public function getLowStockAlert(int $threshold = 10): array
    {
        $lowStockProducts = $this->productRepository->getLowStockProducts($threshold);

        return [
            'threshold' => $threshold,
            'count' => $lowStockProducts->count(),
            'products' => $lowStockProducts
        ];
    }
}
