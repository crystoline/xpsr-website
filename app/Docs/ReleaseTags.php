<?php

declare(strict_types=1);

namespace App\Docs;

/**
 * Chooses the documented releases from the repository's tags: the latest patch of each
 * minor version, from a first minor version on.
 */
final class ReleaseTags
{
    /**
     * @param iterable<string> $tags such as v0.3.0 and v0.3.1
     * @param string $since the first documented minor version, such as 0.3
     * @param list<string> $ignore tags that are not releases
     * @return array<string, string> minor version => tag, newest first, such as ['0.3' => 'v0.3.1']
     */
    public static function select(iterable $tags, string $since, array $ignore = []): array
    {
        $latest = [];
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (in_array($tag, $ignore, true) || !preg_match('/\Av(\d+)\.(\d+)\.(\d+)\z/', $tag, $match)) {
                continue;
            }
            $minor = $match[1] . '.' . $match[2];
            if (version_compare($minor, $since, '<')) {
                continue;
            }
            if (!isset($latest[$minor]) || version_compare(substr($tag, 1), substr($latest[$minor], 1), '>')) {
                $latest[$minor] = $tag;
            }
        }
        uksort($latest, static fn(string $a, string $b): int => version_compare((string) $b, (string) $a));

        return $latest;
    }
}
