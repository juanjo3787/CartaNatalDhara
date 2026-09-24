<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiar datos existentes (PDFs en base64) antes de cambiar el tipo
        DB::table('charts')->update(['phase_one_pdf' => null]);

        Schema::table('charts', function (Blueprint $table): void {
            // Cambiar de LONGTEXT a VARCHAR para guardar la ruta del archivo
            $table->string('phase_one_pdf', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('charts', function (Blueprint $table): void {
            // Revertir a LONGTEXT
            $table->longText('phase_one_pdf')->nullable()->change();
        });
    }
};
