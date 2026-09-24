<?php

namespace App\Services;

use App\Models\Chart;
use App\Models\ChartTemplate;
use Illuminate\Support\Facades\DB;

final class PhaseOneManualSaveService
{
    public function save(Chart $chart, array $shared, array $doors): int
    {
        return DB::transaction(function () use ($chart, $shared, $doors): int {
            $chart->interpretations()->where('phase', 'fase-1')->delete();
            $saved = 0;

            foreach ($shared as $block => $content) {
                $storedBlock = ['intro' => 'shared_intro', 'states' => 'shared_states', 'conclusions' => 'shared_conclusions'][$block] ?? $block;
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
        $name = 'fase1_'.($door ?? 'shared').'_'.$block.'_manual';
        $template = ChartTemplate::firstOrCreate(
            ['name' => $name, 'version' => 2],
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
}
