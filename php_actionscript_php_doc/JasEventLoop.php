<?php

declare(strict_types=1);

namespace Jah;

use Fiber;

/**
 * Non-blocking Event Loop for IPC and IO streams using stream_select.
 */
final class JasEventLoop
{
    private static array $readStreams = [];
    private static array $streamFibers = [];

    public static function watch($stream, Fiber $fiber): void
    {
        $id = (int) $stream;
        self::$readStreams[$id] = $stream;
        self::$streamFibers[$id] = $fiber;
    }

    public static function unwatch($stream): void
    {
        $id = (int) $stream;
        unset(self::$readStreams[$id]);
        unset(self::$streamFibers[$id]);
    }

    public static function tick(int $timeoutMicroseconds = 5000): void
    {
        if (empty(self::$readStreams)) {
            // Sleep slightly to prevent 100% CPU busy wait if no IO is pending
            usleep($timeoutMicroseconds);
            return;
        }

        $read = self::$readStreams;
        $write = null;
        $except = null;

        $tv_sec = (int) floor($timeoutMicroseconds / 1_000_000);
        $tv_usec = $timeoutMicroseconds % 1_000_000;

        // Suppress warnings in case stream was closed externally
        if (@stream_select($read, $write, $except, $tv_sec, $tv_usec) > 0) {
            foreach ($read as $stream) {
                $id = (int) $stream;
                if (!isset(self::$streamFibers[$id])) {
                    continue;
                }

                $fiber = self::$streamFibers[$id];
                self::unwatch($stream);

                if ($fiber->isSuspended()) {
                    $fiber->resume($stream);
                }
            }
        }
    }
}
