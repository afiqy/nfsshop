<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'staff_invitations'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('can_view_vehicle_history')->default(false);
                $table->boolean('can_create_customer_records')->default(false);
                $table->boolean('can_review_appointments')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'staff_invitations'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn([
                'can_view_vehicle_history', 'can_create_customer_records', 'can_review_appointments',
            ]));
        }
    }
};
