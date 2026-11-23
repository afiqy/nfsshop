<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('job_card_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->string('payment_method')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['job_card_id']);
            $table->dropColumn('job_card_id');
        });
        Schema::table('invoice_payments', fn (Blueprint $table) => $table->dropColumn('payment_method'));
    }
};
