<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_reminder_attempts', function (Blueprint $table) {
            $table->unsignedBigInteger('requested_by_user_id')->nullable()->change();
            $table->date('scheduled_for')->nullable()->after('vehicle_reminder_id');
            $table->unique(['vehicle_reminder_id', 'scheduled_for'], 'vehicle_reminder_scheduled_attempt_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_reminder_attempts', function (Blueprint $table) {
            $table->dropUnique('vehicle_reminder_scheduled_attempt_unique');
            $table->dropColumn('scheduled_for');
        });
        // Automated audit rows have no user actor, so this column must remain nullable.
    }
};
