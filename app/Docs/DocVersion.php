<?php

declare(strict_types=1);

namespace App\Docs;

/**
 * One version of the documentation: a release such as 0.3, or "next" (the development
 * branch), with the repository files it was extracted from.
 */
final class DocVersion
{
    public const NEXT = 'next';

    /** @var array<string, true>|null */
    private ?array $files = null;
    /** @var array<string, true>|null */
    private ?array $directories = null;

    /**
     * @param string $id "0.3", or "next" for the development branch
     * @param string $ref the tag or branch on GitHub, such as v0.3.0 or master
     * @param string $root the directory holding this version's files
     * @param bool $workingTree true when $root is a checkout, so every file is there; otherwise
     *                          $root holds only the documentation and lists the rest in .files
     */
    public function __construct(
        public readonly string $id,
        public readonly string $ref,
        public readonly string $commit,
        public readonly string $root,
        public readonly bool $workingTree = false,
    ) {}

    public function isRelease(): bool
    {
        return $this->id !== self::NEXT;
    }

    public function label(): string
    {
        return $this->isRelease() ? $this->id : 'next';
    }

    /** Whether the repository has this file at this version. */
    public function has(string $path): bool
    {
        if ($this->workingTree) {
            return $path !== '' && is_file($this->root . '/' . $path);
        }
        $this->loadFiles();

        return isset($this->files[$path]);
    }

    public function hasDirectory(string $path): bool
    {
        if ($this->workingTree) {
            return $path === '' || is_dir($this->root . '/' . $path);
        }
        $this->loadFiles();

        return $path === '' || isset($this->directories[$path]);
    }

    public function read(string $path): ?string
    {
        $file = $this->root . '/' . $path;
        if (!is_file($file)) {
            return null;
        }
        $contents = file_get_contents($file);

        return $contents === false ? null : $contents;
    }

    /**
     * The package manifests at this version, such as packages/cache/composer.json.
     *
     * @return list<string>
     */
    public function packageManifests(): array
    {
        $manifests = [];
        foreach (glob($this->root . '/packages/*/composer.json') ?: [] as $file) {
            $manifests[] = 'packages/' . basename(dirname($file)) . '/composer.json';
        }
        sort($manifests);

        return $manifests;
    }

    private function loadFiles(): void
    {
        if ($this->files !== null) {
            return;
        }
        $this->files = [];
        $this->directories = [];
        $list = is_file($this->root . '/.files') ? file($this->root . '/.files', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;
        foreach ($list ?: [] as $path) {
            $this->files[$path] = true;
            for ($directory = dirname($path); $directory !== '.' && !isset($this->directories[$directory]); $directory = dirname($directory)) {
                $this->directories[$directory] = true;
            }
        }
    }
}
