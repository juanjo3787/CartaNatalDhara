<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['templates', 'interpretations'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('block', 64)->change();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('No se puede restaurar el enum anterior sin perder las nuevas secciones. Revisa los datos antes de una migración inversa.');
    }
};
