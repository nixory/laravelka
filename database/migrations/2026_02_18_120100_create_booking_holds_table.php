<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_holds', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->dateTime('expires_at')->index();
            $table->dateTime('confirmed_at')->nullable()->index();
            $table->dateTime('released_at')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->timestamps();

            $table->index(['worker_id', 'starts_at', 'ends_at'], 'booking_holds_worker_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_holds');
    }
};

