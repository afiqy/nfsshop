<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('short_name', 100)->nullable()->after('name');
            // This references central users, so a tenant-database foreign key is unavailable.
            $table->unsignedBigInteger('portal_user_id')->nullable()->unique()->after('address');
        });

        Schema::create('customer_vehicle_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('requested_by_user_id');
            $table->string('plate_number')->index();
            $table->string('chassis_number')->index();
            $table->string('engine_number')->index();
            $table->string('brand');
            $table->string('model');
            $table->string('year')->nullable();
            $table->string('color')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_vehicle_requests');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['portal_user_id']);
            $table->dropColumn(['short_name', 'portal_user_id']);
        });
    }
};
