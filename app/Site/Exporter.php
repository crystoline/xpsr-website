<?php

declare(strict_types=1);

namespace App\Site;

use App\Docs\DocsLibrary;
use FilesystemIterator;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Exports the site as static files: every page is requested through the HTTP kernel, as a
 * browser would request it, and its response written where a static host will look for it.
 */
final class Exporter
{
    /** Written into the output directory, so a later build knows it may empty that directory. */
    private const MARKER = '.xpsr-site-build';

    public function __construct(
        private readonly RequestHandlerInterface $kernel,
        private readonly ServerRequestFactoryInterface $requests,
        private readonly DocsLibrary $docs,
        private readonly SiteContext $site,
        private readonly string $publicDir,
    ) {}

    /**
     * Every address of the site and the status it must answer with.
     *
     * @return list<array{string, int}>
     */
    public function urls(): array
    {
        $urls = [['/', 200], ['/docs/', 200]];
        foreach ($this->docs->versions()->all() as $version) {
            foreach ($this->docs->pages($version) as $page) {
                $urls[] = [$this->docs->url($version, $page['slug']), 200];
            }
        }
        // Static hosts such as GitHub Pages answer unknown addresses with /404.html.
        array_push($urls, ['/sitemap.xml', 200], ['/robots.txt', 200], ['/404.html', 404]);

        return $urls;
    }

    /**
     * @param callable(string): void $log
     * @return int the number of pages written
     */
    public function export(string $output, callable $log): int
    {
        $this->prepare($output);
        $count = 0;
        foreach ($this->urls() as [$path, $status]) {
            $response = $this->kernel->handle($this->requests->createServerRequest('GET', $this->site->baseUrl() . $path));
            if ($response->getStatusCode() !== $status) {
                throw new RuntimeException("{$path} answered {$response->getStatusCode()} instead of {$status}.");
            }
            $this->write($output . (str_ends_with($path, '/') ? $path . 'index.html' : $path), (string) $response->getBody());
            $count++;
        }
        $log("{$count} pages");

        $assets = 0;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->publicDir, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($this->publicDir));
            $relative = str_replace('\\', '/', $relative);
            if (in_array($relative, ['/index.php', '/.htaccess'], true) || preg_match('~/\.~', $relative)) {
                continue;
            }
            $this->write($output . $relative, (string) file_get_contents($file->getPathname()));
            $assets++;
        }
        $log("{$assets} public files");

        $host = parse_url($this->site->baseUrl(), PHP_URL_HOST);
        if (is_string($host) && !in_array($host, ['localhost', '127.0.0.1'], true)) {
            // The custom domain, for hosts such as GitHub Pages that read it from the site.
            $this->write($output . '/CNAME', $host . "\n");
        }

        return $count;
    }

    /** Creates the output directory, or empties one that an earlier build wrote. */
    private function prepare(string $output): void
    {
        if (is_dir($output)) {
            $entries = array_diff(scandir($output) ?: [], ['.', '..']);
            if ($entries !== [] && !is_file($output . '/' . self::MARKER)) {
                throw new RuntimeException("{$output} is not empty and was not written by site:build, so it is left alone. Choose another --output.");
            }
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($output, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            /** @var SplFileInfo $item */
            foreach ($items as $item) {
                $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
        }
        $this->write($output . '/' . self::MARKER, "Written by php xpsr site:build. The next build replaces everything here.\n");
    }

    private function write(string $file, string $contents): void
    {
        $directory = dirname($file);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException("Cannot create {$directory}.");
        }
        if (file_put_contents($file, $contents) === false) {
            throw new RuntimeException("Cannot write {$file}.");
        }
    }
}
