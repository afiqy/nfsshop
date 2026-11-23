<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_create_job_invoices')->default(false);
            $table->boolean('can_record_payments')->default(false);
        });
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->boolean('can_create_job_invoices')->default(false);
            $table->boolean('can_record_payments')->default(false);
            $table->boolean('for_existing_user')->default(false);
            $table->foreignId('existing_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('existing_user_id');
            $table->dropColumn(['can_create_job_invoices', 'can_record_payments', 'for_existing_user']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['can_create_job_invoices', 'can_record_payments']));
    }
};
