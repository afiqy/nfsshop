<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('status', 16)->default('issued')->index()->after('total_sen');
            $table->timestamp('voided_at')->nullable()->after('status');
            $table->unsignedBigInteger('voided_by_user_id')->nullable()->after('voided_at');
            $table->text('void_reason')->nullable()->after('voided_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'voided_at', 'voided_by_user_id', 'void_reason']);
        });
    }
};
