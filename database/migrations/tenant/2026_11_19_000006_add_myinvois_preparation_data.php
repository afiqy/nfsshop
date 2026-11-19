<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_tax_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->text('details');
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->text('myinvois_buyer')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('myinvois_buyer');
        });

        Schema::dropIfExists('workshop_tax_profiles');
    }
};
