<?php
/**
 * /home/alan/src/wp-argent-video-processor/includes/Transcoder.php
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
            throw new RuntimeException('Source video is missing or unreadable: ' . $source);
        }

        $current_signature = hash('sha256', wp_normalize_path($source) . '|' . filesize($source) . '|' . filemtime($source));
        if (! hash_equals((string) $job['source_signature'], $current_signature)) {
            throw new RuntimeException('Source video changed after it was queued; queue it again before processing.');
        }

        $free_space = disk_free_space(dirname($source));
        $minimum_space = max(536870912, (int) filesize($source));
        if (false !== $free_space && $free_space < $minimum_space) {
            throw new RuntimeException('Insufficient free disk space for temporary video derivatives.');
        }

        $source_probe = $this->probe->inspect($source);
        if (null === Probe::video_stream($source_probe)) {
            throw new RuntimeException('Source attachment has no readable video stream.');
        }

        $settings = Settings::all();
        $profile = (string) ($job['profile'] ?: $settings['profile']);
        $plans = $this->plans($source, $profile);
        $temporary_paths = array();
        $validated = array();

        try {
            foreach ($plans as $key => $plan) {
                $temporary_paths[] = $plan['temporary'];
                $command = 'mp4' === $key
                    ? Command_Builder::mp4($source, $plan['temporary'], $settings)
                    : Command_Builder::webm($source, $plan['temporary'], $settings);

                $result = $this->runner->run($command, true);
                if (0 !== $result['exit_code']) {
                    throw new RuntimeException(
                        strtoupper($key) . ' encode failed: ' . $this->error_tail($result['stderr'])
                    );
                }

                if (! is_file($plan['temporary']) || 0 === (int) filesize($plan['temporary'])) {
                    throw new RuntimeException(strtoupper($key) . ' encode did not create a usable output file.');
                }

                $output_probe = $this->probe->inspect($plan['temporary']);
                $this->validate_output($source_probe, $output_probe, $key, $settings);
                $validated[$key] = $this->manifest_entry($plan['temporary'], $plan['final'], $output_probe, $key);
            }

            foreach ($plans as $key => $plan) {
                if (is_file($plan['final']) && ! @unlink($plan['final'])) {
                    throw new RuntimeException('Could not replace existing derivative: ' . $plan['final']);
                }

                if (! @rename($plan['temporary'], $plan['final'])) {
                    throw new RuntimeException('Could not move validated derivative into place: ' . $plan['final']);
                }

                $validated[$key]['path'] = $plan['final'];
                $validated[$key]['url'] = $this->path_to_url($plan['final']);
                $validated[$key]['size'] = (int) filesize($plan['final']);
            }
        } catch (Throwable $error) {
            foreach ($temporary_paths as $temporary_path) {
                if (is_file($temporary_path)) {
                    @unlink($temporary_path);
                }
            }
            throw $error;
        }

        update_post_meta($attachment_id, '_argent_video_outputs', $validated);
        update_post_meta($attachment_id, '_argent_video_status', 'complete');
        update_post_meta($attachment_id, '_argent_video_processed_at', current_time('mysql', true));
        update_post_meta($attachment_id, '_argent_video_processor_version', ARGENT_VIDEO_VERSION);

        return $validated;
    }

    /** @return array<string, array{final:string,temporary:string}> */
    private function plans(string $source, string $profile): array
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

        if ([] === $plans) {
            throw new RuntimeException('No output profile was selected.');
        }

        return $plans;
    }

    /** @param array<string, mixed> $source
     *  @param array<string, mixed> $output
     *  @param array<string, mixed> $settings
     */
    private function validate_output(array $source, array $output, string $type, array $settings): void
    {
        $stream = Probe::video_stream($output);
        if (null === $stream) {
            throw new RuntimeException('Generated ' . $type . ' file has no video stream.');
        }

        $expected_codec = 'mp4' === $type ? 'h264' : 'vp9';
        if ($expected_codec !== ($stream['codec_name'] ?? '')) {
            throw new RuntimeException('Generated ' . $type . ' file has unexpected codec.');
        }

        $duration_delta = abs(Probe::duration($source) - Probe::duration($output));
        $allowed_delta = max(2.0, Probe::duration($source) * 0.01);
        if ($duration_delta > $allowed_delta) {
            throw new RuntimeException('Generated ' . $type . ' duration differs too much from the source.');
        }

        if ((int) ($stream['width'] ?? 0) > (int) $settings['max_width']) {
            throw new RuntimeException('Generated ' . $type . ' width exceeds the configured maximum.');
        }

        if ((int) ($stream['height'] ?? 0) > (int) $settings['max_height']) {
            throw new RuntimeException('Generated ' . $type . ' height exceeds the configured maximum.');
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

    private function error_tail(string $error): string
    {
        $error = trim($error);
        if (strlen($error) <= 8000) {
            return $error;
        }

        return substr($error, -8000);
    }
}

// EOF: /home/alan/src/wp-argent-video-processor/includes/Transcoder.php
