<?php

namespace App\Services\Doors;

final class SolPipeline extends AbstractDoorPipeline
{
    public function door(): string
    {
        return 'sol';
    }

    public function label(): string
    {
        return 'el Sol';
    }

    public function focus(): string
    {
        return 'la identidad, la voluntad, la elección y la dirección personal';
    }

    public function rulerParagraphCount(): int
    {
        return 6;
    }

    public function hasHarmonization(): bool
    {
        return true;
    }
}
