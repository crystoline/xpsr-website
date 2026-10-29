<?php

declare(strict_types=1);

namespace App\Docs;

use App\Markdown\MarkdownRenderer;
use JsonException;

/**
 * The documentation of every version: its sidebar, its pages, and its packages.
 * Pages are rendered on first use and kept for the rest of the process.
 */
final class DocsLibrary
{
    private const INDEX = 'docs/README.md';

    private ?VersionCatalog $catalog = null;
    /** @var array<string, Navigation> */
    private array $navigation = [];
    /** @var array<string, list<array{slug: string, path: string, title: string, section: string|null}>> */
    private array $pages = [];
    /** @var array<string, DocPage> */
    private array $rendered = [];
    /** @var array<string, list<string>> */
    private array $problems = [];

    public function __construct(
        private readonly MarkdownRenderer $renderer,
        private readonly string $sources,
        private readonly ?string $nextPath,
        private readonly string $fallbackNavigation,
    ) {}

    public function versions(): VersionCatalog
    {
        return $this->catalog ??= VersionCatalog::load($this->sources, $this->nextPath);
    }

    public function version(string $id): ?DocVersion
    {
        return $this->versions()->get($id);
    }

    /** The site URL of a page, such as /docs/0.3/routing/ ("" is the version's index). */
    public function url(DocVersion $version, string $slug = ''): string
    {
        return '/docs/' . rawurlencode($version->id) . '/' . ($slug === '' ? '' : $slug . '/');
    }

    /** The sidebar, without pages whose file this version does not have. */
    public function navigation(DocVersion $version): Navigation
    {
        if (isset($this->navigation[$version->id])) {
            return $this->navigation[$version->id];
        }
        $markdown = $version->read(self::INDEX) ?? (string) file_get_contents($this->fallbackNavigation);
        $seen = [];
        $navigation = Navigation::parse($markdown)->filter(static function (string $path) use ($version, &$seen): bool {
            $slug = Paths::slug($path);
            if ($slug === null || $slug === '' || isset($seen[$slug]) || !$version->has($path)) {
                return false;
            }
            $seen[$slug] = true;

            return true;
        });

        return $this->navigation[$version->id] = $navigation;
    }

    /**
     * Every page of a version in reading order, starting with the index.
     *
     * @return list<array{slug: string, path: string, title: string, section: string|null}>
     */
    public function pages(DocVersion $version): array
    {
        if (isset($this->pages[$version->id])) {
            return $this->pages[$version->id];
        }
        $pages = [['slug' => '', 'path' => self::INDEX, 'title' => 'Overview', 'section' => null]];
        foreach ($this->navigation($version)->pages() as $page) {
            $pages[] = ['slug' => (string) Paths::slug($page['path']), 'path' => $page['path'], 'title' => $page['title'], 'section' => $page['section']];
        }

        return $this->pages[$version->id] = $pages;
    }

    /** @return array<string, string> repository path => site URL */
    public function urls(DocVersion $version): array
    {
        $urls = [];
        foreach ($this->pages($version) as $page) {
            $urls[$page['path']] = $this->url($version, $page['slug']);
        }

        return $urls;
    }

    public function hasPage(DocVersion $version, string $slug): bool
    {
        foreach ($this->pages($version) as $page) {
            if ($page['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }

    public function page(DocVersion $version, string $slug): ?DocPage
    {
        $key = $version->id . ' ' . $slug;
        if (isset($this->rendered[$key])) {
            return $this->rendered[$key];
        }
        foreach ($this->pages($version) as $page) {
            if ($page['slug'] !== $slug) {
                continue;
            }
            $markdown = $version->read($page['path']) ?? $this->generatedIndex($version);
            $result = $this->renderer->render($markdown, $version, $page['path'], $this->urls($version));
            if ($result->problems !== []) {
                $this->problems["{$version->label()} {$page['path']}"] = $result->problems;
            }
            $title = $result->title ?? $page['title'];
            if ($slug !== '' && str_starts_with($title, 'crystoline/')) {
                // Package READMEs start with the package name; the sidebar's name reads better.
                $title = $page['title'];
            }

            return $this->rendered[$key] = new DocPage($version, $slug, $page['path'], $title, $page['section'], $result->html, $result->headings);
        }

        return null;
    }

    /**
     * Mistakes found while rendering, such as links to files that do not exist.
     *
     * @return array<string, list<string>> "0.3 docs/ROUTING.md" => problems
     */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * The packages of a version, from their composer.json files, in sidebar order.
     *
     * @return list<array{name: string, description: string, directory: string, page: string|null}>
     */
    public function packages(DocVersion $version): array
    {
        $urls = $this->urls($version);
        $order = array_flip(array_keys($urls));
        $packages = [];
        foreach ($version->packageManifests() as $manifest) {
            try {
                $data = json_decode((string) $version->read($manifest), true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }
            if (!is_array($data) || !is_string($data['name'] ?? null)) {
                continue;
            }
            $directory = dirname($manifest);
            $packages[] = [
                'name' => $data['name'],
                'description' => is_string($data['description'] ?? null) ? $data['description'] : '',
                'directory' => $directory,
                'page' => $urls[$directory . '/README.md'] ?? null,
            ];
        }
        // Packages without a page keep their alphabetical order, after the others.
        usort($packages, static fn(array $a, array $b): int => ($order[$a['directory'] . '/README.md'] ?? PHP_INT_MAX) <=> ($order[$b['directory'] . '/README.md'] ?? PHP_INT_MAX));

        return $packages;
    }

    /** The index of a version whose docs/ has no README.md: its sidebar as a list. */
    private function generatedIndex(DocVersion $version): string
    {
        $intro = $version->has('docs/GETTING_STARTED.md')
            ? 'New to XPSR? [Getting started](GETTING_STARTED.md) builds a small API with tests.'
            : '';

        return $this->navigation($version)->toMarkdown("XPSR {$version->label()} documentation", $intro);
    }
}
