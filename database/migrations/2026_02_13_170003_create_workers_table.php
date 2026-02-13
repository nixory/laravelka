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
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->string('phone')->nullable();
            $table->string('telegram')->nullable();
            $table->string('city')->nullable();
            $table->enum('status', ['offline', 'online', 'busy', 'paused'])->default('offline')->index();
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('completed_orders')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
