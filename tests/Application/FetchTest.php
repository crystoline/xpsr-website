<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Docs\VersionCatalog;
use App\Source\Git;
use App\Source\SourceFetcher;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** Fetching from a real (local) git repository. */
final class FetchTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/xpsr-website-fetch-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/repository/docs', 0o777, true);
        mkdir($this->directory . '/repository/packages/cache/src', 0o777, true);
        $this->git('init', '--quiet', '--initial-branch=master');
        $this->commit([
            'docs/ROUTING.md' => "# Routing\n",
            'packages/cache/README.md' => "# Cache\n",
            'packages/cache/composer.json' => "{\"name\": \"crystoline/xpsr-cache\"}\n",
            'packages/cache/src/Cache.php' => "<?php\n",
            'CHANGELOG.md' => "# Changelog\n",
        ]);
        $this->git('tag', 'v0.2.0');
        $this->git('tag', 'v0.3.0');
        $this->git('tag', 'v1.0.0');
        $this->commit(['docs/VIEWS.md' => "# Views\n"]);
    }

    protected function tearDown(): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->directory);
    }

    public function testEachVersionGetsItsDocumentation(): void
    {
        $versions = $this->fetcher()->fetch(static fn() => null);

        self::assertSame(['0.3', 'next'], array_column($versions, 'id'));
        self::assertSame(['v0.3.0', 'master'], array_column($versions, 'ref'));

        $catalog = VersionCatalog::load($this->directory . '/sources');
        $release = $catalog->get('0.3');
        self::assertNotNull($release);
        self::assertSame("# Routing\n", $release->read('docs/ROUTING.md'));
        self::assertNull($release->read('docs/VIEWS.md'));
        self::assertNull($release->read('packages/cache/src/Cache.php'), 'Source files are not extracted');
        self::assertTrue($release->has('packages/cache/src/Cache.php'), 'but they are listed.');
        self::assertTrue($release->hasDirectory('packages/cache'));
        self::assertSame(['packages/cache/composer.json'], $release->packageManifests());
        self::assertSame("# Views\n", $catalog->next()?->read('docs/VIEWS.md'));
    }

    public function testFetchingAgainPicksUpNewReleasesAndRemovesOldVersions(): void
    {
        $this->fetcher()->fetch(static fn() => null);
        $this->git('tag', 'v0.3.1');
        $this->git('tag', 'v0.4.0');
        $lines = [];

        $versions = $this->fetcher('0.4')->fetch(static function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        self::assertSame(['0.4', 'next'], array_column($versions, 'id'));
        self::assertDirectoryDoesNotExist($this->directory . '/sources/0.3');
        self::assertStringStartsWith('Updating ', $lines[0]);
        self::assertSame("# Views\n", VersionCatalog::load($this->directory . '/sources')->get('0.4')?->read('docs/VIEWS.md'));
    }

    private function fetcher(string $since = '0.3'): SourceFetcher
    {
        return new SourceFetcher($this->directory . '/repository', $this->directory . '/sources', 'master', $since, ['v1.0.0']);
    }

    /** @param array<string, string> $files */
    private function commit(array $files): void
    {
        foreach ($files as $path => $contents) {
            file_put_contents($this->directory . '/repository/' . $path, $contents);
        }
        $this->git('add', '--all');
        $this->git('-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '-m', 'Change');
    }

    private function git(string ...$arguments): void
    {
        Git::exec(['git', '-C', $this->directory . '/repository', ...$arguments]);
    }
}
