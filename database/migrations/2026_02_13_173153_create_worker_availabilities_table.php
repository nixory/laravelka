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
        Schema::create('worker_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['worker_id', 'day_of_week', 'start_time', 'end_time'], 'worker_availability_unique_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worker_availabilities');
    }
};
