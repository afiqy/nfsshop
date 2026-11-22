<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('price_sen');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('services');
            $table->unsignedBigInteger('price_sen');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('workshop_pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('tax_rate_basis_points')->default(0);
            $table->timestamps();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('pricing')->nullable();
        });
        Schema::table('calendars', function (Blueprint $table) {
            $table->json('service_quote')->nullable();
        });
        Schema::create('appointment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('calendar_id')->nullable()->constrained('calendars')->nullOnDelete();
            $table->dateTime('start');
            $table->dateTime('end');
            $table->json('service_quote');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_requests');
        Schema::table('calendars', fn (Blueprint $table) => $table->dropColumn('service_quote'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('pricing'));
        Schema::dropIfExists('workshop_pricing_settings');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('workshop_services');
    }
};
