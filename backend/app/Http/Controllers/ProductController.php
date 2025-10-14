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
     * Get all products with pagination and filters
     * GET /api/products
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validation for query parameters
            $validated = $request->validate([
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'search' => 'sometimes|string|max:255',
                'name' => 'sometimes|string|max:255',
                'reference' => 'sometimes|string|max:100',
                'min_stock' => 'sometimes|integer|min:0',
                'max_stock' => 'sometimes|integer|min:0',
            ]);

            // Build filters array
            $filters = [];
            if (isset($validated['search'])) {
                $filters['search'] = $validated['search'];
            }
            if (isset($validated['name'])) {
                $filters['name'] = $validated['name'];
            }
            if (isset($validated['reference'])) {
                $filters['reference'] = $validated['reference'];
            }
            if (isset($validated['min_stock'])) {
                $filters['min_stock'] = $validated['min_stock'];
            }
            if (isset($validated['max_stock'])) {
                $filters['max_stock'] = $validated['max_stock'];
            }

            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 50;

            $result = $this->inventoryService->getProducts($filters, $page, $perPage);

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'pagination' => $result['pagination']
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
                'reference' => 'nullable|string|max:100|unique:products,reference',
                'current_stock' => 'required|integer|min:0'
            ]);

            // Set default reference if empty
            if (empty($validated['reference'])) {
                $validated['reference'] = 'REF-' . time();
            }

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
                'reference' => 'sometimes|nullable|string|max:100|unique:products,reference,' . $id,
                'current_stock' => 'sometimes|integer|min:0'
            ]);

            \Log::info('Update product request', [
                'product_id' => $id,
                'validated' => $validated,
                'request_data' => $request->all()
            ]);

            $userSource = $request->input('user_source', 'api');

            $product = null;

            // First update basic fields (name, reference)
            $basicFields = array_diff_key($validated, ['current_stock' => '']);
            \Log::info('Basic fields to update', ['basicFields' => $basicFields]);

            if (!empty($basicFields)) {
                try {
                    $product = $this->inventoryService->updateProduct($id, $basicFields);
                    \Log::info('Product updated with basic fields', ['product' => $product]);
                    if (!$product) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Product not found'
                        ], 404);
                    }
                } catch (\Exception $e) {
                    \Log::error('Error updating basic fields', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }

            // Then update stock if provided
            if (isset($validated['current_stock'])) {
                $result = $this->inventoryService->updateProductStock(
                    $id,
                    $validated['current_stock'],
                    $userSource
                );

                if (!$result['success']) {
                    return response()->json($result, 404);
                }

                // Format the response to match our API structure
                $responseData = [
                    'success' => true,
                    'data' => $result['product'],
                    'message' => 'Product updated successfully'
                ];

                return response()->json($responseData);
            }

            // If only basic fields were updated
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
