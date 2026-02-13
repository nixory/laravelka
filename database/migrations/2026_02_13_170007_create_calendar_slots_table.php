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
        Schema::create('calendar_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->enum('status', ['available', 'reserved', 'booked', 'blocked'])->default('available')->index();
            $table->enum('source', ['manual', 'auto', 'order'])->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['worker_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_slots');
    }
};
