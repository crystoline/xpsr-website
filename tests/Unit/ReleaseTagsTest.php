<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Docs\ReleaseTags;
use PHPUnit\Framework\TestCase;

final class ReleaseTagsTest extends TestCase
{
    public function testTheLatestPatchOfEachMinorVersionIsDocumentedNewestFirst(): void
    {
        $tags = ['v0.1.0', 'v0.2.0', 'v0.3.0', 'v0.3.2', 'v0.3.10', 'v0.4.0', 'v0.4.1-beta', 'v1.0.0', 'latest', 'v0.5'];

        self::assertSame(['0.4' => 'v0.4.0', '0.3' => 'v0.3.10'], ReleaseTags::select($tags, '0.3', ['v1.0.0']));
    }

    public function testNoTagsMeansNoReleases(): void
    {
        self::assertSame([], ReleaseTags::select([], '0.3'));
    }
}
