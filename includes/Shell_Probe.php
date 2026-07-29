<?php
/**
 * File: includes/Shell_Probe.php
 */

declare(strict_types=1);

namespace ArgentVideo;

final class Shell_Probe
{
    /** @return array{ok:bool,exit_code:int,output:string} */
    public static function run(array $command): array
    {
        if (! self::exec_available()) {
            return array('ok' => false, 'exit_code' => 1, 'output' => 'PHP exec() is unavailable or disabled.');
        }
        if ([] === $command || ! self::valid_absolute_path((string) $command[0])) {
            return array('ok' => false, 'exit_code' => 1, 'output' => 'Invalid executable path.');
        }

        $parts = array_map(static fn(mixed $part): string => escapeshellarg((string) $part), $command);
        $output = array();
        $exit_code = 1;
        exec(implode(' ', $parts) . ' 2>&1', $output, $exit_code);

        return array(
            'ok'        => 0 === $exit_code,
            'exit_code' => $exit_code,
            'output'    => implode("\n", $output),
        );
    }

    public static function path_executable(string $path): bool
    {
        if (! self::exec_available() || ! self::valid_absolute_path($path)) {
            return false;
        }

        $output = array();
        $exit_code = 1;
        exec('if [ -f ' . escapeshellarg($path) . ' ] && [ -x ' . escapeshellarg($path) . ' ]; then exit 0; else exit 1; fi', $output, $exit_code);
        return 0 === $exit_code;
    }

    public static function stat_restricted(string $path): bool
    {
        return '' !== trim((string) ini_get('open_basedir')) && ! @is_executable($path) && self::path_executable($path);
    }

    public static function exec_available(): bool
    {
        if (! function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return ! in_array('exec', $disabled, true);
    }

    private static function valid_absolute_path(string $path): bool
    {
        return '' !== $path
            && str_starts_with($path, '/')
            && ! str_contains($path, "\0")
            && 1 === preg_match('#^/[A-Za-z0-9_./+-]+$#', $path);
    }
}

// EOF: includes/Shell_Probe.php
