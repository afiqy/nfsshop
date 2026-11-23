<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->boolean('managed')->default(false);
            $table->unsignedBigInteger('credential_version')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->index(['user_id', 'managed', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'managed', 'revoked_at']);
            $table->dropColumn(['managed', 'credential_version', 'revoked_at']);
        });
    }
};
