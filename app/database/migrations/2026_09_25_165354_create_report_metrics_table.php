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
        Schema::create('report_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_job_id')->constrained('report_jobs')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('section_id');
            $table->string('cohort');
            $table->unsignedInteger('attempt');
            $table->string('model')->nullable();
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->timestamp('started_at', 3);
            $table->timestamp('finished_at', 3)->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->string('outcome', 16)->default('running');
            $table->string('error_code')->nullable();
            $table->index(['report_job_id', 'kind', 'section_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_metrics');
    }
};
