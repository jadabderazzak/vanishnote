<?php

namespace App\Enum;

/**
 * Defines standard log levels as integers, inspired by PSR-3.
 */
final class LogLevel
{

    public const WARNING   = 300;
    public const ERROR     = 400;
    public const CRITICAL  = 500;


    /**
     * Returns a human-readable label for a given log level.
     *
     * @param int $level
     * @return string
     */
    public static function getLabel(int $level): string
    {
        return match ($level) {

            self::WARNING   => 'WARNING',
            self::ERROR     => 'ERROR',
            self::CRITICAL  => 'CRITICAL',

            default         => 'UNKNOWN',
        };
    }
}
