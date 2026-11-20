<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_card_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_card_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('actor_user_id');
            $table->string('actor_name');
            $table->string('type', 24);
            $table->string('status', 24)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['job_card_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_card_updates');
    }
};
