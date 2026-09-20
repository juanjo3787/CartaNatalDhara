<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('interpretations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_id')->constrained('charts')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('templates')->restrictOnDelete();
            $table->string('phase')->default('fase-1');
            $table->longText('content');
            $table->boolean('ai_assisted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interpretations');
    }
};
