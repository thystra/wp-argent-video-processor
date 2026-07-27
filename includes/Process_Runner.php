<?php
/**
 * /home/alan/src/wp-argent-video-processor/includes/Process_Runner.php
 */

declare(strict_types=1);

namespace ArgentVideo;

use RuntimeException;

final class Process_Runner
{
    /**
     * @param list<string> $command
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    public function run(array $command, bool $prioritize = false): array
    {
        if (! function_exists('proc_open') || $this->function_disabled('proc_open')) {
            throw new RuntimeException('PHP proc_open() is unavailable or disabled.');
        }

        if ([] === $command || ! is_executable($command[0])) {
            throw new RuntimeException('Executable is missing or not executable: ' . ($command[0] ?? '(empty command)'));
        }

        if ($prioritize) {
            $command = $this->with_priority($command);
        }

        $stdout_path = tempnam(sys_get_temp_dir(), 'argent-video-stdout-');
        $stderr_path = tempnam(sys_get_temp_dir(), 'argent-video-stderr-');
        if (false === $stdout_path || false === $stderr_path) {
            throw new RuntimeException('Could not allocate temporary process log files.');
        }

        $descriptors = array(
            0 => array('file', '/dev/null', 'r'),
            1 => array('file', $stdout_path, 'w'),
            2 => array('file', $stderr_path, 'w'),
        );

        $process = proc_open($command, $descriptors, $pipes, null, null, array('bypass_shell' => true));
        if (! is_resource($process)) {
            @unlink($stdout_path);
            @unlink($stderr_path);
            throw new RuntimeException('Could not start external process.');
        }

        $exit_code = proc_close($process);
        $stdout = $this->read_tail($stdout_path, 1048576);
        $stderr = $this->read_tail($stderr_path, 1048576);
        @unlink($stdout_path);
        @unlink($stderr_path);

        return array(
            'exit_code' => (int) $exit_code,
            'stdout'    => $stdout,
            'stderr'    => $stderr,
        );
    }

    /** @param list<string> $command
     *  @return list<string>
     */
    private function with_priority(array $command): array
    {
        $settings = Settings::all();
        $prefix = array();

        if (is_executable('/usr/bin/nice')) {
            array_push($prefix, '/usr/bin/nice', '-n', (string) (int) $settings['nice_level']);
        }

        if (is_executable('/usr/bin/ionice')) {
            array_push(
                $prefix,
                '/usr/bin/ionice',
                '-c',
                (string) (int) $settings['ionice_class'],
                '-n',
                (string) (int) $settings['ionice_level']
            );
        }

        return array_merge($prefix, $command);
    }

    private function function_disabled(string $function): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return in_array($function, $disabled, true);
    }

    private function read_tail(string $path, int $maximum_bytes): string
    {
        if (! is_file($path)) {
            return '';
        }

        $size = filesize($path);
        if (false === $size || 0 === $size) {
            return '';
        }

        $handle = fopen($path, 'rb');
        if (false === $handle) {
            return '';
        }

        if ($size > $maximum_bytes) {
            fseek($handle, -$maximum_bytes, SEEK_END);
        }

        $content = stream_get_contents($handle);
        fclose($handle);

        return false === $content ? '' : $content;
    }
}

// EOF: /home/alan/src/wp-argent-video-processor/includes/Process_Runner.php
