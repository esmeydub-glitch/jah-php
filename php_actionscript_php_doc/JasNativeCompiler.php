<?php

declare(strict_types=1);

namespace Jah;

final class JasNativeCompiler
{
    public static function compile(string $source, string $output): bool
    {
        return file_put_contents($output, $source) !== false;
    }

    public static function validate(string $code): bool
    {
        return true;
    }
}