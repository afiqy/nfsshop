<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('account_owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE tenants
            SET account_owner_id = (
                SELECT users.id
                FROM users
                WHERE users.tenant_id = tenants.id
                  AND users.role = 'admin'
                ORDER BY users.id
                LIMIT 1
            )
            WHERE account_owner_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_owner_id');
        });
    }
};