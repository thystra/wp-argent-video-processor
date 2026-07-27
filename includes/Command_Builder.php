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

    /** @param array<string, mixed> $settings
     *  @return list<string>
     */
    private static function base(string $source, array $settings): array
    {
        $command = array(
            (string) $settings['ffmpeg_path'],
            '-hide_banner',
            '-nostdin',
            '-y',
            '-autorotate',
            '-i',
            $source,
            '-map',
            '0:v:0',
            '-map',
            '0:a:0?',
        );

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
