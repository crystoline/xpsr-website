<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Docs\DocVersion;

/**
 * What the CommonMark listeners need to know about the page being rendered, and what they
 * find out about it.
 *
 * @internal
 */
final class RenderContext
{
    public ?string $title = null;
    /** @var list<array{level: int, text: string, id: string}> */
    public array $headings = [];
    /** @var list<string> */
    public array $problems = [];

    /**
     * @param string $path the repository file being rendered, such as docs/ROUTING.md
     * @param array<string, string> $pages repository path => site URL of every page in this version
     */
    public function __construct(
        public readonly DocVersion $version,
        public readonly string $path,
        public readonly array $pages,
    ) {}
}
