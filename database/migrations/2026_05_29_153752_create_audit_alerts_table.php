<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('audit_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_log_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('severity')->default('medium');
            $table->string('message');
            $table->json('meta')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_alerts');
    }
};