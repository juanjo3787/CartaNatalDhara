<?php

namespace Tests\Unit;

use App\Services\SunTemplateCatalog;
use PHPUnit\Framework\TestCase;

class SunTemplateCatalogTest extends TestCase
{
    public function test_catalog_exposes_the_required_sun_blocks_and_shared_text(): void
    {
        $catalog = new SunTemplateCatalog();

        $this->assertSame('sol', $catalog->door());
        $this->assertSame(['shared_intro', 'shared_states', 'shared_conclusions'], array_keys($catalog->sharedBlocks()));
        $this->assertSame([
            'function',
            'sign',
            'house',
            'ruler',
            'integration',
            'harmony',
            'deficit',
            'excess',
            'closing',
        ], array_keys($catalog->doorBlocks()));
        $this->assertNotEmpty($catalog->sharedBlocks()['shared_intro']);
        $this->assertNotEmpty($catalog->doorBlocks()['function']);
    }
}
