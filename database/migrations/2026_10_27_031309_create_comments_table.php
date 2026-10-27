<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('show_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone');                              // +254712345678
            $table->string('device_id', 64)->nullable();          // localStorage UUID for identity
            $table->text('body');
            $table->string('ip_address', 45)->nullable();
            $table->boolean('has_banned_word')->default(false);   // drives grey-out on frontend
            $table->timestamps();

            $table->index(['show_id', 'created_at']);
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
