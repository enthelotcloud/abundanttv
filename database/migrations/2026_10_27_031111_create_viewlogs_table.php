<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('view_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['show_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('view_logs');
    }
};
