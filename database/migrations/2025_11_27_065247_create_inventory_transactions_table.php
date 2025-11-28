<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use phpDocumentor\Reflection\Types\Nullable;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();

            // Kolom tabel input user 
            $table->enum('type', ['Pembelian', 'Penjualan']); //tipe transaksi
            $table->date('date');
            $table->string('description')->nullable();

            $table->decimal('qty_input', 12, 2);
            $table->decimal('price_input', 15, 4);

            $table->decimal('qty', 12, 5);
            $table->decimal('cost', 15, 4);
            $table->decimal('total_cost', 15, 4);
            $table->decimal('qty_balance', 12, 5);
            $table->decimal('value_balance', 15, 4);
            $table->decimal('hpp', 15, 4);

            $table->timestamps();

            $table->index(['date', 'created_at']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
