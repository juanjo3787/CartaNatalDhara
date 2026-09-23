<?php

namespace App\Services\Doors;

final class DescendentePipeline extends AbstractDoorPipeline
{
    public function door(): string
    {
        return 'descendente';
    }

    public function label(): string
    {
        return 'el Descendente';
    }

    public function focus(): string
    {
        return 'el encuentro con otra persona, la reciprocidad, la confianza, los acuerdos y la autonomía compartida';
    }

    public function rulerParagraphCount(): int
    {
        return 8;
    }

    public function hasHarmonization(): bool
    {
        return false;
    }
}
