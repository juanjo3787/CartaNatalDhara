<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reports:timings {job?} {--before= : IDs separados por comas} {--after= : IDs separados por comas}')]
#[Description('Muestra tiempos medidos, intentos y comparación de informes completados')]
class ReportTimings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->argument('job')) {
            $job = \App\Models\ReportJob::findOrFail($this->argument('job'));
            $metrics = \Illuminate\Support\Facades\DB::table('report_metrics')->where('report_job_id', $job->id)->orderBy('id')->get();
            if ($metrics->isEmpty()) {
                $this->warn('No hay métricas para este informe. No se pueden reconstruir tiempos anteriores.');

                return self::FAILURE;
            }
            $end = $job->completed_at ?? now();
            $this->info('total_duration_ms (desde encolado, incluye esperas y reintentos): '.(int) $job->created_at->diffInMilliseconds($end));
            $names = ['preparing' => 'prepare_chart_data', 'sol' => 'sun', 'luna' => 'moon', 'ascendente' => 'ascendant', 'descendente' => 'descendant', 'generating_integration' => 'integration', 'validating' => 'validation', 'building_document' => 'document_build', 'generating_pdf' => 'pdf_generation'];
            $rows = $metrics->where('kind', 'step')->groupBy(fn ($metric) => $names[explode('.', $metric->section_id)[0]] ?? $metric->section_id)
                ->map(fn ($group, $name) => [$name, $group->sum('duration_ms'), $group->count(), $group->where('outcome', 'error')->count(), $group->where('outcome', 'running')->count()])->values()->all();
            $this->table(['Etapa', 'Tiempo activo ms', 'Intentos', 'Errores', 'Sin terminar'], $rows);
            $this->table(['Sección IA', 'Inicio', 'Fin', 'ms', 'Intento', 'Modelo', 'Entrada', 'Salida', 'Resultado'], $metrics->where('kind', 'ai')->map(fn ($m) => [$m->section_id, $m->started_at, $m->finished_at, $m->duration_ms, $m->attempt, $m->model, $m->input_tokens, $m->output_tokens, $m->outcome])->all());

            return self::SUCCESS;
        }
        $averages = [];
        foreach (['before', 'after'] as $group) {
            $ids = array_values(array_unique(array_filter(explode(',', (string) $this->option($group)))));
            $jobs = \App\Models\ReportJob::whereIn('id', $ids)->where('status', 'completed')->get();
            if ($jobs->isEmpty() || $jobs->count() !== count($ids)) {
                $this->error('Indica un job o --before=IDs --after=IDs de informes completados comparables.');

                return self::FAILURE;
            }
            $averages[$group] = $jobs->avg(fn ($job) => $job->created_at->diffInMilliseconds($job->completed_at));
            $this->line($group.': n='.$jobs->count().' average_generation_time_ms='.round($averages[$group]));
        }
        $this->line('improvement_percent='.($averages['before'] > 0 ? round(100 * (1 - $averages['after'] / $averages['before']), 2) : 'N/A'));
        $this->warn('Comparación descriptiva: usa mismas puertas, modelo e infraestructura; incluye esperas y reintentos.');

        return self::SUCCESS;
    }
}
