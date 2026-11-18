<?php

namespace App\Services;

use App\Exceptions\ProcessException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessRunner
{
    /**
     * Jalankan proses dengan argumen array (tanpa shell) dan kembalikan stdout.
     *
     * @param  list<string>  $command
     */
    public function run(array $command, int $timeout = 120, ?string $workingDirectory = null): string
    {
        $process = new Process($command, $workingDirectory, null, null, $timeout > 0 ? $timeout : null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessException(
                $this->failureMessage($command, $process->getExitCode(), $process->getErrorOutput()),
            );
        }

        return $process->getOutput();
    }

    /**
     * Jalankan proses sambil mengalirkan output ke callback (untuk progres unduhan).
     *
     * @param  list<string>  $command
     * @param  callable(string, string): void  $onOutput
     */
    public function runStreaming(array $command, int $timeout, callable $onOutput, ?string $workingDirectory = null): string
    {
        $process = new Process($command, $workingDirectory, null, null, $timeout > 0 ? $timeout : null);
        $buffer = '';

        $process->run(function (string $type, string $chunk) use (&$buffer, $onOutput): void {
            $buffer .= $chunk;
            $onOutput($type, $chunk);
        });

        if (! $process->isSuccessful()) {
            throw new ProcessException(
                $this->failureMessage($command, $process->getExitCode(), $process->getErrorOutput()),
            );
        }

        return $buffer;
    }

    public function isAvailable(string $binary): bool
    {
        $path = is_file($binary) ? $binary : (new ExecutableFinder)->find($binary);

        if ($path === null) {
            return false;
        }

        try {
            $this->run([$path, '--version'], 20);

            return true;
        } catch (ProcessException) {
            try {
                $this->run([$path, '-version'], 20);

                return true;
            } catch (ProcessException) {
                return false;
            }
        }
    }

    public function resolve(string $binary): string
    {
        if (is_file($binary)) {
            return $binary;
        }

        return (new ExecutableFinder)->find($binary) ?? $binary;
    }

    /**
     * @param  list<string>  $command
     */
    private function failureMessage(array $command, ?int $exitCode, string $errorOutput): string
    {
        $stderr = trim($errorOutput);
        $stderr = $stderr === '' ? '(tanpa detail)' : mb_substr($stderr, -1200);
        $binary = basename($command[0] ?? 'proses');

        return sprintf('Proses %s gagal (exit code %s): %s', $binary, $exitCode ?? '?', $stderr);
    }
}
