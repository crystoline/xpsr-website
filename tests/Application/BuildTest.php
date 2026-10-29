<?php

declare(strict_types=1);

namespace Tests\Application;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

final class BuildTest extends TestCase
{
    private string $output;

    protected function setUp(): void
    {
        $this->output = sys_get_temp_dir() . '/xpsr-website-tests/' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->output)) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->output, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->output);
    }

    public function testTheSiteIsWrittenAsStaticFiles(): void
    {
        [$status, $output] = $this->console('site:build', ['--no-fetch' => true, '--output' => $this->output]);

        self::assertSame(0, $status, $output);
        foreach (['index.html', 'docs/index.html', 'docs/0.3/index.html', 'docs/0.3/routing/index.html', 'docs/next/packages/mail/index.html', '404.html', 'sitemap.xml', 'robots.txt', 'assets/site.css', 'assets/site.js', 'favicon.svg'] as $file) {
            self::assertFileExists($this->output . '/' . $file);
        }
        self::assertFileDoesNotExist($this->output . '/index.php');
        self::assertSame("xpsr.test\n", file_get_contents($this->output . '/CNAME'));
        self::assertStringContainsString('This page does not exist', (string) file_get_contents($this->output . '/404.html'));
    }

    public function testBrokenLinksAndAnchorsAreReported(): void
    {
        [$status, $output] = $this->console('site:build', ['--no-fetch' => true, '--output' => $this->output]);

        self::assertSame(0, $status);
        self::assertStringContainsString('next docs/ROUTING.md: links to NOWHERE.md, a file that does not exist', $output);
        // The index is docs/README.md itself, which lists the page whose file is missing.
        self::assertStringContainsString('next docs/README.md: links to MISSING.md, a file that does not exist', $output);
        self::assertStringContainsString('docs/next/routing/index.html: /docs/next/getting-started/#nope: no #nope on that page', $output);
        self::assertStringNotContainsString('#create-a-project: no', $output);
    }

    public function testStrictBuildsFailOnBrokenLinks(): void
    {
        [$status, $output] = $this->console('site:build', ['--no-fetch' => true, '--strict' => true, '--output' => $this->output]);

        self::assertSame(1, $status);
        self::assertStringContainsString('3 broken links outside released versions.', $output);
    }

    public function testADirectoryTheBuildDidNotWriteIsLeftAlone(): void
    {
        mkdir($this->output, 0o777, true);
        file_put_contents($this->output . '/important.txt', 'keep');

        [$status, $output] = $this->console('site:build', ['--no-fetch' => true, '--output' => $this->output]);

        self::assertSame(1, $status);
        self::assertStringContainsString('was not written by site:build', $output);
        self::assertFileExists($this->output . '/important.txt');
    }

    public function testABuildReplacesTheLastOne(): void
    {
        $this->console('site:build', ['--no-fetch' => true, '--output' => $this->output]);
        file_put_contents($this->output . '/stale.html', 'old');

        [$status] = $this->console('site:build', ['--no-fetch' => true, '--output' => $this->output]);

        self::assertSame(0, $status);
        self::assertFileDoesNotExist($this->output . '/stale.html');
    }
}
