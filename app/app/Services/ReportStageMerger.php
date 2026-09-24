<?php

namespace App\Services;

final class ReportStageMerger
{
    public static function merge(array $completed, array $result): array
    {
        foreach ($result as $key => $value) {
            if ($key === '_usage') {
                continue;
            }
            if ($key === 'examples' && is_array($value)) {
                if (array_column($value, 'id') === range(1, 7)) {
                    $completed[$key] = $value;

                    continue;
                }
                $byId = [];
                foreach ([...($completed[$key] ?? []), ...$value] as $item) {
                    $byId[$item['id']] = $item;
                }
                ksort($byId);
                $completed[$key] = array_values($byId);
            } elseif (is_array($value) && ! array_is_list($value)) {
                $completed[$key] = self::merge($completed[$key] ?? [], $value);
            } else {
                // Lists are replacements; only the two explicit example batches are combined by ID.
                $completed[$key] = $value;
            }
        }

        return $completed;
    }
}
