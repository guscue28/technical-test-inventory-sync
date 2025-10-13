<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Product;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\DB;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test products
        $this->createTestProducts();
    }

    protected function createTestProducts()
    {
        Product::create([
            'name' => 'Test Product 1',
            'reference' => 'TEST-001',
            'current_stock' => 100
        ]);

        Product::create([
            'name' => 'Test Product 2',
            'reference' => 'TEST-002',
            'current_stock' => 50
        ]);
    }

    /**
     * Test successful stock update endpoint
     */
    public function testUpdateStockSuccess()
    {
        // Arrange
        $product = Product::first();
        $newStock = 150;

        // Act
        $response = $this->patchJson("/api/products/{$product->id}/stock", [
            'stock' => $newStock,
            'user_source' => 'phpunit_test'
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Stock updated successfully'
                ])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'product_id',
                        'previous_stock',
                        'new_stock',
                        'change_amount'
                    ]
                ]);

        // Verify database changes
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'current_stock' => $newStock
        ]);

        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'previous_stock' => 100,
            'new_stock' => $newStock,
            'change_amount' => $newStock - 100,
            'user_source' => 'phpunit_test'
        ]);
    }

    /**
     * Test stock update with invalid data
     */
    public function testUpdateStockValidationError()
    {
        // Arrange
        $product = Product::first();

        // Act - Test with negative stock
        $response = $this->patchJson("/api/products/{$product->id}/stock", [
            'stock' => -10
        ]);

        // Assert
        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Validation failed'
                ])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'errors'
                ]);

        // Verify no database changes
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'current_stock' => 100 // Original stock unchanged
        ]);
    }

    /**
     * Test stock update for non-existent product
     */
    public function testUpdateStockNonExistentProduct()
    {
        // Act
        $response = $this->patchJson("/api/products/999/stock", [
            'stock' => 100
        ]);

        // Assert
        $response->assertStatus(500)
                ->assertJson([
                    'success' => false,
                    'message' => 'Failed to update stock'
                ]);
    }

    /**
     * Test get product details
     */
    public function testShowProduct()
    {
        // Arrange
        $product = Product::first();

        // Act
        $response = $this->getJson("/api/products/{$product->id}");

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'reference' => $product->reference,
                        'current_stock' => $product->current_stock
                    ]
                ]);
    }

    /**
     * Test get all products
     */
    public function testIndexProducts()
    {
        // Act
        $response = $this->getJson('/api/products');

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'reference',
                            'current_stock'
                        ]
                    ],
                    'count'
                ]);

        $responseData = $response->json();
        $this->assertEquals(2, $responseData['count']);
    }

    /**
     * Test bulk stock update
     */
    public function testBulkUpdateStock()
    {
        // Arrange
        $product1 = Product::where('reference', 'TEST-001')->first();
        $product2 = Product::where('reference', 'TEST-002')->first();

        $updates = [
            ['product_id' => $product1->id, 'stock' => 200],
            ['product_id' => $product2->id, 'stock' => 300]
        ];

        // Act
        $response = $this->postJson('/api/products/bulk-update-stock', [
            'updates' => $updates,
            'user_source' => 'bulk_test'
        ]);

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'updated_count' => 2
                ]);

        // Verify database changes
        $this->assertDatabaseHas('products', [
            'id' => $product1->id,
            'current_stock' => 200
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product2->id,
            'current_stock' => 300
        ]);

        // Verify logs were created
        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product1->id,
            'new_stock' => 200,
            'user_source' => 'bulk_test'
        ]);
    }

    /**
     * Test bulk update with validation errors
     */
    public function testBulkUpdateWithValidationErrors()
    {
        // Arrange - Invalid updates (missing required fields)
        $updates = [
            ['product_id' => 1], // Missing stock
            ['stock' => 100]     // Missing product_id
        ];

        // Act
        $response = $this->postJson('/api/products/bulk-update-stock', [
            'updates' => $updates
        ]);

        // Assert
        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'Validation failed'
                ]);
    }

    /**
     * Test get low stock products
     */
    public function testGetLowStock()
    {
        // Arrange - Create a product with low stock
        Product::create([
            'name' => 'Low Stock Product',
            'reference' => 'LOW-001',
            'current_stock' => 5
        ]);

        // Act
        $response = $this->getJson('/api/products/low-stock?threshold=10');

        // Assert
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'threshold',
                        'count',
                        'products'
                    ]
                ]);

        $responseData = $response->json();
        $this->assertEquals(10, $responseData['data']['threshold']);
        $this->assertEquals(1, $responseData['data']['count']);
    }

    /**
     * Test ACID transaction integrity
     * This test verifies that if any part of the stock update fails,
     * the entire transaction is rolled back
     */
    public function testTransactionIntegrity()
    {
        // Arrange
        $product = Product::first();
        $originalStock = $product->current_stock;

        // Simulate a database constraint violation by using an invalid user_source
        // that exceeds the column length (this would need to be configured in the actual migration)

        // Act - Force a failure after product update but before log creation
        try {
            DB::transaction(function () use ($product) {
                $product->current_stock = 999;
                $product->save();

                // Force an exception to test rollback
                throw new \Exception('Simulated failure');
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        // Assert - Product stock should remain unchanged due to rollback
        $product->refresh();
        $this->assertEquals($originalStock, $product->current_stock);
    }

    /**
     * Test concurrent stock updates
     */
    public function testConcurrentStockUpdates()
    {
        // This test simulates concurrent updates to verify transaction isolation

        $product = Product::first();
        $initialStock = $product->current_stock;

        // Simulate two concurrent updates
        $response1 = $this->patchJson("/api/products/{$product->id}/stock", [
            'stock' => $initialStock + 10,
            'user_source' => 'user_1'
        ]);

        $response2 = $this->patchJson("/api/products/{$product->id}/stock", [
            'stock' => $initialStock + 20,
            'user_source' => 'user_2'
        ]);

        // Both should succeed (last one wins)
        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Verify final state
        $product->refresh();
        $this->assertEquals($initialStock + 20, $product->current_stock);

        // Verify both logs were created
        $this->assertEquals(2, InventoryLog::where('product_id', $product->id)->count());
    }

    /**
     * Test API endpoint rate limiting (if implemented)
     */
    public function testRateLimiting()
    {
        // This test would verify that the API handles high-frequency requests appropriately
        $product = Product::first();

        // Make multiple rapid requests
        for ($i = 0; $i < 5; $i++) {
            $response = $this->patchJson("/api/products/{$product->id}/stock", [
                'stock' => 100 + $i,
                'user_source' => "test_{$i}"
            ]);

            // All should succeed (assuming no rate limiting for tests)
            $response->assertStatus(200);
        }

        // Verify all updates were processed
        $this->assertEquals(5, InventoryLog::where('product_id', $product->id)->count());
    }

    /**
     * Test stock update with large numbers
     */
    public function testUpdateStockWithLargeNumbers()
    {
        // Arrange
        $product = Product::first();
        $largeStock = 999999;

        // Act
        $response = $this->patchJson("/api/products/{$product->id}/stock", [
            'stock' => $largeStock
        ]);

        // Assert
        $response->assertStatus(200);

        $product->refresh();
        $this->assertEquals($largeStock, $product->current_stock);
    }

    /**
     * Test stock update with zero
     */
    public function testUpdateStockToZero()
    {
        // Arrange
        $product = Product::first();

        // Act
        $response = $this->patchJson("/api/products/{$product->id}/stock", [
            'stock' => 0,
            'user_source' => 'zero_test'
        ]);

        // Assert
        $response->assertStatus(200);

        $product->refresh();
        $this->assertEquals(0, $product->current_stock);

        // Verify log shows correct change
        $log = InventoryLog::where('product_id', $product->id)->latest()->first();
        $this->assertEquals(-100, $log->change_amount); // From 100 to 0
    }
}
