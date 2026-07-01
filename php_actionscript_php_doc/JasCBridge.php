<?php

declare(strict_types=1);

namespace Jah;

use Fiber;
use RuntimeException;

/**
 * Bridge for executing external C binaries asynchronously using JASB bytecode and Fibers.
 */
final class JasCBridge
{
    /**
     * Executes a binary, sends payload via STDIN, and returns the exit code decoded from JASB bytecode.
     */
    public static function callBinary(string $binaryPath, string $payload): int
    {
        // We don't check is_executable because $binaryPath might contain arguments (e.g. "php script.php")
        $descriptorspec = [
            0 => ["pipe", "r"], // STDIN
            1 => ["pipe", "w"], // STDOUT
            2 => ["pipe", "w"]  // STDERR
        ];

        $process = proc_open($binaryPath, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException("Cannot start process: {$binaryPath}");
        }

        // Send payload via STDIN
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);

        // Configure STDOUT for non-blocking read
        stream_set_blocking($pipes[1], false);

        $output = '';
        while (!feof($pipes[1])) {
            JasEventLoop::watch($pipes[1], Fiber::getCurrent());

            // Suspend Fiber until the stream becomes readable
            Fiber::suspend();

            $chunk = fread($pipes[1], 8192);
            if ($chunk !== false) {
                $output .= $chunk;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if ($output === '') {
            throw new RuntimeException("No output received from binary: {$binaryPath}");
        }

        // Decode the returned JASB bytecode to an exit code
        return JasBinaryCompiler::execute($output);
    }
}
