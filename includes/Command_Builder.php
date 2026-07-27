<?php
/**
 * /home/alan/src/wp-argent-video-processor/includes/Command_Builder.php
 */

declare(strict_types=1);

namespace ArgentVideo;

final class Command_Builder
{
    /** @param array<string, mixed> $settings
     *  @return list<string>
     */
    public static function mp4(string $source, string $output, array $settings): array
    {
        $width = (int) $settings['max_width'];
        $height = (int) $settings['max_height'];
        $maxrate = (int) $settings['mp4_maxrate_kbps'];
        $audio = (int) $settings['audio_bitrate_kbps'];

        $command = self::base($source, $settings);
        array_push(
            $command,
            '-vf', self::scale_filter($width, $height),
            '-c:v', 'libx264',
            '-preset', 'medium',
            '-crf', (string) (int) $settings['mp4_crf'],
            '-maxrate', $maxrate . 'k',
            '-bufsize', ($maxrate * 2) . 'k',
            '-pix_fmt', 'yuv420p',
            '-profile:v', 'high',
            '-level', '4.0',
            '-g', '60',
            '-keyint_min', '60',
            '-sc_threshold', '0',
            '-c:a', 'aac',
            '-b:a', $audio . 'k',
            '-ac', '2',
            '-movflags', '+faststart',
            $output
        );

        return $command;
    }

    /** @param array<string, mixed> $settings
     *  @return list<string>
     */
    public static function webm(string $source, string $output, array $settings): array
    {
        $width = (int) $settings['max_width'];
        $height = (int) $settings['max_height'];
        $maxrate = (int) $settings['webm_maxrate_kbps'];
        $audio = min(192, (int) $settings['audio_bitrate_kbps']);

        $command = self::base($source, $settings);
        array_push(
            $command,
            '-vf', self::scale_filter($width, $height),
            '-c:v', 'libvpx-vp9',
            '-deadline', 'good',
            '-cpu-used', '2',
            '-row-mt', '1',
            '-threads', '0',
            '-crf', (string) (int) $settings['webm_crf'],
            '-b:v', $maxrate . 'k',
            '-maxrate', $maxrate . 'k',
            '-bufsize', ($maxrate * 2) . 'k',
            '-g', '240',
            '-c:a', 'libopus',
            '-b:a', $audio . 'k',
            '-ac', '2',
            $output
        );

        return $command;
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, int|string> $rendition
     * @return list<string>
     */
    public static function hls(
        string $source,
        string $playlist,
        string $segment_pattern,
        array $settings,
        array $rendition,
        bool $has_audio
    ): array {
        $segment_seconds = (int) $settings['hls_segment_seconds'];
        $maxrate = (int) $rendition['video_kbps'];
        $command = self::base($source, $settings, $has_audio);
        array_push(
            $command,
            '-vf', self::scale_filter((int) $rendition['max_width'], (int) $rendition['max_height']),
            '-c:v', 'libx264',
            '-preset', (string) $settings['hls_preset'],
            '-crf', '23',
            '-maxrate', $maxrate . 'k',
            '-bufsize', ($maxrate * 2) . 'k',
            '-pix_fmt', 'yuv420p',
            '-profile:v', 'high',
            '-level', '4.0',
            '-sc_threshold', '0',
            '-force_key_frames', 'expr:gte(t,n_forced*' . $segment_seconds . ')'
        );

        if ($has_audio) {
            array_push(
                $command,
                '-c:a', 'aac',
                '-b:a', (int) $rendition['audio_kbps'] . 'k',
                '-ac', '2'
            );
        }

        array_push(
            $command,
            '-f', 'hls',
            '-hls_time', (string) $segment_seconds,
            '-hls_playlist_type', 'vod',
            '-hls_list_size', '0',
            '-hls_segment_type', 'fmp4',
            '-hls_flags', 'independent_segments',
            '-hls_fmp4_init_filename', 'init.mp4',
            '-hls_segment_filename', $segment_pattern,
            $playlist
        );

        return $command;
    }

    /** @param array<string, mixed> $settings
     *  @return list<string>
     */
    private static function base(string $source, array $settings, bool $map_audio = true): array
    {
        $command = array(
            (string) $settings['ffmpeg_path'],
            '-hide_banner',
            '-nostdin',
            '-y',
            '-i',
            $source,
            '-map',
            '0:v:0',
        );

        if ($map_audio) {
            array_push($command, '-map', '0:a:0?');
        }

        if (! empty($settings['strip_metadata'])) {
            array_push($command, '-map_metadata', '-1', '-map_chapters', '-1');
        }

        return $command;
    }

    private static function scale_filter(int $width, int $height): string
    {
        return "scale=w='min({$width},iw)':h='min({$height},ih)':force_original_aspect_ratio=decrease:force_divisible_by=2";
    }
}

// EOF: /home/alan/src/wp-argent-video-processor/includes/Command_Builder.php
