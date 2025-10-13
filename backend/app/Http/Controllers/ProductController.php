<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class ProductController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Get all products
     * GET /api/products
     */
    public function index(): JsonResponse
    {
        try {
            $products = $this->inventoryService->getAllProducts();

            return response()->json([
                'success' => true,
                'data' => $products,
                'count' => count($products)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single product
     * GET /api/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        try {
            $product = $this->inventoryService->getProductById($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $product
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new product
     * POST /api/products
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'reference' => 'required|string|max:100|unique:products,reference',
                'current_stock' => 'required|integer|min:0'
            ]);

            $product = $this->inventoryService->createProduct($validated);

            return response()->json([
                'success' => true,
                'data' => $product,
                'message' => 'Product created successfully'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product (including stock)
     * PATCH/PUT /api/products/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'reference' => 'sometimes|string|max:100|unique:products,reference,' . $id,
                'current_stock' => 'sometimes|integer|min:0'
            ]);

            $userSource = $request->input('user_source', 'api');

            // If updating stock, use the inventory service for proper logging
            if (isset($validated['current_stock'])) {
                $result = $this->inventoryService->updateProductStock(
                    $id,
                    $validated['current_stock'],
                    $userSource
                );

                if (!$result['success']) {
                    return response()->json($result, 404);
                }

                return response()->json($result);
            }

            // For other fields, update directly
            $product = $this->inventoryService->updateProduct($id, $validated);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $product,
                'message' => 'Product updated successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete product
     * DELETE /api/products/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->inventoryService->deleteProduct($id);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product stock - Legacy endpoint for backward compatibility
     * PATCH /api/products/{id}/stock
     */
    public function updateStock(Request $request, int $id): JsonResponse
    {
        try {
            // Validation
            $validated = $request->validate([
                'stock' => 'required|integer|min:0'
            ]);

            $userSource = $request->input('user_source', 'api');

            // Update stock with ACID transaction
            $result = $this->inventoryService->updateProductStock(
                $id,
                $validated['stock'],
                $userSource
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock updated successfully',
                'data' => [
                    'product_id' => $id,
                    'previous_stock' => $result['log']->previous_stock,
                    'new_stock' => $result['product']->current_stock,
                    'change_amount' => $result['change_amount']
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk stock update
     * POST /api/products/bulk-update-stock
     */
    public function bulkUpdateStock(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'updates' => 'required|array',
                'updates.*.product_id' => 'required|integer',
                'updates.*.stock' => 'required|integer|min:0',
                'user_source' => 'sometimes|string'
            ]);

            $result = $this->inventoryService->bulkStockUpdate(
                $validated['updates'],
                $validated['user_source'] ?? 'bulk_api'
            );

            if (!$result['success']) {
                return response()->json($result, 422);
            }

            return response()->json($result, 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Bulk update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get low stock alert
     * GET /api/products/low-stock
     */
    public function getLowStock(Request $request): JsonResponse
    {
        try {
            $threshold = $request->input('threshold', 10);
            $alert = $this->inventoryService->getLowStockAlert($threshold);

            return response()->json([
                'success' => true,
                'data' => $alert
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get low stock alert',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
