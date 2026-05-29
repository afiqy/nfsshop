<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('user_profiles', 'avatar')) {
                $table->string('avatar')->default(null)->after('user_id');
            }
            if (! Schema::hasColumn('user_profiles', 'first_name')) {
                $table->string('first_name')->default("")->after('avatar');
            }
            if (! Schema::hasColumn('user_profiles', 'last_name')) {
                $table->string('last_name')->default("")->after('first_name');
            }
            if (Schema::hasColumn('user_profiles', 'user_id')) {
                $table->unique('user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
