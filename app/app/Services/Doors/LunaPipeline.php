<?php

namespace App\Services\Doors;

final class LunaPipeline extends AbstractDoorPipeline
{
    public function door(): string
    {
        return 'luna';
    }

    public function label(): string
    {
        return 'la Luna';
    }

    public function focus(): string
    {
        return 'las necesidades emocionales, la seguridad, el afecto, la regulación y el cuidado';
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
