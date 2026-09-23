<?php

namespace App\Services\Doors;

use InvalidArgumentException;

final class DoorPipelineFactory
{
    public static function for(string $door): AbstractDoorPipeline
    {
        return match ($door) {
            'sol' => new SolPipeline(),
            'luna' => new LunaPipeline(),
            'ascendente' => new AscendentePipeline(),
            'descendente' => new DescendentePipeline(),
            default => throw new InvalidArgumentException("Puerta Fase 1 no válida: {$door}"),
        };
    }
}
