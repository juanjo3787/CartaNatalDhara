<?php

namespace App\Http\Controllers;

use App\Domain\Astrology\BirthDataNormalizer;
use App\Models\BirthData;
use App\Models\Chart;
use App\Models\Person;
use App\Models\Place;
use App\Models\PersonChangeLog;
use App\Models\ReportGeneration;
use App\Services\ChartService;
use App\Services\PhaseOneReportService;
use App\Services\PhaseOneAiGenerationService;
use App\Services\PhaseOneManualSaveService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class ChartController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $query = Chart::query()
            ->with('person')
            ->with(['interpretations:id,chart_id,phase'])
            ->latest();

        if ($search !== '') {
            $query->whereHas('person', function ($personQuery) use ($search) {
                $personQuery->where('alias', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        return view('charts.index', [
            'charts' => $query->paginate(20)->appends(['search' => $search]),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('charts.create');
    }

    public function store(Request $request, BirthDataNormalizer $normalizer, ChartService $chartService): RedirectResponse
    {
        $validated = $request->validate($this->registrationRules(), $this->registrationMessages());

        $validated['local_date'] = \DateTime::createFromFormat('d/m/Y', $validated['local_date'])->format('Y-m-d');

        $normalized = $normalizer->normalize($validated);

        $place = Place::create([
            'city' => $validated['city'],
            'country' => $validated['country'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'timezone_identifier' => $validated['timezone_identifier'],
        ]);

        $person = Person::create([
            'alias' => $validated['alias'],
            'full_name' => $validated['full_name'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $birthData = BirthData::create([
            'person_id' => $person->id,
            'place_id' => $place->id,
            'local_date' => $normalized->localDate,
            'local_time' => $normalized->localTime,
            'timezone_identifier' => $normalized->timezoneIdentifier,
            'utc_offset' => $normalized->utcOffset,
            'utc_datetime' => $normalized->utcDatetime,
            'time_source' => $normalized->timeSource,
            'time_precision' => $normalized->timePrecision,
        ]);

        $chart = $chartService->calculateFor($birthData->load('place'));

        return redirect()->route('charts.show', $chart);
    }

    public function show(Chart $chart): View
    {
        $chart->load('person', 'birthData.place');

        return view('charts.show', ['chart' => $chart]);
    }

    public function report(Chart $chart, PhaseOneReportService $reportService): View
    {
        return view('charts.report', [
            'chart' => $chart,
            'report' => $reportService->build($chart),
        ]);
    }

    public function downloadReport(Chart $chart, PhaseOneReportService $reportService)
    {
        if ($chart->phase_one_pdf) {
            return response(base64_decode($chart->phase_one_pdf, true), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="carta-natal-fase-1-' . $chart->id . '.pdf"',
            ]);
        }

        $report = $reportService->build($chart);
        $pdf = app('dompdf.wrapper')
            ->loadView('charts.report', ['chart' => $chart, 'report' => $report, 'pdf' => true])
            ->setPaper('a4', 'portrait');

        $output = $pdf->output();
        ReportGeneration::create([
            'chart_id' => $chart->id,
            'report_type' => 'fase-1',
            'filename' => 'carta-natal-fase-1-' . $chart->id . '.pdf',
            'size_bytes' => strlen($output),
            'checksum' => hash('sha256', $output),
        ]);

        return response()->streamDownload(fn () => print($output), 'carta-natal-fase-1-' . $chart->id . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function validateAndStoreReport(Chart $chart, PhaseOneReportService $reportService): RedirectResponse
    {
        return $this->storeReportPdf($chart, $reportService, 'Informe validado y PDF guardado correctamente.');
    }

    public function regenerateReport(Chart $chart, PhaseOneReportService $reportService): RedirectResponse
    {
        $chart->interpretations()
            ->where('phase', 'fase-1')
            ->delete();

        $chart->forceFill([
            'phase_one_pdf' => null,
            'phase_one_pdf_generated_at' => null,
        ])->save();

        return $this->storeReportPdf($chart, $reportService, 'Informe regenerado y PDF actualizado correctamente.');
    }

    private function storeReportPdf(Chart $chart, PhaseOneReportService $reportService, string $successMessage): RedirectResponse
    {
        $report = $reportService->build($chart);
        $reportService->persistGeneratedContent($chart, $report);
        $pdf = app('dompdf.wrapper')
            ->loadView('charts.report', ['chart' => $chart, 'report' => $report, 'pdf' => true])
            ->setPaper('a4', 'portrait')
            ->output();

        $chart->forceFill([
            'phase_one_pdf' => base64_encode($pdf),
            'phase_one_pdf_generated_at' => now(),
        ])->save();

        ReportGeneration::create([
            'chart_id' => $chart->id,
            'report_type' => 'fase-1',
            'filename' => 'carta-natal-fase-1-' . $chart->id . '.pdf',
            'size_bytes' => strlen($pdf),
            'checksum' => hash('sha256', $pdf),
        ]);

        return redirect()->route('charts.report', $chart)
            ->with('success', $successMessage);
    }

    private function registrationRules(?Person $person = null): array
    {
        $aliasRule = Rule::unique('people', 'alias');
        $fullNameRule = Rule::unique('people', 'full_name')->whereNotNull('full_name');

        if ($person) {
            $aliasRule->ignore($person->id);
            $fullNameRule->ignore($person->id);
        }

        return [
            'alias' => ['required', 'string', 'max:255', $aliasRule],
            'full_name' => ['required', 'string', 'max:255', $fullNameRule],
            'notes' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone_identifier' => ['required', 'timezone'],
            'local_date' => ['required', 'date_format:d/m/Y'],
            'local_time' => ['required', 'date_format:H:i'],
            'time_source' => ['required', 'in:document,family,estimated,unknown'],
            'time_precision' => ['required', 'in:exact,approximate,unknown'],
        ];
    }

    private function registrationMessages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute no puede superar los :max caracteres.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'between' => 'El campo :attribute está fuera del rango permitido.',
            'timezone' => 'La zona horaria indicada no es válida.',
            'date_format' => 'El campo :attribute debe tener el formato :format.',
            'in' => 'El valor seleccionado para :attribute no es válido.',
            'alias.unique' => 'Ya existe una carta natal para esa persona con este alias.',
            'full_name.unique' => 'Ya existe una carta natal para esa persona con este nombre completo.',
            'attributes' => [
                'alias' => 'alias',
                'full_name' => 'nombre completo',
                'city' => 'ciudad',
                'country' => 'país',
                'latitude' => 'latitud',
                'longitude' => 'longitud',
                'timezone_identifier' => 'zona horaria',
                'local_date' => 'fecha de nacimiento',
                'local_time' => 'hora de nacimiento',
                'time_source' => 'fuente de la hora',
                'time_precision' => 'precisión de la hora',
            ],
        ];
    }

    public function editRegistration(Chart $chart): View
    {
        $chart->load('person', 'birthData.place');

        return view('charts.registration-edit', ['chart' => $chart]);
    }

    public function updateRegistration(Request $request, Chart $chart, BirthDataNormalizer $normalizer): RedirectResponse
    {
        $chart->load('person', 'birthData.place');
        $validated = $request->validate(
            $this->registrationRules($chart->person),
            $this->registrationMessages(),
        );

        $validated['local_date'] = \DateTime::createFromFormat('d/m/Y', $validated['local_date'])->format('Y-m-d');
        $normalized = $normalizer->normalize($validated);
        $person = $chart->person;
        $changes = [];
        foreach (['alias', 'full_name', 'notes'] as $field) {
            $old = (string) ($person->{$field} ?? '');
            $new = (string) ($validated[$field] ?? '');
            if ($old !== $new) $changes[] = [$field, $old, $new];
        }
        $person->update(['alias' => $validated['alias'], 'full_name' => $validated['full_name'] ?? null, 'notes' => $validated['notes'] ?? null]);

        $place = $chart->birthData->place;
        foreach (['city', 'country', 'latitude', 'longitude', 'timezone_identifier'] as $field) {
            $old = (string) ($place->{$field} ?? '');
            $new = (string) $validated[$field];
            if ($old !== $new) $changes[] = ['place.' . $field, $old, $new];
        }
        $place->update(array_intersect_key($validated, array_flip(['city', 'country', 'latitude', 'longitude', 'timezone_identifier'])));
        $chart->birthData->update([
            'local_date' => $normalized->localDate, 'local_time' => $normalized->localTime,
            'timezone_identifier' => $normalized->timezoneIdentifier, 'utc_offset' => $normalized->utcOffset,
            'utc_datetime' => $normalized->utcDatetime, 'time_source' => $normalized->timeSource, 'time_precision' => $normalized->timePrecision,
        ]);

        foreach ($changes as [$field, $old, $new]) PersonChangeLog::create(['person_id' => $person->id, 'chart_id' => $chart->id, 'field' => $field, 'old_value' => $old, 'new_value' => $new]);

        return redirect()->route('charts.index')->with('success', 'Datos de registro actualizados.');
    }

    public function reportHistory(Chart $chart): View
    {
        $generations = $chart->reportGenerations()->latest()->get();
        $aiGenerations = $generations->where('ai_assisted', true);

        return view('charts.report-history', [
            'chart' => $chart,
            'generations' => $generations,
            'totals' => [
                'tokens' => (int) $aiGenerations->sum('total_tokens'),
                'subtotal' => (float) $aiGenerations->sum('cost_subtotal'),
                'tax' => (float) $aiGenerations->sum('tax_amount'),
                'total' => (float) $aiGenerations->sum('cost_total'),
                'currency' => $aiGenerations->pluck('cost_currency')->filter()->first() ?? config('ai.currency', 'EUR'),
            ],
        ]);
    }

    public function personHistory(Chart $chart): View
    {
        $chart->load('person');
        return view('charts.person-history', ['chart' => $chart, 'changes' => PersonChangeLog::where('person_id', $chart->person_id)->latest()->get()]);
    }

    public function editReport(Chart $chart, PhaseOneReportService $reportService): View
    {
        return view('charts.report-edit', [
            'chart' => $chart,
            'report' => $reportService->build($chart),
            'editable' => $reportService->editableContent($chart),
        ]);
    }

    public function saveReport(Request $request, Chart $chart, PhaseOneManualSaveService $saveService): RedirectResponse
    {
        $validated = $request->validate([
            'shared' => ['required', 'array'],
            'shared.*' => ['nullable', 'string'],
            'doors' => ['required', 'array'],
            'doors.*' => ['required', 'array'],
            'doors.*.*' => ['nullable', 'string'],
        ]);

        $saved = $saveService->save($chart, $validated['shared'], $validated['doors']);

        return redirect()->route('charts.report', $chart)
            ->with('success', "Informe guardado correctamente ({$saved} bloques).");
    }

    public function generateAiReport(Chart $chart, string $door, PhaseOneAiGenerationService $generationService): RedirectResponse
    {
        try {
            $blocks = $generationService->generateDoor($chart, $door);

            return redirect()->route('charts.report', $chart)
                ->with('success', "Se han generado {$blocks} bloques con IA para la puerta {$door}.");
        } catch (\Throwable $exception) {
            $message = str_replace((string) config('ai.api_key'), '[redacted]', $exception->getMessage());
            Log::error('Phase 1 AI generation failed', [
                'exception' => $exception::class,
                'message' => mb_substr($message, 0, 500),
                'door' => $door,
            ]);

            return redirect()->route('charts.report', $chart)
                ->with('error', 'Error de IA: '.$message);
        }
    }

    public function generateAllAiReport(Chart $chart, PhaseOneAiGenerationService $generationService): RedirectResponse
    {
        try {
            $blocks = $generationService->generateAll($chart);

            return redirect()->route('charts.report.edit', $chart)
                ->with('success', "Se han generado {$blocks} bloques con IA para las cuatro puertas.");
        } catch (\Throwable $exception) {
            $message = str_replace((string) config('ai.api_key'), '[redacted]', $exception->getMessage());
            Log::error('Phase 1 AI full generation failed', [
                'exception' => $exception::class,
                'message' => mb_substr($message, 0, 500),
                'chart' => $chart->id,
            ]);

            return redirect()->route('charts.report.edit', $chart)
                ->with('error', 'Error de IA: '.$message);
        }
    }

    public function destroy(Chart $chart): RedirectResponse
    {
        $chart->delete();

        return redirect()->route('charts.index')->with('success', 'La carta natal ha sido eliminada.');
    }
}
