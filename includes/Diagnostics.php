<?php
/**
 * /home/alan/src/wp-argent-video-processor/includes/Diagnostics.php
 */

declare(strict_types=1);

namespace ArgentVideo;

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

        return $checks;
    }
}

// EOF: /home/alan/src/wp-argent-video-processor/includes/Diagnostics.php
