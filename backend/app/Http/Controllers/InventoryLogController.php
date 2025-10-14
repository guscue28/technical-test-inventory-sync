<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class InventoryLogController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Get inventory logs with filters
     * GET /api/inventory-logs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_id' => 'sometimes|integer',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date',
                'user_source' => 'sometimes|string',
                'per_page' => 'sometimes|integer|min:1|max:100'
            ]);

            $filters = array_filter([
                'product_id' => $validated['product_id'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'user_source' => $validated['user_source'] ?? null,
            ]);

            $perPage = $validated['per_page'] ?? 10;

            $logs = $this->inventoryService->getInventoryLogs($filters, $perPage);

            // Transform data for frontend consumption
            $transformedData = $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'product_id' => $log->product_id,
                    'product_name' => $log->product->name ?? 'Unknown',
                    'product_reference' => $log->product->reference ?? 'Unknown',
                    'previous_stock' => $log->previous_stock,
                    'new_stock' => $log->new_stock,
                    'change_amount' => $log->change_amount,
                    'formatted_change' => $log->formatted_change,
                    'user_source' => $log->user_source,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $log->created_at->diffForHumans()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedData,
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem()
                ],
                'filters_applied' => $filters
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch inventory logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get logs for specific product
     * GET /api/products/{id}/logs
     */
    public function getProductLogs(int $productId, Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 20);

            $logs = $this->inventoryService->inventoryLogRepository
                ->getLogsForProduct($productId, $limit);

            $transformedData = $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'previous_stock' => $log->previous_stock,
                    'new_stock' => $log->new_stock,
                    'change_amount' => $log->change_amount,
                    'formatted_change' => $log->formatted_change,
                    'user_source' => $log->user_source,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $log->created_at->diffForHumans()
                ];
            });

            return response()->json([
                'success' => true,
                'product_id' => $productId,
                'data' => $transformedData,
                'count' => $logs->count()
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory statistics
     * GET /api/inventory-logs/statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date'
            ]);

            $stats = $this->inventoryService->getInventoryStatistics(
                $validated['date_from'] ?? null,
                $validated['date_to'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => [
                    'from' => $validated['date_from'] ?? 'All time',
                    'to' => $validated['date_to'] ?? 'Present'
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export logs to CSV
     * GET /api/inventory-logs/export
     */
    public function exportLogs(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_id' => 'sometimes|integer',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date',
                'format' => 'sometimes|in:csv,json'
            ]);

            $filters = array_filter([
                'product_id' => $validated['product_id'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
            ]);

            $logs = $this->inventoryService->getInventoryLogs($filters, 1000);
            $format = $validated['format'] ?? 'csv';

            if ($format === 'csv') {
                $filename = 'inventory_logs_' . date('Y-m-d_H-i-s') . '.csv';

                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                ];

                $callback = function() use ($logs) {
                    $file = fopen('php://output', 'w');

                    // CSV Headers
                    fputcsv($file, [
                        'ID', 'Product ID', 'Product Name', 'Previous Stock',
                        'New Stock', 'Change Amount', 'User Source', 'Date'
                    ]);

                    // CSV Data
                    foreach ($logs as $log) {
                        fputcsv($file, [
                            $log->id,
                            $log->product_id,
                            $log->product->name ?? 'Unknown',
                            $log->previous_stock,
                            $log->new_stock,
                            $log->change_amount,
                            $log->user_source,
                            $log->created_at->format('Y-m-d H:i:s')
                        ]);
                    }

                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
            }

            // JSON format
            return response()->json([
                'success' => true,
                'data' => $logs,
                'exported_at' => now()->format('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
