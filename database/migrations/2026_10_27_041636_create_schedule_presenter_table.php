<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_presenter', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('presenter_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();                  // "DJ", "Co-host", "Minister"
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['schedule_id', 'presenter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_presenter');
    }
};
