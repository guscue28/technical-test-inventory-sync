<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->integer('change_amount');
            $table->string('user_source')->default('system');
            $table->timestamps();

            // Composite index for optimized reporting queries
            // This index will significantly improve performance for:
            // - Filtering by product_id and date range
            // - Ordering by created_at within a product
            $table->index(['product_id', 'created_at'], 'idx_product_date_composite');

            // Additional indexes for common query patterns
            $table->index('user_source');
            $table->index('created_at');
            $table->index('change_amount'); // For statistics queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
