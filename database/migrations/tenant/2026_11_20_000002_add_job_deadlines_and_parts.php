<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_cards', function (Blueprint $table) {
            $table->date('due_date')->nullable()->index();
        });

        Schema::create('job_card_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_movement_id')->constrained()->restrictOnDelete();
            $table->foreignId('return_for_id')->nullable()->constrained('job_card_parts')->restrictOnDelete();
            $table->unsignedBigInteger('recorded_by_user_id');
            $table->string('type', 16);
            $table->string('item_sku', 64);
            $table->string('item_name');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->index(['job_card_id', 'type', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_card_parts');

        Schema::table('job_cards', function (Blueprint $table) {
            $table->dropIndex(['due_date']);
            $table->dropColumn('due_date');
        });
    }
};
