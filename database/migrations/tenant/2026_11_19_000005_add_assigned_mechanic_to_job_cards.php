<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_mechanic_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropIndex(['assigned_mechanic_id']);
            $table->dropColumn('assigned_mechanic_id');
        });
    }
};
