<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('engagement_reminders_opt_in')->default(false);
        });
        Schema::create('loyalty_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->integer('points');
            $table->string('reason', 500);
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->timestamps();
        });
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('active')->default(false);
            $table->timestamps();
        });
        Schema::create('vehicle_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->date('due_on');
            $table->string('note', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('vehicle_reminder_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_reminder_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->string('recipient_email');
            $table->string('status', 20);
            $table->string('failure_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_reminder_attempts');
        Schema::dropIfExists('vehicle_reminders');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('loyalty_entries');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('engagement_reminders_opt_in'));
    }
};
