<?php

declare(strict_types=1);

namespace App\Docs;

use JsonException;
use RuntimeException;

/**
 * The documentation versions that site:fetch extracted, newest release first and "next" last.
 */
final class VersionCatalog
{
    /** @param list<DocVersion> $versions */
    public function __construct(private readonly array $versions) {}

    /**
     * Reads versions.json from the sources directory. With $nextPath, "next" is read from that
     * checkout instead of the extracted master branch.
     */
    public static function load(string $sources, ?string $nextPath = null): self
    {
        $entries = [];
        $file = $sources . '/versions.json';
        if (is_file($file)) {
            try {
                $data = json_decode((string) file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException("{$file} is not valid JSON. Run php xpsr site:fetch again.", 0, $exception);
            }
            $entries = is_array($data) && is_array($data['versions'] ?? null) ? $data['versions'] : [];
        } elseif ($nextPath === null) {
            throw new RuntimeException('There are no documentation sources yet. Run php xpsr site:fetch.');
        }

        $versions = [];
        $next = null;
        foreach ($entries as $entry) {
            if (!is_array($entry) || !is_string($entry['id'] ?? null) || !is_string($entry['ref'] ?? null)) {
                continue;
            }
            $version = new DocVersion($entry['id'], $entry['ref'], (string) ($entry['commit'] ?? ''), $sources . '/' . $entry['id']);
            if ($version->isRelease()) {
                $versions[] = $version;
            } else {
                $next = $version;
            }
        }
        usort($versions, static fn(DocVersion $a, DocVersion $b): int => version_compare($b->id, $a->id));

        if ($nextPath !== null) {
            $root = realpath($nextPath);
            if ($root === false || !is_dir($root . '/docs')) {
                throw new RuntimeException("DOCS_NEXT_PATH must be a checkout of the framework repository; {$nextPath} has no docs directory.");
            }
            $next = new DocVersion(DocVersion::NEXT, $next->ref ?? 'master', 'working tree', $root, workingTree: true);
        }
        if ($next !== null) {
            $versions[] = $next;
        }

        return new self($versions);
    }

    /** @return list<DocVersion> */
    public function all(): array
    {
        return $this->versions;
    }

    public function get(string $id): ?DocVersion
    {
        foreach ($this->versions as $version) {
            if ($version->id === $id) {
                return $version;
            }
        }

        return null;
    }

    /** The newest release, or "next" before the first release. */
    public function latest(): ?DocVersion
    {
        return $this->versions[0] ?? null;
    }

    public function next(): ?DocVersion
    {
        return $this->get(DocVersion::NEXT);
    }
}
