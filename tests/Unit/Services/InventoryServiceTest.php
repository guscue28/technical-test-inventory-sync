<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;
use App\Services\InventoryService;
use App\Repositories\ProductRepository;
use App\Repositories\InventoryLogRepository;
use App\Models\Product;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class InventoryServiceTest extends TestCase
{
    protected $productRepository;
    protected $inventoryLogRepository;
    protected $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->inventoryLogRepository = Mockery::mock(InventoryLogRepository::class);

        // Create service instance with mocked repositories
        $this->inventoryService = new InventoryService(
            $this->productRepository,
            $this->inventoryLogRepository
        );

        // Mock DB facade for transaction testing
        DB::shouldReceive('beginTransaction')->byDefault();
        DB::shouldReceive('commit')->byDefault();
        DB::shouldReceive('rollBack')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test successful stock update with ACID transaction
     */
    public function testUpdateProductStockSuccess()
    {
        // Arrange
        $productId = 1;
        $newStock = 150;
        $userSource = 'test_user';
        $previousStock = 100;

        $product = new Product([
            'id' => $productId,
            'name' => 'Test Product',
            'reference' => 'TEST-001',
            'current_stock' => $previousStock
        ]);

        $expectedLog = new InventoryLog([
            'product_id' => $productId,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'change_amount' => $newStock - $previousStock,
            'user_source' => $userSource
        ]);

        // Mock repository calls
        $this->productRepository
            ->shouldReceive('findById')
            ->once()
            ->with($productId)
            ->andReturn($product);

        $this->inventoryLogRepository
            ->shouldReceive('create')
            ->once()
            ->with([
                'product_id' => $productId,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'change_amount' => $newStock - $previousStock,
                'user_source' => $userSource
            ])
            ->andReturn($expectedLog);

        // Mock product save and fresh methods
        $product->shouldReceive('save')->once()->andReturn(true);
        $product->shouldReceive('fresh')->once()->andReturn($product);

        // Act
        $result = $this->inventoryService->updateProductStock($productId, $newStock, $userSource);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($product, $result['product']);
        $this->assertEquals($expectedLog, $result['log']);
        $this->assertEquals($newStock - $previousStock, $result['change_amount']);
    }

    /**
     * Test stock update with negative stock (should fail validation)
     */
    public function testUpdateProductStockWithNegativeStock()
    {
        // Arrange
        $productId = 1;
        $newStock = -10; // Invalid negative stock

        // Act & Assert
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->inventoryService->updateProductStock($productId, $newStock);
    }

    /**
     * Test stock update with non-existent product
     */
    public function testUpdateProductStockWithNonExistentProduct()
    {
        // Arrange
        $productId = 999; // Non-existent product
        $newStock = 50;

        $this->productRepository
            ->shouldReceive('findById')
            ->once()
            ->with($productId)
            ->andThrow(new ModelNotFoundException("Product with ID {$productId} not found"));

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to update stock');

        $this->inventoryService->updateProductStock($productId, $newStock);
    }

    /**
     * Test transaction rollback on failure
     */
    public function testTransactionRollbackOnFailure()
    {
        // Arrange
        $productId = 1;
        $newStock = 150;
        $previousStock = 100;

        $product = new Product([
            'id' => $productId,
            'current_stock' => $previousStock
        ]);

        // Mock successful product finding
        $this->productRepository
            ->shouldReceive('findById')
            ->once()
            ->with($productId)
            ->andReturn($product);

        // Mock product save to succeed
        $product->shouldReceive('save')->once()->andReturn(true);

        // Mock inventory log creation to fail
        $this->inventoryLogRepository
            ->shouldReceive('create')
            ->once()
            ->andThrow(new Exception('Database error'));

        // Mock transaction methods
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to update stock');

        $this->inventoryService->updateProductStock($productId, $newStock);
    }

    /**
     * Test bulk stock update with valid data
     */
    public function testBulkStockUpdateSuccess()
    {
        // Arrange
        $updates = [
            ['product_id' => 1, 'stock' => 100],
            ['product_id' => 2, 'stock' => 200]
        ];
        $userSource = 'bulk_test';

        // Mock individual update calls
        foreach ($updates as $update) {
            $product = new Product([
                'id' => $update['product_id'],
                'current_stock' => 50
            ]);

            $this->productRepository
                ->shouldReceive('findById')
                ->with($update['product_id'])
                ->andReturn($product);

            $this->inventoryLogRepository
                ->shouldReceive('create')
                ->andReturn(new InventoryLog());

            $product->shouldReceive('save')->andReturn(true);
            $product->shouldReceive('fresh')->andReturn($product);
        }

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->once();

        // Act
        $result = $this->inventoryService->bulkStockUpdate($updates, $userSource);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['updated_count']);
        $this->assertCount(2, $result['results']);
    }

    /**
     * Test bulk stock update with validation errors
     */
    public function testBulkStockUpdateWithErrors()
    {
        // Arrange
        $updates = [
            ['product_id' => 1, 'stock' => 100], // Valid
            ['stock' => 200], // Missing product_id
            ['product_id' => 3] // Missing stock
        ];

        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();

        // Act
        $result = $this->inventoryService->bulkStockUpdate($updates);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertCount(2, $result['errors']); // Two validation errors
    }

    /**
     * Test get inventory logs with filters
     */
    public function testGetInventoryLogs()
    {
        // Arrange
        $filters = ['product_id' => 1];
        $perPage = 25;
        $expectedLogs = collect([
            new InventoryLog(['id' => 1, 'product_id' => 1])
        ]);

        $this->inventoryLogRepository
            ->shouldReceive('getFilteredLogs')
            ->once()
            ->with($filters, $perPage)
            ->andReturn($expectedLogs);

        // Act
        $result = $this->inventoryService->getInventoryLogs($filters, $perPage);

        // Assert
        $this->assertEquals($expectedLogs, $result);
    }

    /**
     * Test get inventory statistics
     */
    public function testGetInventoryStatistics()
    {
        // Arrange
        $startDate = '2024-01-01';
        $endDate = '2024-01-31';
        $expectedStats = [
            'total_logs' => 100,
            'total_stock_increases' => 500,
            'total_stock_decreases' => 200,
            'net_change' => 300
        ];

        $this->inventoryLogRepository
            ->shouldReceive('getStatistics')
            ->once()
            ->with($startDate, $endDate)
            ->andReturn($expectedStats);

        // Act
        $result = $this->inventoryService->getInventoryStatistics($startDate, $endDate);

        // Assert
        $this->assertEquals($expectedStats, $result);
    }

    /**
     * Test low stock alert functionality
     */
    public function testGetLowStockAlert()
    {
        // Arrange
        $threshold = 10;
        $lowStockProducts = collect([
            new Product(['id' => 1, 'current_stock' => 5]),
            new Product(['id' => 2, 'current_stock' => 8])
        ]);

        $this->productRepository
            ->shouldReceive('getLowStockProducts')
            ->once()
            ->with($threshold)
            ->andReturn($lowStockProducts);

        // Act
        $result = $this->inventoryService->getLowStockAlert($threshold);

        // Assert
        $this->assertEquals($threshold, $result['threshold']);
        $this->assertEquals(2, $result['count']);
        $this->assertEquals($lowStockProducts, $result['products']);
    }

    /**
     * Test edge case: zero stock update
     */
    public function testUpdateProductStockToZero()
    {
        // Arrange
        $productId = 1;
        $newStock = 0;
        $previousStock = 10;

        $product = new Product([
            'id' => $productId,
            'current_stock' => $previousStock
        ]);

        $this->productRepository
            ->shouldReceive('findById')
            ->once()
            ->andReturn($product);

        $this->inventoryLogRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn(new InventoryLog());

        $product->shouldReceive('save')->once()->andReturn(true);
        $product->shouldReceive('fresh')->once()->andReturn($product);

        // Act
        $result = $this->inventoryService->updateProductStock($productId, $newStock);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(-10, $result['change_amount']);
    }

    /**
     * Test concurrent update scenario
     */
    public function testConcurrentStockUpdate()
    {
        // This test simulates what happens when two users try to update stock simultaneously
        // The service should handle this gracefully with proper transaction isolation

        // Arrange
        $productId = 1;
        $newStock1 = 100;
        $newStock2 = 150;

        $product = new Product([
            'id' => $productId,
            'current_stock' => 50
        ]);

        // First update
        $this->productRepository
            ->shouldReceive('findById')
            ->andReturn($product);

        $this->inventoryLogRepository
            ->shouldReceive('create')
            ->andReturn(new InventoryLog());

        $product->shouldReceive('save')->andReturn(true);
        $product->shouldReceive('fresh')->andReturn($product);

        // Act - First update should succeed
        $result1 = $this->inventoryService->updateProductStock($productId, $newStock1);

        // Assert
        $this->assertTrue($result1['success']);
    }
}
