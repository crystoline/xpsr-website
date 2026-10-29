<?php

declare(strict_types=1);

namespace App\Source;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Runs git commands against one repository directory. */
final class Git
{
    public function __construct(private readonly string $gitDir) {}

    /**
     * @param list<string> $arguments
     * @return string the command's standard output
     */
    public function run(array $arguments, ?string $workingDirectory = null): string
    {
        return self::exec(['git', '--git-dir=' . $this->gitDir, ...$arguments], $workingDirectory);
    }

    /**
     * @param list<string> $arguments
     * @return list<string>
     */
    public function lines(array $arguments): array
    {
        return array_values(array_filter(explode("\n", $this->run($arguments)), static fn(string $line): bool => $line !== ''));
    }

    /**
     * @param list<string> $command
     */
    public static function exec(array $command, ?string $workingDirectory = null): string
    {
        $process = new Process($command, $workingDirectory, ['GIT_TERMINAL_PROMPT' => '0'], null, 600);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException(sprintf('%s failed: %s', implode(' ', array_slice($command, 0, 4)), trim($process->getErrorOutput() ?: $process->getOutput())));
        }

        return $process->getOutput();
    }
}
