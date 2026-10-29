<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Docs\DocsLibrary;
use App\Markdown\Highlighter;
use App\Site\SiteContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HomeController
{
    /** Guides the home page links to, by their slug. */
    private const GUIDES = [
        'getting-started', 'deployment', 'routing', 'http', 'views', 'sessions', 'auth', 'console',
        'container', 'events', 'packages', 'versioning', 'changelog', 'packages/queue',
    ];

    public function __construct(
        private readonly DocsLibrary $docs,
        private readonly SiteContext $site,
        private readonly Highlighter $highlighter,
        private readonly string $samples,
    ) {}

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $latest = $this->docs->versions()->latest();
        $next = $this->docs->versions()->next();
        $released = $latest !== null && $latest->isRelease();

        // Each guide links to the latest release's page, or to next's while only next has it.
        $guides = [];
        foreach (self::GUIDES as $slug) {
            if ($latest !== null && $this->docs->hasPage($latest, $slug)) {
                $guides[$slug] = ['url' => $this->docs->url($latest, $slug), 'unreleased' => false];
            } elseif ($next !== null && $this->docs->hasPage($next, $slug)) {
                $guides[$slug] = ['url' => $this->docs->url($next, $slug), 'unreleased' => $released];
            }
        }

        // The packages on master, marked while no release has them yet.
        $inRelease = [];
        if ($released) {
            foreach ($this->docs->packages($latest) as $package) {
                $inRelease[$package['name']] = $package;
            }
        }
        $packages = [];
        foreach (($next ?? $latest) !== null ? $this->docs->packages($next ?? $latest) : [] as $package) {
            $release = $inRelease[$package['name']] ?? null;
            $packages[] = [
                'name' => $package['name'],
                'description' => $package['description'],
                'url' => $release['page'] ?? $package['page'],
                'unreleased' => $released && $release === null,
            ];
        }

        return view('home', $this->site->with([
            'title' => null,
            'path' => '/',
            'canonical' => $this->site->baseUrl() . '/',
            'latest' => $latest?->label(),
            'guides' => $guides,
            'packages' => $packages,
            'samples' => [
                'routes' => $this->sample('routes.php'),
                'controller' => $this->sample('TaskController.php'),
                'test' => $this->sample('TaskTest.php'),
            ],
        ]));
    }

    private function sample(string $file): string
    {
        return $this->highlighter->block(rtrim((string) file_get_contents($this->samples . '/' . $file)), 'php');
    }
}
