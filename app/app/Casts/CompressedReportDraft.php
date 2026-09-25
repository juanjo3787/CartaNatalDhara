<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class CompressedReportDraft implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }
        if (str_starts_with($value, 'gzip:')) {
            $value = gzdecode(base64_decode(substr($value, 5), true));
            if ($value === false) {
                throw new \RuntimeException('No se pudo leer el borrador comprimido.');
            }
        }

        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (! config('reports.compress_drafts', true)) {
            return $json;
        }

        return 'gzip:'.base64_encode(gzencode($json, 6));
    }
}
