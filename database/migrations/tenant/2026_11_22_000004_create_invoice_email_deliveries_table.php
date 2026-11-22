<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('retry_of_id')->nullable()->constrained('invoice_email_deliveries')->restrictOnDelete();
            $table->string('recipient_email');
            $table->unsignedBigInteger('sent_by_user_id');
            $table->string('status', 20);
            $table->text('failure_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_email_deliveries');
    }
};
