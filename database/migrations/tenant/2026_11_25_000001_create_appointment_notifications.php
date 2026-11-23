<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendars', fn (Blueprint $table) => $table->index(['status', 'start']));
        Schema::create('appointment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('calendar_id')->nullable()->constrained('calendars')->nullOnDelete();
            $table->string('event');
            $table->string('recipient_type');
            $table->string('service');
            $table->string('vehicle_plate');
            $table->dateTime('start');
            $table->dateTime('end')->nullable();
            $table->dateTime('reminder_start')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('recipient_email')->nullable();
            $table->string('failure_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['calendar_id', 'recipient_type', 'reminder_start'], 'appointment_reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_notifications');
        Schema::table('calendars', fn (Blueprint $table) => $table->dropIndex(['status', 'start']));
    }
};
