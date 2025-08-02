<?php

namespace App\Enum;

/**
 * Defines standard log levels as integers, inspired by PSR-3.
 */
final class LogLevel
{
    public const DEBUG     = 100;
    public const INFO      = 200;
    public const NOTICE    = 250;
    public const WARNING   = 300;
    public const ERROR     = 400;
    public const CRITICAL  = 500;
    public const ALERT     = 550;
    public const EMERGENCY = 600;

    /**
     * Returns a human-readable label for a given log level.
     *
     * @param int $level
     * @return string
     */
    public static function getLabel(int $level): string
    {
        return match ($level) {
            self::DEBUG     => 'DEBUG',
            self::INFO      => 'INFO',
            self::NOTICE    => 'NOTICE',
            self::WARNING   => 'WARNING',
            self::ERROR     => 'ERROR',
            self::CRITICAL  => 'CRITICAL',
            self::ALERT     => 'ALERT',
            self::EMERGENCY => 'EMERGENCY',
            default         => 'UNKNOWN',
        };
    }
}
