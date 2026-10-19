<?php

namespace App\Services;

use App\Models\Chart;
use App\Models\ChartTemplate;
use App\Models\ReportJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PhaseOneManualSaveService
{
    public function save(Chart $chart, array $shared, array $doors): int
    {
        return DB::transaction(function () use ($chart, $shared, $doors): int {
            $chart = Chart::whereKey($chart->id)->lockForUpdate()->firstOrFail();
            abort_if(ReportJob::where('active_chart_id', $chart->id)->exists(), 409, 'Espera a que termine la generación antes de guardar cambios en este informe.');
            abort_if($chart->fresh()->report_is_stale, 409, 'Regenera el Informe I con los datos actuales antes de editarlo.');
            app(PhaseOneReportService::class)->build($chart, storedOnly: true);
            $expectedShared = ['intro', ...array_keys(InteriorMapMaster::INTEGRATION_TITLES)];
            abort_if(array_diff($expectedShared, array_keys($shared)) || array_diff(array_keys($shared), $expectedShared), 422, 'Secciones compartidas del nuevo modelo incompletas.');
            abort_if(array_diff(InteriorMapMaster::DOORS, array_keys($doors)) || array_diff(array_keys($doors), InteriorMapMaster::DOORS), 422, 'Se requieren las cuatro puertas.');
            $texts = $shared;
            foreach ($doors as $blocks) {
                $keys = array_keys(InteriorMapMaster::TITLES);
                abort_if(array_diff($keys, array_keys($blocks)) || array_diff(array_keys($blocks), $keys), 422, 'Secciones de puerta incompletas.');
                $texts = [...$texts, ...array_values($blocks)];
            }
            foreach ($texts as $text) {
                abort_if(! is_string($text) || trim(strip_tags($text)) === '', 422, 'No se pueden guardar secciones vacías.');
            }
            $master = app(InteriorMapMaster::class);
            $facts = $master->facts($chart);
            foreach ($doors as $door => $blocks) {
                $plain = array_map(fn ($html) => [$this->plainText($html)], $blocks);
                try {
                    foreach (['harmony', 'deficit', 'excess'] as $state) {
                        preg_match_all('/<li\b[^>]*>(.*?)<\/li>/isu', $blocks[$state], $items);
                        if (count($items[1]) !== 5 || count(array_filter($items[1], fn ($item) => trim($this->plainText($item)) !== '')) !== 5) {
                            throw new \RuntimeException('Cada estado debe conservar una lista de cinco comportamientos.');
                        }
                    }
                    $master->validateEditorialMethod($plain);
                    $master->validateFacts($plain, $facts, $door);
                } catch (\RuntimeException $exception) {
                    throw ValidationException::withMessages(['doors' => $exception->getMessage()]);
                }
            }
            try {
                $master->validateEditorialMethod(['integration' => array_map($this->plainText(...), $shared)]);
            } catch (\RuntimeException $exception) {
                throw ValidationException::withMessages(['shared' => $exception->getMessage()]);
            }
            $words = $master->wordCount(['text' => array_map($this->plainText(...), array_values($texts))]);
            abort_if($words < 4000 || $words > 5500, 422, 'El Informe I debe conservar una extensión de 4000–5500 palabras.');
            $chart->interpretations()->where('phase', 'fase-1')->delete();
            $saved = 0;

            foreach ($shared as $block => $content) {
                $storedBlock = $block === 'intro' ? 'shared_intro' : $block;
                $this->storeBlock($chart, null, $storedBlock, $content, $saved);
            }

            foreach ($doors as $door => $blocks) {
                foreach ($blocks as $block => $content) {
                    $this->storeBlock($chart, $door, $block, $content, $saved);
                }
            }

            $chart->forceFill(['phase_one_pdf' => null, 'phase_one_pdf_generated_at' => null])->save();

            return $saved;
        });
    }

    private function storeBlock(Chart $chart, ?string $door, string $block, string $content, int &$saved): void
    {
        $content = $this->sanitize($content);
        $name = 'interior_map_'.($door ?? 'shared').'_'.$block.'_manual';
        $template = ChartTemplate::firstOrCreate(
            ['name' => $name, 'version' => InteriorMapMaster::VERSION],
            [
                'door' => $door,
                'block' => $block,
                'content_type' => 'phase1_manual',
                'status' => 'published',
                'content' => 'Contenido editorial guardado desde el editor del informe.',
            ],
        );

        $chart->interpretations()->create([
            'template_id' => $template->id,
            'phase' => 'fase-1',
            'door' => $door,
            'block' => $block,
            'content' => trim($content),
            'ai_assisted' => false,
            'rulers_used' => [],
        ]);
        $saved++;
    }

    private function sanitize(string $content): string
    {
        $content = strip_tags($content, '<p><strong><em><u><ul><ol><li><br><h3><h4>');

        return preg_replace(
            '/<((?:p|strong|em|u|ul|ol|li|br|h3|h4))\b[^>]*>/i',
            '<$1>',
            $content,
        ) ?? '';
    }

    private function plainText(string $html): string
    {
        $html = str_ireplace(['</p>', '</li>', '<br>', '<br/>', '<br />', '</h3>', '</h4>'], ' ', $html);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
