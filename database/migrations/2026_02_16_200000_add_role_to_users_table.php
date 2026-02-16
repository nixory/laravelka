<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('admin')->after('password')->index();
        });

        DB::table('users')
            ->whereNull('role')
            ->update(['role' => 'admin']);

        $workerUserIds = DB::table('workers')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        if (! empty($workerUserIds)) {
            DB::table('users')
                ->whereIn('id', $workerUserIds)
                ->update(['role' => 'worker']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
