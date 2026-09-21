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
        Schema::create('birth_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            $table->date('local_date');
            $table->time('local_time');
            $table->string('timezone_identifier');
            $table->string('utc_offset');
            $table->dateTime('utc_datetime');
            $table->enum('time_source', ['document', 'family', 'estimated', 'unknown'])->default('unknown');
            $table->enum('time_precision', ['exact', 'approximate', 'unknown'])->default('unknown');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('birth_data');
    }
};
