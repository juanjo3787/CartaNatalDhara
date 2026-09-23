<?php

namespace App\Services\Doors;

final class AscendentePipeline extends AbstractDoorPipeline
{
    public function door(): string
    {
        return 'ascendente';
    }

    public function label(): string
    {
        return 'el Ascendente';
    }

    public function focus(): string
    {
        return 'la manera de entrar en una experiencia nueva, orientarse, dar el primer paso y sostener un ritmo';
    }

    public function rulerParagraphCount(): int
    {
        return 6;
    }

    public function hasHarmonization(): bool
    {
        return false;
    }
}
