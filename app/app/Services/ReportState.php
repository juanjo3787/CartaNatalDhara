<?php

namespace App\Services;

final class ReportState
{
    public const SCHEMA_VERSION = 2;

    public const PROMPT_VERSION = 'staged-plain-text-1';

    public const HEADINGS = [
        'harmony' => ['Características que puedes observar', 'Pautas y consideraciones para reconocer este equilibrio', 'Ejemplos cotidianos de estas pautas'],
        'deficit' => ['Características que puedes observar', 'Pautas y consideraciones para empezar a armonizar', 'Ejemplos cotidianos y formas de empezar a armonizar'],
        'excess' => ['Características que puedes observar', 'Pautas y consideraciones para recuperar una medida adecuada', 'Ejemplos cotidianos y formas de recuperar medida'],
    ];

    public static function assertNarrative(array $development, string $id): void
    {
        if ($development === []) {
            throw new \RuntimeException("SECTION_SCHEMA_CONTAMINATION: {$id}: falta development.");
        }
        foreach ($development as $paragraph) {
            if (! is_string($paragraph) || trim($paragraph) === '' || preg_match('/<[^>]+>|^\s*#{1,6}\s/mu', $paragraph)) {
                throw new \RuntimeException("SECTION_SCHEMA_CONTAMINATION: {$id}: desarrollo no narrativo.");
            }
            foreach (array_unique(array_merge(...array_values(self::HEADINGS))) as $heading) {
                if (mb_stripos(html_entity_decode(strip_tags($paragraph), ENT_QUOTES, 'UTF-8'), $heading) !== false) {
                    throw new \RuntimeException("SECTION_SCHEMA_CONTAMINATION: {$id}: {$heading} dentro de development.");
                }
            }
        }
    }

    public static function render(array $state, string $stateName): array
    {
        self::validate($state, $state['id'] ?? $stateName);
        $blocks = array_map(static fn (string $text): string => '<p>'.e($text).'</p>', $state['development']);
        foreach (['characteristics', 'guidelines', 'examples'] as $index => $key) {
            $blocks[] = '<h3>'.e(self::HEADINGS[$stateName][$index]).'</h3>';
            $blocks[] = '<ol>'.implode('', array_map(static fn (array $item): string => '<li>'.e($item['text']).'</li>', $state[$key])).'</ol>';
        }

        return $blocks;
    }

    public static function validate(array $state, string $id): void
    {
        self::assertNarrative($state['development'] ?? [], $id);
        foreach (['characteristics', 'guidelines', 'examples'] as $key) {
            $items = $state[$key] ?? [];
            if (count($items) !== 7 || array_column($items, 'id') !== range(1, 7)) {
                throw new \RuntimeException("SECTION_SCHEMA_CONTAMINATION: {$id}.{$key}: se requieren siete IDs únicos y ordenados.");
            }
            foreach ($items as $item) {
                if (! is_string($item['text'] ?? null) || trim($item['text']) === '') {
                    throw new \RuntimeException("SECTION_SCHEMA_CONTAMINATION: {$id}.{$key}: contenido vacío.");
                }
            }
        }
    }

    /** Explicit, lossless structural migration of the previously persisted renderer HTML. */
    public static function fromRendered(array $blocks, string $id, string $stateName): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div id="state">'.implode('', $blocks).'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $root = $document->getElementById('state');
        $state = ['id' => $id, 'section_schema_version' => self::SCHEMA_VERSION, 'source' => 'persisted_html_migration', 'development' => [], 'characteristics' => [], 'guidelines' => [], 'examples' => []];
        $headings = self::HEADINGS[$stateName];
        $keys = ['characteristics', 'guidelines', 'examples'];
        $index = -1;
        $expectList = false;
        foreach ($root->childNodes as $node) {
            if ($node instanceof \DOMText && trim($node->textContent) === '') {
                continue;
            }
            if ($node->nodeName === 'p' && $index === -1) {
                $state['development'][] = $node->textContent;
            } elseif ($node->nodeName === 'h3' && ! $expectList && trim($node->textContent) === ($headings[$index + 1] ?? null)) {
                $index++;
                $expectList = true;
            } elseif ($node->nodeName === 'ol' && $expectList) {
                foreach ($node->childNodes as $item) {
                    if ($item instanceof \DOMText && trim($item->textContent) === '') {
                        continue;
                    }
                    if ($item->nodeName !== 'li') {
                        throw new \RuntimeException("SECTION_SCHEMA_CONTAMINATION: {$id}: lista inválida.");
                    }
                    $state[$keys[$index]][] = ['id' => count($state[$keys[$index]]) + 1, 'text' => $item->textContent];
                }
                $expectList = false;
            } else {
                throw new \RuntimeException("SECTION_SCHEMA_CONTAMINATION: {$id}: estructura antigua incompatible; regenera esta puerta.");
            }
        }
        if ($index !== 2 || $expectList) {
            throw new \RuntimeException("SECTION_SCHEMA_CONTAMINATION: {$id}: cabeceras incompletas.");
        }
        self::validate($state, $id);

        return $state;
    }
}
