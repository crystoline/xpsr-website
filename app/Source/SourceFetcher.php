<?php

declare(strict_types=1);

namespace App\Source;

use App\Docs\DocVersion;
use App\Docs\ReleaseTags;
use FilesystemIterator;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Brings the documentation sources up to date: a mirror of the framework repository, and for
 * each documented version a directory with its Markdown files and package manifests.
 *
 * The sources directory then holds versions.json, xpsr.git, and one directory per version
 * (such as 0.3 and next). Each version directory lists every file of the repository at that
 * version in .files, so links to source files can be checked without extracting them.
 */
final class SourceFetcher
{
    /** Files a version needs: documentation, root Markdown files, and package READMEs and manifests. */
    private const WANTED = '~\A(?:docs/.+\.md|[^/]+\.md|packages/[^/]+/(?:README\.md|composer\.json))\z~';

    /** @param list<string> $ignoreTags */
    public function __construct(
        private readonly string $repository,
        private readonly string $sources,
        private readonly string $nextBranch,
        private readonly string $since,
        private readonly array $ignoreTags,
    ) {}

    /**
     * @param callable(string): void $log
     * @return list<array{id: string, ref: string, commit: string}>
     */
    public function fetch(callable $log): array
    {
        if (!is_dir($this->sources) && !mkdir($this->sources, 0o777, true) && !is_dir($this->sources)) {
            throw new RuntimeException("Cannot create {$this->sources}.");
        }
        $mirror = $this->sources . '/xpsr.git';
        $git = new Git($mirror);
        if (is_dir($mirror)) {
            $log("Updating {$this->repository}");
            $git->run(['remote', 'set-url', 'origin', $this->repository]);
            $git->run(['remote', 'update', '--prune']);
        } else {
            $log("Cloning {$this->repository}");
            Git::exec(['git', 'clone', '--mirror', '--quiet', $this->repository, $mirror]);
        }

        $refs = ReleaseTags::select($git->lines(['tag', '--list', 'v*']), $this->since, $this->ignoreTags);
        $refs[DocVersion::NEXT] = $this->nextBranch;

        $versions = [];
        foreach ($refs as $id => $ref) {
            $commit = trim($git->run(['rev-parse', '--verify', '--quiet', $ref . '^{commit}']));
            $this->extract($git, (string) $id, $commit);
            $versions[] = ['id' => (string) $id, 'ref' => $ref, 'commit' => $commit];
            $log(sprintf('%-5s %s (%s)', $id, $ref, substr($commit, 0, 12)));
        }

        $this->removeStale(array_column($versions, 'id'));
        $json = json_encode(['versions' => $versions], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($this->sources . '/versions.json', $json . "\n");

        return $versions;
    }

    private function extract(Git $git, string $id, string $commit): void
    {
        // -z separates names with NUL, so names with unusual characters arrive unquoted.
        $files = array_values(array_filter(
            explode("\0", $git->run(['ls-tree', '-r', '--name-only', '-z', $commit])),
            static fn(string $file): bool => $file !== '',
        ));
        $wanted = array_values(array_filter($files, static fn(string $file): bool => preg_match(self::WANTED, $file) === 1));

        $temporary = "{$this->sources}/.{$id}.tmp";
        self::remove($temporary);
        mkdir($temporary, 0o777, true);
        if ($wanted !== []) {
            // Named after the commit: PharData caches archives by file name within a process.
            $archive = "{$this->sources}/.{$id}-{$commit}.tar";
            $git->run(['archive', '--format=tar', '-o', $archive, $commit, '--', ...$wanted]);
            (new PharData($archive))->extractTo($temporary, null, true);
            unlink($archive);
        }
        file_put_contents("{$temporary}/.files", implode("\n", $files) . "\n");

        $target = "{$this->sources}/{$id}";
        self::remove($target);
        rename($temporary, $target);
    }

    /** @param list<string> $keep */
    private function removeStale(array $keep): void
    {
        foreach (glob($this->sources . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);
            if ($name !== 'xpsr.git' && !in_array($name, $keep, true)) {
                self::remove($directory);
            }
        }
    }

    private static function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
