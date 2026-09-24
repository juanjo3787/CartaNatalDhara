<?php

namespace App\Exceptions;

use App\Models\Chart;
use Illuminate\Http\Request;
use RuntimeException;

final class SectionSchemaException extends RuntimeException
{
    public function render(Request $request): mixed
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'error_code' => 'SECTION_SCHEMA_CONTAMINATION'], 422);
        }
        $chart = $request->route('chart');
        preg_match('/SECTION_SCHEMA_CONTAMINATION: (sol|luna|ascendente|descendente)\./', $this->getMessage(), $matches);
        if (! $chart instanceof Chart || ! isset($matches[1])) {
            return false;
        }

        return response()->view('reports.phase-one.invalid-state', ['chart' => $chart, 'door' => $matches[1]], 422);
    }
}
