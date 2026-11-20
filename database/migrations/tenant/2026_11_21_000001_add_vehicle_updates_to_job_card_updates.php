<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_card_updates', function (Blueprint $table) {
            $table->unsignedInteger('mileage_km')->nullable();
            $table->text('vehicle_condition')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_card_updates', function (Blueprint $table) {
            $table->dropColumn(['mileage_km', 'vehicle_condition']);
        });
    }
};
