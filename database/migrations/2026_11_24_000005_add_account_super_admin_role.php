<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','mechanic','client','super_admin','account_super_admin') DEFAULT 'client'");
    }

    public function down(): void
    {
        if (DB::table('users')->where('role', 'account_super_admin')->exists()) {
            throw new \RuntimeException('Cannot remove the account super admin role while accounts still use it.');
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','mechanic','client','super_admin') DEFAULT 'client'");
    }
};
