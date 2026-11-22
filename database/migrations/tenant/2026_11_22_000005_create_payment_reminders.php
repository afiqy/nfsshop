<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reminder_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
        DB::table('payment_reminder_settings')->insert(['id' => 1, 'enabled' => false]);

        Schema::create('invoice_payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->date('due_date');
            $table->unsignedTinyInteger('days_overdue');
            $table->timestamps();
            $table->unique(['invoice_id', 'due_date', 'days_overdue'], 'invoice_reminder_stage_unique');
        });

        Schema::create('invoice_payment_reminder_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_payment_reminder_id');
            $table->foreign('invoice_payment_reminder_id', 'reminder_attempt_stage_foreign')
                ->references('id')->on('invoice_payment_reminders')->restrictOnDelete();
            $table->string('recipient_email')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('status', 20);
            $table->string('failure_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payment_reminder_attempts');
        Schema::dropIfExists('invoice_payment_reminders');
        Schema::dropIfExists('payment_reminder_settings');
    }
};
