<?php
/**
 * /home/alan/src/wp-argent-video-processor/includes/Diagnostics.php
 */

declare(strict_types=1);

namespace ArgentVideo;

use Throwable;

final class Diagnostics
{
    /** @return list<array{check:string,status:string,detail:string}> */
    public function checks(): array
    {
        $settings = Settings::all();
        $checks = array();

        foreach (array('ffmpeg_path' => 'FFmpeg', 'ffprobe_path' => 'FFprobe', 'wp_cli_path' => 'WP-CLI') as $key => $label) {
            $path = (string) $settings[$key];
            $checks[] = array(
                'check'  => $label,
                'status' => is_executable($path) ? 'ok' : 'error',
                'detail' => $path,
            );
        }

        $checks[] = array(
            'check'  => 'proc_open()',
            'status' => function_exists('proc_open') ? 'ok' : 'error',
            'detail' => function_exists('proc_open') ? 'Available' : 'Unavailable',
        );
        $checks[] = array(
            'check'  => 'exec()',
            'status' => function_exists('exec') ? 'ok' : 'warning',
            'detail' => function_exists('exec') ? 'Available for detached dispatch' : 'Use a system-scheduled worker',
        );
        $checks[] = array(
            'check'  => 'Uploads writable',
            'status' => wp_is_writable((string) wp_get_upload_dir()['basedir']) ? 'ok' : 'error',
            'detail' => (string) wp_get_upload_dir()['basedir'],
        );

        return array_merge($checks, $this->encoder_checks($settings));
    }

    /** @param array<string, mixed> $settings
     *  @return list<array{check:string,status:string,detail:string}>
     */
    private function encoder_checks(array $settings): array
    {
        $profile = (string) ($settings['profile'] ?? 'dual');
        $required = array();

        if (in_array($profile, array('compatibility', 'dual'), true)) {
            $required['libx264'] = 'H.264 video';
            $required['aac'] = 'AAC audio';
        }
        if (in_array($profile, array('open', 'dual'), true)) {
            $required['libvpx-vp9'] = 'VP9 video';
            $required['libopus'] = 'Opus audio';
        }

        $ffmpeg = (string) ($settings['ffmpeg_path'] ?? '');
        if ([] === $required || ! is_executable($ffmpeg)) {
            return array();
        }

        try {
            $result = (new Process_Runner())->run(array($ffmpeg, '-hide_banner', '-encoders'));
        } catch (Throwable $error) {
            return array(array(
                'check'  => 'FFmpeg encoders',
                'status' => 'error',
                'detail' => $error->getMessage(),
            ));
        }

        if (0 !== $result['exit_code']) {
            return array(array(
                'check'  => 'FFmpeg encoders',
                'status' => 'error',
                'detail' => trim($result['stderr']),
            ));
        }

        $output = $result['stdout'] . "\n" . $result['stderr'];
        $checks = array();
        foreach ($required as $encoder => $description) {
            $available = 1 === preg_match(
                '/^\s*[A-Z\.]{6}\s+' . preg_quote($encoder, '/') . '\s/m',
                $output
            );
            $checks[] = array(
                'check'  => 'Encoder ' . $encoder,
                'status' => $available ? 'ok' : 'error',
                'detail' => $available ? $description . ' available' : $description . ' unavailable',
            );
        }

        return $checks;
    }
}

// EOF: /home/alan/src/wp-argent-video-processor/includes/Diagnostics.php
