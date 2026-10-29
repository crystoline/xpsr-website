<?php

declare(strict_types=1);

namespace App\Docs;

/**
 * Repository paths: resolving relative links, and the site address of each kind of page.
 */
final class Paths
{
    /**
     * Resolves $relative against the directory $base, both inside the repository.
     * Returns null when the result would leave the repository.
     */
    public static function resolve(string $base, string $relative): ?string
    {
        $path = str_starts_with($relative, '/') ? $relative : ($base === '' || $base === '.' ? '' : $base . '/') . $relative;
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    return null;
                }
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * The page slug of a repository file, or null if such a file cannot be a page:
     * docs/README.md is the index (""), docs/ROUTING.md is "routing", packages/cache/README.md
     * is "packages/cache", and UPGRADE.md at the root is "upgrade".
     */
    public static function slug(string $path): ?string
    {
        if ($path === 'docs/README.md') {
            return '';
        }
        if (preg_match('~\Apackages/([a-z0-9][a-z0-9-]*)/README\.md\z~', $path, $match)) {
            return 'packages/' . $match[1];
        }
        if (preg_match('~\A(?:docs/)?([A-Za-z0-9][A-Za-z0-9_-]*)\.md\z~', $path, $match)) {
            return strtolower(str_replace('_', '-', $match[1]));
        }

        return null;
    }

    /**
     * A relative link from docs/README.md to a repository file, as the navigation file writes it.
     */
    public static function fromDocs(string $path): string
    {
        return str_starts_with($path, 'docs/') ? substr($path, 5) : '../' . $path;
    }
}
