<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedInteger('mileage_km')->nullable();
            $table->string('fuel_type', 100)->nullable();
        });

        Schema::table('customer_vehicle_requests', function (Blueprint $table) {
            $table->unsignedInteger('mileage_km')->nullable();
            $table->string('fuel_type', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_vehicle_requests', function (Blueprint $table) {
            $table->dropColumn(['mileage_km', 'fuel_type']);
        });
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['mileage_km', 'fuel_type']);
        });
    }
};
