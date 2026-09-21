<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chart_id')->constrained('charts')->cascadeOnDelete();
            $table->string('report_type')->default('fase-1');
            $table->string('filename');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_generations');
    }
};
