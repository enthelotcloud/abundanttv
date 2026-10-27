<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('show_id')->constrained()->cascadeOnDelete();
            $table->time('start_time');                           // 13:00:00
            $table->time('end_time');                             // 13:30:00 (auto from show duration)
            $table->string('label')->nullable();                  // optional: "Prime Time", "Late Edition"
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
