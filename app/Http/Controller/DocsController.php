<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Docs\DocPage;
use App\Docs\DocsLibrary;
use App\Docs\DocVersion;
use App\Docs\Paths;
use App\Site\SiteContext;
use Crystoline\Xpsr\Configuration;
use Crystoline\Xpsr\Http\Exception\NotFoundHttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DocsController
{
    public function __construct(
        private readonly DocsLibrary $docs,
        private readonly SiteContext $site,
        private readonly Configuration $config,
    ) {}

    /** /docs/ leads to the latest release's documentation. */
    public function latest(ServerRequestInterface $request): ResponseInterface
    {
        $latest = $this->docs->versions()->latest() ?? throw new NotFoundHttpException();

        return view('docs.redirect', $this->site->with([
            'title' => 'Documentation',
            'path' => '/docs/',
            'target' => $this->docs->url($latest),
        ]));
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return $this->show($request, '');
    }

    public function page(ServerRequestInterface $request): ResponseInterface
    {
        return $this->show($request, (string) $request->getAttribute('page'));
    }

    public function package(ServerRequestInterface $request): ResponseInterface
    {
        return $this->show($request, 'packages/' . $request->getAttribute('package'));
    }

    private function show(ServerRequestInterface $request, string $slug): ResponseInterface
    {
        $version = $this->docs->version((string) $request->getAttribute('version')) ?? throw new NotFoundHttpException();
        $page = $this->docs->page($version, $slug) ?? throw new NotFoundHttpException();
        $latest = $this->docs->versions()->latest();
        $canonical = $latest !== null && $this->docs->hasPage($latest, $slug) ? $latest : $version;

        return view('docs.page', $this->site->with([
            'title' => $page->title,
            'path' => $this->docs->url($version, $slug),
            'canonical' => $this->site->baseUrl() . $this->docs->url($canonical, $slug),
            'page' => $page,
            'version' => ['id' => $version->id, 'label' => $version->label(), 'release' => $version->isRelease()],
            'sections' => $this->sidebar($version, $slug),
            'versions' => $this->versionMenu($slug, $version, $latest),
            'banner' => $this->banner($slug, $version, $latest),
            'pager' => $this->pager($version, $slug),
            'source' => $this->sourceLink($page),
        ], $version));
    }

    /** @return list<array{title: string, active: bool, items: list<array{title: string, url: string, active: bool}>}> */
    private function sidebar(DocVersion $version, string $slug): array
    {
        $sections = [];
        foreach ($this->docs->navigation($version)->sections as $section) {
            $items = [];
            $active = false;
            foreach ($section['items'] as $item) {
                $itemSlug = (string) Paths::slug($item['path']);
                $items[] = ['title' => $item['title'], 'url' => $this->docs->url($version, $itemSlug), 'active' => $itemSlug === $slug];
                $active = $active || $itemSlug === $slug;
            }
            $sections[] = ['title' => $section['title'], 'active' => $active, 'items' => $items];
        }

        return $sections;
    }

    /**
     * Every version, each linking to this page if it has one, or else to its index.
     *
     * @return list<array{label: string, url: string, current: bool, latest: bool}>
     */
    private function versionMenu(string $slug, DocVersion $current, ?DocVersion $latest): array
    {
        $menu = [];
        foreach ($this->docs->versions()->all() as $version) {
            $menu[] = [
                'label' => $version->isRelease() ? $version->label() : 'next (master)',
                'url' => $this->docs->url($version, $this->docs->hasPage($version, $slug) ? $slug : ''),
                'current' => $version->id === $current->id,
                'latest' => $version->id === $latest?->id,
            ];
        }

        return $menu;
    }

    /** @return array{kind: string, latest: string, url: string}|null */
    private function banner(string $slug, DocVersion $version, ?DocVersion $latest): ?array
    {
        if ($latest === null || $latest->id === $version->id || !$latest->isRelease()) {
            return null;
        }

        return [
            'kind' => $version->isRelease() ? 'old' : 'next',
            'latest' => $latest->label(),
            'url' => $this->docs->url($latest, $this->docs->hasPage($latest, $slug) ? $slug : ''),
        ];
    }

    /** @return array{previous: array{title: string, url: string}|null, next: array{title: string, url: string}|null} */
    private function pager(DocVersion $version, string $slug): array
    {
        $pages = $this->docs->pages($version);
        $position = array_search($slug, array_column($pages, 'slug'), true);
        $link = fn(?array $page): ?array => $page === null ? null : ['title' => $page['title'], 'url' => $this->docs->url($version, $page['slug'])];

        return [
            'previous' => $link(is_int($position) && $position > 0 ? $pages[$position - 1] : null),
            'next' => $link(is_int($position) ? $pages[$position + 1] ?? null : null),
        ];
    }

    /** @return array{label: string, url: string} */
    private function sourceLink(DocPage $page): array
    {
        $github = rtrim((string) $this->config->get('site.github'), '/');
        $version = $page->version;
        if (!$version->has($page->path)) {
            // A generated index: there is no file to show.
            return ['label' => 'Browse these docs on GitHub', 'url' => "{$github}/tree/{$version->ref}/docs"];
        }
        if ($version->isRelease()) {
            return ['label' => 'View this page on GitHub', 'url' => "{$github}/blob/{$version->ref}/{$page->path}"];
        }

        return ['label' => 'Edit this page on GitHub', 'url' => "{$github}/edit/{$version->ref}/{$page->path}"];
    }
}
