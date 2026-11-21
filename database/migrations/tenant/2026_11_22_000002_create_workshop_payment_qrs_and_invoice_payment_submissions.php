<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_payment_qrs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 16);
            $table->string('label', 100);
            $table->string('path');
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('created_by_user_id');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('invoice_payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->foreignId('invoice_payment_id')->nullable()->constrained('invoice_payments')->nullOnDelete();
            $table->string('payment_method', 100);
            $table->unsignedBigInteger('amount_sen');
            $table->string('reference')->nullable();
            $table->string('proof_path');
            $table->string('proof_mime_type', 64);
            $table->string('status', 16)->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payment_submissions');
        Schema::dropIfExists('workshop_payment_qrs');
    }
};
