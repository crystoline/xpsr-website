<?php

declare(strict_types=1);

namespace App\Markdown;

final class RenderedMarkdown
{
    /**
     * @param string|null $title the text of the first level-1 heading
     * @param list<array{level: int, text: string, id: string}> $headings level-2 and level-3 headings
     * @param list<string> $problems links to files that do not exist, and similar mistakes
     */
    public function __construct(
        public readonly string $html,
        public readonly ?string $title,
        public readonly array $headings,
        public readonly array $problems,
    ) {}
}
