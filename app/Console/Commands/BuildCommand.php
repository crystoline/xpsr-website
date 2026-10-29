<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Docs\DocsLibrary;
use App\Site\Exporter;
use App\Site\LinkChecker;
use App\Source\SourceFetcher;
use Crystoline\Xpsr\Configuration;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BuildCommand extends Command
{
    public function __construct(
        private readonly SourceFetcher $fetcher,
        private readonly Exporter $exporter,
        private readonly LinkChecker $links,
        private readonly DocsLibrary $docs,
        private readonly Configuration $config,
    ) {
        parent::__construct('site:build');
    }

    protected function configure(): void
    {
        $this->setDescription('Build the static site.')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The directory to write the site to (default: build/).')
            ->addOption('no-fetch', null, InputOption::VALUE_NONE, 'Build from the documentation fetched before, without updating it.')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail when a link is broken. Released versions only warn: their tags cannot change.')
            ->setHelp(<<<'HELP'
                Fetches the documentation (see site:fetch), requests every page through the
                application, and writes the responses as static files. Then it checks every link
                and anchor between the pages. Run Pagefind on the result to add search:

                  npx pagefind --site build
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('no-fetch')) {
            $this->fetcher->fetch(static fn(string $line) => $output->writeln($line));
        }
        $directory = $this->directory($input->getOption('output'));
        try {
            $this->exporter->export($directory, static fn(string $line) => $output->writeln("Wrote {$line}"));
        } catch (RuntimeException $exception) {
            $output->writeln("<error>{$exception->getMessage()}</error>");

            return self::FAILURE;
        }

        $releases = [];
        foreach ($this->docs->versions()->all() as $version) {
            if ($version->isRelease()) {
                $releases[] = $version->label();
            }
        }
        $problems = $this->docs->problems();
        foreach ($this->links->check($directory) as $page => $list) {
            $problems[$page] = [...($problems[$page] ?? []), ...$list];
        }

        $counted = 0;
        foreach ($problems as $where => $list) {
            $release = (bool) preg_match('~\A(?:docs/)?(' . implode('|', array_map('preg_quote', $releases ?: ['-'])) . ')[ /]~', $where);
            foreach ($list as $problem) {
                $counted += $release ? 0 : 1;
                $output->writeln(sprintf('<comment>%s</comment>: %s', $where, $problem));
                if (getenv('GITHUB_ACTIONS') === 'true') {
                    // Shown on the workflow run's summary.
                    $output->writeln(sprintf('::warning title=Broken link::%s: %s', $where, $problem), OutputInterface::OUTPUT_RAW);
                }
            }
        }

        $output->writeln(sprintf('<info>The site is in %s.</info>', $directory));
        if ($counted > 0 && $input->getOption('strict')) {
            $output->writeln("<error>{$counted} broken links outside released versions.</error>");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function directory(mixed $option): string
    {
        $directory = is_string($option) && $option !== '' ? $option : (string) $this->config->get('site.build');
        if (!preg_match('~\A(?:/|[A-Za-z]:[\\\\/])~', $directory)) {
            $directory = getcwd() . '/' . $directory;
        }

        return rtrim($directory, '/\\');
    }
}
