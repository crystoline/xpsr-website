<?php

declare(strict_types=1);

namespace App\Docs;

final class DocPage
{
    /**
     * @param string $slug "" for the index, "routing", or "packages/cache"
     * @param string $path the repository file, such as docs/ROUTING.md
     * @param string|null $section the sidebar section, or null for the index
     * @param list<array{level: int, text: string, id: string}> $headings
     */
    public function __construct(
        public readonly DocVersion $version,
        public readonly string $slug,
        public readonly string $path,
        public readonly string $title,
        public readonly ?string $section,
        public readonly string $html,
        public readonly array $headings,
    ) {}
}
