<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_payment_id')->constrained('invoice_payments')->restrictOnDelete();
            $table->unsignedBigInteger('amount_sen');
            $table->text('reason');
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('refunded_by_user_id');
            $table->timestamps();
        });

        Schema::create('invoice_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_sen');
            $table->text('reason');
            $table->unsignedBigInteger('issued_by_user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_credit_notes');
        Schema::dropIfExists('invoice_refunds');
    }
};
