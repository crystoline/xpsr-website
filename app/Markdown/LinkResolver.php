<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Docs\Paths;

/**
 * Turns the relative links in the repository's Markdown into site links.
 *
 * A link to a file that is a page here becomes that page's URL, so ROUTING.md#named-urls
 * becomes /docs/0.3/routing/#named-urls. Any other repository file or directory links to
 * GitHub at the same tag or branch. Absolute URLs and same-page anchors are left alone.
 */
final class LinkResolver
{
    private readonly string $raw;

    /** @param string $github the repository on GitHub, such as https://github.com/crystoline/xpsr */
    public function __construct(private readonly string $github)
    {
        $this->raw = (string) preg_replace('~\Ahttps://github\.com/~', 'https://raw.githubusercontent.com/', rtrim($github, '/'));
    }

    public function link(string $url, RenderContext $context): string
    {
        $target = $this->target($url, $context);
        if ($target === null) {
            return $url;
        }
        [$path, $suffix, $directory] = $target;
        if (!$directory && isset($context->pages[$path])) {
            return $context->pages[$path] . self::fragment($suffix);
        }
        $ref = $context->version->ref;
        if ($directory || $context->version->hasDirectory($path)) {
            if (!$context->version->hasDirectory($path)) {
                $context->problems[] = "links to {$url}, a directory that does not exist";
            }

            return rtrim("{$this->github}/tree/{$ref}/{$path}", '/') . $suffix;
        }
        if (!$context->version->has($path)) {
            $context->problems[] = "links to {$url}, a file that does not exist";
        }

        return "{$this->github}/blob/{$ref}/{$path}{$suffix}";
    }

    /** Images load from GitHub's raw file host at the same tag or branch. */
    public function image(string $url, RenderContext $context): string
    {
        $target = $this->target($url, $context);
        if ($target === null) {
            return $url;
        }
        if (!$context->version->has($target[0])) {
            $context->problems[] = "shows the image {$url}, which does not exist";
        }

        return "{$this->raw}/{$context->version->ref}/{$target[0]}";
    }

    /**
     * The repository path a relative URL points to, what followed the path (query and
     * fragment), and whether it named a directory. Null for URLs that are not relative paths.
     *
     * @return array{string, string, bool}|null
     */
    private function target(string $url, RenderContext $context): ?array
    {
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '//') || preg_match('~\A[a-z][a-z0-9+.-]*:~i', $url)) {
            return null;
        }
        $position = strcspn($url, '?#');
        $path = rawurldecode(substr($url, 0, $position));
        $resolved = Paths::resolve(dirname($context->path), $path);
        if ($resolved === null) {
            $context->problems[] = "links to {$url}, which is outside the repository";

            return null;
        }

        return [$resolved, substr($url, $position), $path === '' || str_ends_with($path, '/')];
    }

    /** Keeps the fragment of "?query#fragment": site pages take no query. */
    private static function fragment(string $suffix): string
    {
        $hash = strpos($suffix, '#');

        return $hash === false ? '' : substr($suffix, $hash);
    }
}
