<?php

namespace App\Http\Controllers;

use App\Models\Chart;
use App\Models\ReportJob;
use App\Services\ReportJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportJobController extends Controller
{
    public function store(Request $request, Chart $chart, ?array $doors = null): JsonResponse
    {
        abort_unless($request->user(), 401);
        $data = $request->validate(['doors' => 'sometimes|array|max:4', 'doors.*' => 'required|distinct|in:sol,luna,ascendente,descendente', 'wheel_image' => 'nullable|string|max:5000000']);
        $doors ??= $data['doors'] ?? ['sol', 'luna', 'ascendente', 'descendente'];
        abort_if(array_diff($doors, ['sol', 'luna', 'ascendente', 'descendente']) !== [], 422);
        abort_unless(is_array($chart->snapshot) && $chart->snapshot !== [], 422, 'La carta debe estar calculada.');
        $image = $data['wheel_image'] ?? null;
        abort_unless($image || $chart->natal_wheel_image, 422, 'Abre la carta y pulsa Generar informe cuando la rueda termine de dibujarse. Debemos conservarla en el PDF.');
        if ($image) {
            abort_unless(str_starts_with($image, 'data:image/jpeg;base64,'), 422);
            $binary = base64_decode(substr($image, strlen('data:image/jpeg;base64,')), true);
            abort_unless($binary !== false && str_starts_with($binary, "\xff\xd8\xff"), 422);
        }
        $job = app(ReportJobService::class)->start($chart, $request->user()->id, array_values(array_intersect(['sol', 'luna', 'ascendente', 'descendente'], $doors)), $image);

        return response()->json($this->payload($job), 202);
    }

    public function show(Request $request, ReportJob $job): JsonResponse
    {
        abort_unless($request->user()?->id === $job->user_id, 403);

        return response()->json($this->payload($job));
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user(), 401);
        $jobs = ReportJob::where('user_id', $request->user()->id)
            ->whereIn('id', ReportJob::selectRaw('MAX(id)')->where('user_id', $request->user()->id)->groupBy('chart_id'))
            ->where(function ($query): void {
                $query->where('status', '!=', 'completed')->orWhere('updated_at', '>=', now()->subDay());
            })->select(['id', 'chart_id', 'status', 'current_section', 'progress', 'error_code', 'error_message'])->latest('id')->get();

        return response()->json($jobs->map(fn ($job) => $this->payload($job)));
    }

    public function retry(Request $request, ReportJob $job): JsonResponse
    {
        abort_unless($request->user()?->id === $job->user_id, 403);

        return response()->json($this->payload(app(ReportJobService::class)->retry($job)), 202);
    }

    private function payload(ReportJob $job): array
    {
        $messages = ['queued' => 'En cola', 'preparing' => 'Preparando informe', 'generating_sun' => 'Generando Sol', 'generating_moon' => 'Generando Luna', 'generating_ascendant' => 'Generando Ascendente', 'generating_descendant' => 'Generando Descendente', 'generating_integration' => 'Preparando integración', 'validating' => 'Validando contenido', 'building_document' => 'Montando documento', 'generating_pdf' => 'Generando PDF', 'completed' => 'Informe disponible', 'failed' => 'Generación interrumpida'];

        return ['job_id' => $job->id, 'report_id' => $job->chart_id, 'status' => $job->status, 'current_section' => $job->current_section, 'progress' => $job->progress, 'message' => $messages[$job->status] ?? $job->status, 'error_code' => $job->error_code, 'error_message' => $job->error_message, 'status_url' => route('reports.jobs.show', $job), 'retry_url' => route('reports.jobs.retry', $job), 'report_url' => route('charts.report', $job->chart_id), 'pdf_url' => $job->status === 'completed' ? route('charts.report.download', $job->chart_id) : null];
    }
}
