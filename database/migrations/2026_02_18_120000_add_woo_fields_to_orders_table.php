<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('woo_status')->nullable()->after('external_order_id')->index();
            $table->string('woo_currency', 8)->nullable()->after('service_price');
            $table->string('woo_payment_method')->nullable()->after('woo_currency');

            $table->string('woo_plan')->nullable()->after('woo_payment_method');
            $table->string('woo_hours')->nullable()->after('woo_plan');
            $table->text('woo_addons')->nullable()->after('woo_hours');

            $table->date('woo_session_date')->nullable()->after('woo_addons');
            $table->string('woo_session_time')->nullable()->after('woo_session_date');
            $table->unsignedBigInteger('woo_worker_id')->nullable()->after('woo_session_time');

            $table->string('woo_client_telegram')->nullable()->after('woo_worker_id');
            $table->string('woo_client_discord')->nullable()->after('woo_client_telegram');
            $table->string('woo_desired_datetime')->nullable()->after('woo_client_discord');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'woo_status',
                'woo_currency',
                'woo_payment_method',
                'woo_plan',
                'woo_hours',
                'woo_addons',
                'woo_session_date',
                'woo_session_time',
                'woo_worker_id',
                'woo_client_telegram',
                'woo_client_discord',
                'woo_desired_datetime',
            ]);
        });
    }
};

