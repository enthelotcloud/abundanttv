<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presenters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('role')->nullable();
            $table->string('phone')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('phone_visible')->default(false);
            $table->boolean('email_visible')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presenters');
    }
};
