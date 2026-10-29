<?php

declare(strict_types=1);

namespace App\Docs;

/**
 * The sidebar of one documentation version: sections of pages, in reading order.
 *
 * It is read from docs/README.md: each "## Heading" starts a section, and each
 * "- [Title](path)" item under it is a page. Paths are relative to docs/.
 */
final class Navigation
{
    /** @param list<array{title: string, items: list<array{title: string, path: string}>}> $sections */
    public function __construct(public readonly array $sections) {}

    public static function parse(string $markdown): self
    {
        $sections = [];
        $current = null;
        $inComment = false;
        $inFence = false;
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if ($inComment) {
                $inComment = !str_contains($line, '-->');
                continue;
            }
            if (preg_match('/\A\s*(```|~~~)/', $line)) {
                $inFence = !$inFence;
                continue;
            }
            if ($inFence) {
                continue;
            }
            if (str_contains($line, '<!--') && !str_contains($line, '-->')) {
                $inComment = true;
                continue;
            }
            if (preg_match('/\A##\s+(.+?)\s*#*\s*\z/', $line, $match)) {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $current = ['title' => $match[1], 'items' => []];
                continue;
            }
            if ($current !== null && preg_match('/\A[-*+]\s+\[([^\]]+)\]\(([^)\s]+)\)\s*\z/', $line, $match)) {
                $path = Paths::resolve('docs', $match[2]);
                if ($path !== null && Paths::slug($path) !== null) {
                    $current['items'][] = ['title' => $match[1], 'path' => $path];
                }
            }
        }
        if ($current !== null) {
            $sections[] = $current;
        }

        return new self($sections);
    }

    /**
     * Keeps the pages whose file passes $keep, and the sections that still have pages.
     *
     * @param callable(string): bool $keep
     */
    public function filter(callable $keep): self
    {
        $sections = [];
        foreach ($this->sections as $section) {
            $items = array_values(array_filter($section['items'], static fn(array $item): bool => $keep($item['path'])));
            if ($items !== []) {
                $sections[] = ['title' => $section['title'], 'items' => $items];
            }
        }

        return new self($sections);
    }

    /** @return list<array{title: string, path: string, section: string}> */
    public function pages(): array
    {
        $pages = [];
        foreach ($this->sections as $section) {
            foreach ($section['items'] as $item) {
                $pages[] = $item + ['section' => $section['title']];
            }
        }

        return $pages;
    }

    /** The navigation as Markdown, for a version that has no docs/README.md of its own. */
    public function toMarkdown(string $title, string $intro = ''): string
    {
        $markdown = "# {$title}\n\n" . ($intro !== '' ? "{$intro}\n\n" : '');
        foreach ($this->sections as $section) {
            $markdown .= "## {$section['title']}\n\n";
            foreach ($section['items'] as $item) {
                $markdown .= "- [{$item['title']}](" . Paths::fromDocs($item['path']) . ")\n";
            }
            $markdown .= "\n";
        }

        return $markdown;
    }
}
