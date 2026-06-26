<?php

declare(strict_types=1);

namespace Jah;

final class JasBinaryCompiler
{
    public static function compileExit(int $code): string
    {
        $binary = pack('V*',
            0x7f454c46,     // ELF magic
            0x01010100,     // 32-bit
            0x00000000,     // machine
            0x00000000,     // version
            0x00000000,     // entry
            0x00000000,     // phoff
            0x00000000,     // shoff
            0x00000000,     // flags
            0x00000000,     // ehsize
            0x00000000,     // phentsize
            0x00000000,     // phnum
            0x00000000,     // shentsize
            0x00000000,     // shnum
            0x00000000      // shstrndx
        );
        
        return $binary;
    }
}