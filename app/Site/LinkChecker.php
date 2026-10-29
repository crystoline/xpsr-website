<?php

declare(strict_types=1);

namespace App\Site;

use Dom\HTMLDocument;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Checks every link between the exported pages: that the page exists, and that the
 * anchor after "#" is an ID on it. Links to other sites are not checked.
 */
final class LinkChecker
{
    /** Created after the build by Pagefind. */
    private const SKIP = ['/pagefind/'];

    /** @var array<string, array<string, true>> */
    private array $ids = [];

    /**
     * @return array<string, list<string>> page => problems, such as "docs/0.3/routing/index.html"
     */
    public function check(string $directory): array
    {
        $this->ids = [];
        $problems = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }
            $page = str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            $document = HTMLDocument::createFromString((string) file_get_contents($file->getPathname()), LIBXML_NOERROR);
            foreach ($document->querySelectorAll('a[href], link[href], script[src], img[src]') as $element) {
                $url = (string) ($element->getAttribute('href') ?? $element->getAttribute('src'));
                $problem = $this->problem($directory, $page, $url);
                if ($problem !== null) {
                    $problems[$page][] = $problem;
                }
            }
        }
        ksort($problems);

        return array_map(static fn(array $list): array => array_values(array_unique($list)), $problems);
    }

    private function problem(string $directory, string $page, string $url): ?string
    {
        if ($url === '' || str_starts_with($url, '//') || preg_match('~\A[a-z][a-z0-9+.-]*:~i', $url)) {
            return null;
        }
        $hash = strpos($url, '#');
        $fragment = $hash === false ? '' : rawurldecode(substr($url, $hash + 1));
        $path = (string) strtok($hash === false ? $url : substr($url, 0, $hash), '?');
        if ($path === '') {
            $target = $page;
        } else {
            if (!str_starts_with($path, '/')) {
                $path = '/' . ltrim(dirname('/' . $page), '/') . '/' . $path;
            }
            foreach (self::SKIP as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return null;
                }
            }
            $target = ltrim(rawurldecode($path), '/');
            if ($target === '' || str_ends_with($target, '/')) {
                $target .= 'index.html';
            }
            if (!is_file($directory . '/' . $target)) {
                return is_dir($directory . '/' . $target) ? "{$url}: add a trailing slash" : "{$url}: no such page";
            }
        }
        if ($fragment !== '' && str_ends_with($target, '.html') && !isset($this->ids($directory, $target)[$fragment])) {
            return "{$url}: no #{$fragment} on that page";
        }

        return null;
    }

    /** @return array<string, true> */
    private function ids(string $directory, string $page): array
    {
        if (!isset($this->ids[$page])) {
            $this->ids[$page] = [];
            $document = HTMLDocument::createFromString((string) file_get_contents($directory . '/' . $page), LIBXML_NOERROR);
            foreach ($document->querySelectorAll('[id]') as $element) {
                $this->ids[$page][(string) $element->getAttribute('id')] = true;
            }
        }

        return $this->ids[$page];
    }
}
