<?php

declare(strict_types=1);

namespace App\Site;

use App\Docs\DocsLibrary;
use App\Docs\DocVersion;
use Composer\InstalledVersions;
use Crystoline\Xpsr\Configuration;

/**
 * The data every page template gets: the site's name and links, asset URLs, and the
 * documentation version that the header links and search use.
 */
final class SiteContext
{
    /** @var array<string, string> */
    private array $assets = [];

    public function __construct(
        private readonly Configuration $config,
        private readonly DocsLibrary $docs,
        private readonly string $publicDir,
    ) {}

    /** The site's address without a trailing slash, such as https://xpsr.crysto.name.ng. */
    public function baseUrl(): string
    {
        return rtrim((string) $this->config->get('app.url'), '/');
    }

    /**
     * @param array<string, mixed> $data the page's own data, which wins over the shared data
     * @return array<string, mixed>
     */
    public function with(array $data, ?DocVersion $version = null): array
    {
        $latest = $this->docs->versions()->latest();
        $version ??= $latest;

        return [
            'site' => [
                'name' => (string) $this->config->get('site.name', 'XPSR'),
                'description' => (string) $this->config->get('site.description', ''),
                'url' => $this->baseUrl(),
                'github' => (string) $this->config->get('site.github'),
                'website_github' => (string) $this->config->get('site.website_github'),
                'packagist' => (string) $this->config->get('site.packagist'),
                'framework_version' => self::frameworkVersion(),
                'year' => date('Y'),
            ],
            'assets' => [
                'css' => $this->asset('assets/site.css'),
                'js' => $this->asset('assets/site.js'),
                'icon' => $this->asset('favicon.svg'),
            ],
            'docs_home' => $latest !== null ? $this->docs->url($latest) : '/docs/',
            'tutorial' => $this->latestPage('getting-started'),
            'changelog' => $this->latestPage('changelog'),
            'footer_guides' => array_filter([
                'Getting started' => $this->latestPage('getting-started'),
                'Routing' => $this->latestPage('routing'),
                'Deployment' => $this->latestPage('deployment'),
            ]),
            'search_version' => $version?->id,
            'path' => '/',
            ...$data,
        ];
    }

    /** The URL of a page in the latest release, or null if it has no such page. */
    private function latestPage(string $slug): ?string
    {
        $latest = $this->docs->versions()->latest();

        return $latest !== null && $this->docs->hasPage($latest, $slug) ? $this->docs->url($latest, $slug) : null;
    }

    /** A public file's URL with a hash of its contents, so browsers fetch it again when it changes. */
    public function asset(string $path): string
    {
        if (!isset($this->assets[$path])) {
            $file = $this->publicDir . '/' . $path;
            $hash = is_file($file) ? substr((string) md5_file($file), 0, 10) : '';
            $this->assets[$path] = '/' . $path . ($hash === '' ? '' : '?v=' . $hash);
        }

        return $this->assets[$path];
    }

    public static function frameworkVersion(): string
    {
        $version = InstalledVersions::getPrettyVersion('crystoline/xpsr-framework') ?? '';

        return ltrim($version, 'v');
    }
}
