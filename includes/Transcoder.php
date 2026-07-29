<?php
/**
 * File: includes/Transcoder.php
 */

declare(strict_types=1);

namespace ArgentVideo;

use RuntimeException;
use Throwable;

final class Transcoder
{
    public function __construct(
        private readonly Process_Runner $runner,
        private readonly Probe $probe
    ) {
    }

    /** @param array<string, mixed> $job
     *  @return array<string, mixed>
     */
    public function process(array $job): array
    {
        $attachment_id = (int) $job['attachment_id'];
        $source = (string) get_attached_file($attachment_id, true);

        if ('' === $source || ! is_file($source) || ! is_readable($source)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
            throw new RuntimeException('Source video is missing or unreadable: ' . $source);
        }

        $current_signature = hash('sha256', wp_normalize_path($source) . '|' . filesize($source) . '|' . filemtime($source));
        if (! hash_equals((string) $job['source_signature'], $current_signature)) {
            throw new RuntimeException('Source video changed after it was queued; queue it again before processing.');
        }

        $free_space = disk_free_space(dirname($source));
        $minimum_space = max(1073741824, (int) filesize($source) * 2);
        if (false !== $free_space && $free_space < $minimum_space) {
            throw new RuntimeException('Insufficient free disk space for temporary video derivatives and adaptive segments.');
        }

        $source_probe = $this->probe->inspect($source);
        if (null === Probe::video_stream($source_probe)) {
            throw new RuntimeException('Source attachment has no readable video stream.');
        }

        $settings = Settings::all();
        $job_profile = (string) ($job['profile'] ?: Settings::current_job_profile());
        $progressive_profile = Settings::progressive_profile($job_profile);
        $adaptive_only = 'adaptive-only' === $job_profile;
        $previous = get_post_meta($attachment_id, '_argent_video_outputs', true);
        $previous = is_array($previous) ? $previous : array();
        $validated = $adaptive_only ? $previous : array();
        $plans = $this->progressive_plans($source, $progressive_profile);
        $temporary_paths = array();
        $temporary_directories = array();
        $hls_plan = null;

        try {
            foreach ($plans as $key => $plan) {
                $temporary_paths[] = $plan['temporary'];
                $command = 'mp4' === $key
                    ? Command_Builder::mp4($source, $plan['temporary'], $settings)
                    : Command_Builder::webm($source, $plan['temporary'], $settings);

                $result = $this->runner->run($command, true);
                if (0 !== $result['exit_code']) {
                    throw new RuntimeException(strtoupper($key) . ' encode failed: ' . $this->error_tail($result['stderr']));
                }
                if (! is_file($plan['temporary']) || 0 === (int) filesize($plan['temporary'])) {
                    throw new RuntimeException(strtoupper($key) . ' encode did not create a usable output file.');
                }

                $output_probe = $this->probe->inspect($plan['temporary']);
                $this->validate_output($source_probe, $output_probe, $key, $settings);
                $validated[$key] = $this->manifest_entry($plan['temporary'], $plan['final'], $output_probe, $key);
            }

            if (Settings::job_has_hls($job_profile)) {
                $hls_plan = $this->encode_hls($source, $source_probe, $settings);
                $temporary_directories[] = $hls_plan['temporary_directory'];
                $validated['hls'] = $hls_plan['manifest'];
            }

            foreach ($plans as $key => $plan) {
                if (is_file($plan['final'])) {
                    wp_delete_file($plan['final']);
                    clearstatcache(true, $plan['final']);
                    if (is_file($plan['final'])) {
                        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
                        throw new RuntimeException('Could not replace existing derivative: ' . $plan['final']);
                    }
                }
                // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic same-filesystem promotion prevents partially installed validated media.
                if (! @rename($plan['temporary'], $plan['final'])) {
                    throw new RuntimeException('Could not move validated derivative into place: ' . $plan['final']);
                }
                $validated[$key]['path'] = $plan['final'];
                $validated[$key]['url'] = $this->path_to_url($plan['final']);
                $validated[$key]['size'] = (int) filesize($plan['final']);
            }

            if (is_array($hls_plan)) {
                $this->promote_directory($hls_plan['temporary_directory'], $hls_plan['final_directory']);
                $validated['hls']['path'] = $hls_plan['final_directory'] . '/master.m3u8';
                $validated['hls']['directory'] = $hls_plan['final_directory'];
                $validated['hls']['url'] = $this->path_to_url($validated['hls']['path']);
                $validated['hls']['size'] = Adaptive_HLS::directory_size($hls_plan['final_directory']);
                foreach ($validated['hls']['renditions'] as &$rendition) {
                    $label = (string) $rendition['label'];
                    $rendition['path'] = $hls_plan['final_directory'] . '/' . $label . '/index.m3u8';
                    $rendition['url'] = $this->path_to_url((string) $rendition['path']);
                }
                unset($rendition);
            }
        } catch (Throwable $error) {
            foreach ($temporary_paths as $temporary_path) {
                if (is_file($temporary_path)) {
                    wp_delete_file($temporary_path);
                }
            }
            foreach ($temporary_directories as $temporary_directory) {
                $this->remove_tree($temporary_directory);
            }
            throw $error;
        }

        update_post_meta($attachment_id, '_argent_video_outputs', $validated);
        update_post_meta($attachment_id, '_argent_video_status', 'complete');
        update_post_meta($attachment_id, '_argent_video_processed_at', current_time('mysql', true));
        update_post_meta($attachment_id, '_argent_video_processor_version', ARGENT_VIDEO_VERSION);
        update_post_meta($attachment_id, '_argent_video_profile', $job_profile);

        return $validated;
    }

    /** @return array<string, array{final:string,temporary:string}> */
    private function progressive_plans(string $source, string $profile): array
    {
        $plans = array();
        if (in_array($profile, array('compatibility', 'dual'), true)) {
            $final = Output_Namer::derivative($source, '720p', 'mp4');
            $plans['mp4'] = array('final' => $final, 'temporary' => Output_Namer::temporary($final));
        }
        if (in_array($profile, array('open', 'dual'), true)) {
            $final = Output_Namer::derivative($source, '720p-vp9', 'webm');
            $plans['webm'] = array('final' => $final, 'temporary' => Output_Namer::temporary($final));
        }
        return $plans;
    }

    /**
     * @param array<string, mixed> $source_probe
     * @param array<string, mixed> $settings
     * @return array{temporary_directory:string,final_directory:string,manifest:array<string,mixed>}
     */
    private function encode_hls(string $source, array $source_probe, array $settings): array
    {
        $final_directory = Output_Namer::adaptive_directory($source);
        $temporary_directory = Output_Namer::temporary_directory($final_directory);
        if (! wp_mkdir_p($temporary_directory)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
            throw new RuntimeException('Could not create temporary HLS directory: ' . $temporary_directory);
        }

        try {
            $renditions = Adaptive_HLS::renditions($settings, $source_probe);
            $has_audio = null !== Probe::audio_stream($source_probe);
            $manifest_renditions = array();

            foreach ($renditions as $rendition) {
                $label = (string) $rendition['label'];
                $directory = $temporary_directory . '/' . $label;
                if (! wp_mkdir_p($directory)) {
                    throw new RuntimeException('Could not create HLS rendition directory: ' . $directory);
                }
                $playlist = $directory . '/index.m3u8';
                $command = Command_Builder::hls(
                    $source,
                    $playlist,
                    $directory . '/segment-%05d.m4s',
                    $settings,
                    $rendition,
                    $has_audio
                );
                $result = $this->runner->run($command, true);
                if (0 !== $result['exit_code']) {
                    throw new RuntimeException('HLS ' . $label . ' encode failed: ' . $this->error_tail($result['stderr']));
                }
                Adaptive_HLS::validate_media_playlist($playlist);
                $probe = $this->probe->inspect($playlist);
                $this->validate_hls_output($source_probe, $probe, $rendition);
                $stream = Probe::video_stream($probe) ?? array();
                $manifest_renditions[] = array(
                    'label'       => $label,
                    'width'       => (int) ($stream['width'] ?? $rendition['width']),
                    'height'      => (int) ($stream['height'] ?? $rendition['height']),
                    'video_kbps'  => (int) $rendition['video_kbps'],
                    'audio_kbps'  => $has_audio ? (int) $rendition['audio_kbps'] : 0,
                    'path'        => '',
                    'url'         => '',
                );
            }

            Adaptive_HLS::write_master($temporary_directory . '/master.m3u8', $manifest_renditions);
            return array(
                'temporary_directory' => $temporary_directory,
                'final_directory'     => $final_directory,
                'manifest'            => array(
                    'path'       => $final_directory . '/master.m3u8',
                    'directory'  => $final_directory,
                    'url'        => '',
                    'mime'       => 'application/vnd.apple.mpegurl',
                    'duration'   => Probe::duration($source_probe),
                    'size'       => Adaptive_HLS::directory_size($temporary_directory),
                    'segment_seconds' => (int) $settings['hls_segment_seconds'],
                    'renditions' => $manifest_renditions,
                ),
            );
        } catch (Throwable $error) {
            $this->remove_tree($temporary_directory);
            throw $error;
        }
    }

    /** @param array<string, mixed> $source
     *  @param array<string, mixed> $output
     *  @param array<string, mixed> $settings
     */
    private function validate_output(array $source, array $output, string $type, array $settings): void
    {
        $stream = Probe::video_stream($output);
        if (null === $stream) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
            throw new RuntimeException('Generated ' . $type . ' file has no video stream.');
        }
        $expected_codec = 'mp4' === $type ? 'h264' : 'vp9';
        if ($expected_codec !== ($stream['codec_name'] ?? '')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
            throw new RuntimeException('Generated ' . $type . ' file has unexpected codec.');
        }
        $this->validate_duration($source, $output, $type);
        if ((int) ($stream['width'] ?? 0) > (int) $settings['max_width']) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
            throw new RuntimeException('Generated ' . $type . ' width exceeds the configured maximum.');
        }
        if ((int) ($stream['height'] ?? 0) > (int) $settings['max_height']) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
            throw new RuntimeException('Generated ' . $type . ' height exceeds the configured maximum.');
        }
    }

    /** @param array<string, mixed> $source
     *  @param array<string, mixed> $output
     *  @param array<string, int|string> $rendition
     */
    private function validate_hls_output(array $source, array $output, array $rendition): void
    {
        $stream = Probe::video_stream($output);
        if (null === $stream || 'h264' !== ($stream['codec_name'] ?? '')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
            throw new RuntimeException('Generated HLS ' . $rendition['label'] . ' rendition has no H.264 video stream.');
        }
        $this->validate_duration($source, $output, 'HLS ' . $rendition['label']);
        if ((int) ($stream['width'] ?? 0) > (int) $rendition['max_width'] || (int) ($stream['height'] ?? 0) > (int) $rendition['max_height']) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
            throw new RuntimeException('Generated HLS ' . $rendition['label'] . ' rendition exceeds its maximum dimensions.');
        }
    }

    /** @param array<string, mixed> $source
     *  @param array<string, mixed> $output
     */
    private function validate_duration(array $source, array $output, string $label): void
    {
        $duration_delta = abs(Probe::duration($source) - Probe::duration($output));
        $allowed_delta = max(2.0, Probe::duration($source) * 0.01);
        if ($duration_delta > $allowed_delta) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
            throw new RuntimeException('Generated ' . $label . ' duration differs too much from the source.');
        }
    }

    /** @param array<string, mixed> $probe
     *  @return array<string, mixed>
     */
    private function manifest_entry(string $temporary, string $final, array $probe, string $type): array
    {
        $stream = Probe::video_stream($probe) ?? array();
        return array(
            'path'     => $final,
            'url'      => '',
            'mime'     => 'mp4' === $type ? 'video/mp4' : 'video/webm',
            'codec'    => (string) ($stream['codec_name'] ?? ''),
            'width'    => (int) ($stream['width'] ?? 0),
            'height'   => (int) ($stream['height'] ?? 0),
            'duration' => Probe::duration($probe),
            'size'     => (int) filesize($temporary),
        );
    }

    private function promote_directory(string $temporary, string $final): void
    {
        $backup = '';
        if (is_dir($final)) {
            $backup = $final . '.old-' . bin2hex(random_bytes(4));
            // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic directory swap preserves the last valid HLS tree.
            if (! @rename($final, $backup)) {
                throw new RuntimeException('Could not preserve the existing HLS directory before replacement.');
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic directory promotion prevents partially installed HLS output.
        if (! @rename($temporary, $final)) {
            if ('' !== $backup) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic rollback restores the previously validated HLS tree.
                @rename($backup, $final);
            }
            throw new RuntimeException('Could not move validated HLS output into place.');
        }
        if ('' !== $backup) {
            $this->remove_tree($backup);
        }
    }

    private function path_to_url(string $path): string
    {
        $uploads = wp_get_upload_dir();
        $base_dir = wp_normalize_path((string) $uploads['basedir']);
        $normalized = wp_normalize_path($path);
        if (! str_starts_with($normalized, $base_dir . '/')) {
            throw new RuntimeException('Derivative is outside the WordPress uploads directory.');
        }
        $relative = ltrim(substr($normalized, strlen($base_dir)), '/');
        return trailingslashit((string) $uploads['baseurl']) . str_replace('%2F', '/', rawurlencode($relative));
    }

    private function remove_tree(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Recursive worker cleanup removes only plugin-created output directories.
                @rmdir($item->getPathname());
            } else {
                wp_delete_file($item->getPathname());
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Recursive worker cleanup removes only a plugin-created output directory.
        @rmdir($directory);
    }

    private function error_tail(string $error): string
    {
        $error = trim($error);
        return strlen($error) <= 8000 ? $error : substr($error, -8000);
    }
}

// EOF: includes/Transcoder.php
