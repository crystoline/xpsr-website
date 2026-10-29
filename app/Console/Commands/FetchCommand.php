<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Source\SourceFetcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class FetchCommand extends Command
{
    public function __construct(private readonly SourceFetcher $fetcher)
    {
        parent::__construct('site:fetch');
    }

    protected function configure(): void
    {
        $this->setDescription('Fetch the documentation of every version from the framework repository.')
            ->setHelp(<<<'HELP'
                Mirrors the framework repository and extracts the documentation of each release
                (the latest patch of each minor version) and of the development branch, which
                the site shows as "next". The pages read these files on every request.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $versions = $this->fetcher->fetch(static fn(string $line) => $output->writeln($line));
        $output->writeln(sprintf('<info>%d documentation versions are ready.</info>', count($versions)));

        return self::SUCCESS;
    }
}
