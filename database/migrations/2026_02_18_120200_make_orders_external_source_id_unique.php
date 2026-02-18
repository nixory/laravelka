<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['external_source', 'external_order_id']);
            $table->unique(['external_source', 'external_order_id'], 'orders_external_source_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_external_source_order_unique');
            $table->index(['external_source', 'external_order_id']);
        });
    }
};

