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
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('RUB');
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid', 'cancelled'])->default('pending')->index();
            $table->string('payment_method')->nullable();
            $table->string('payment_details')->nullable();
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('requested_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};

