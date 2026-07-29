<?php
/**
 * File: includes/Probe.php
 */

declare(strict_types=1);

namespace ArgentVideo;

use RuntimeException;

final class Probe
{
    public function __construct(private readonly Process_Runner $runner)
    {
    }

    /** @return array<string, mixed> */
    public function inspect(string $path): array
    {
        $command = array(
            (string) Settings::get('ffprobe_path'),
            '-v',
            'error',
            '-show_entries',
            'format=duration,size,bit_rate,format_name:stream=index,codec_type,codec_name,width,height,pix_fmt,avg_frame_rate,bit_rate,channels,sample_rate:stream_tags=rotate:stream_side_data=rotation',
            '-of',
            'json',
            $path,
        );

        $result = $this->runner->run($command);
        if (0 !== $result['exit_code']) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal worker exception; escaped at every administrative display boundary.
            throw new RuntimeException('FFprobe failed: ' . trim($result['stderr']));
        }

        $decoded = json_decode($result['stdout'], true);
        if (! is_array($decoded)) {
            throw new RuntimeException('FFprobe returned invalid JSON.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $probe */
    public static function duration(array $probe): float
    {
        return isset($probe['format']['duration']) ? (float) $probe['format']['duration'] : 0.0;
    }

    /** @param array<string, mixed> $probe
     *  @return array<string, mixed>|null
     */
    public static function video_stream(array $probe): ?array
    {
        return self::stream($probe, 'video');
    }

    /** @param array<string, mixed> $probe
     *  @return array<string, mixed>|null
     */
    public static function audio_stream(array $probe): ?array
    {
        return self::stream($probe, 'audio');
    }

    /** @param array<string, mixed> $probe
     *  @return array{0:int,1:int}
     */
    public static function display_dimensions(array $probe): array
    {
        $stream = self::video_stream($probe) ?? array();
        $width = (int) ($stream['width'] ?? 0);
        $height = (int) ($stream['height'] ?? 0);
        $rotation = 0.0;
        if (isset($stream['tags']['rotate'])) {
            $rotation = (float) $stream['tags']['rotate'];
        }
        foreach ((array) ($stream['side_data_list'] ?? array()) as $side_data) {
            if (is_array($side_data) && isset($side_data['rotation'])) {
                $rotation = (float) $side_data['rotation'];
            }
        }
        $normalized = abs(((int) round($rotation)) % 180);
        return 90 === $normalized ? array($height, $width) : array($width, $height);
    }

    /** @param array<string, mixed> $probe
     *  @return array<string, mixed>|null
     */
    private static function stream(array $probe, string $type): ?array
    {
        foreach ((array) ($probe['streams'] ?? array()) as $stream) {
            if (is_array($stream) && $type === ($stream['codec_type'] ?? '')) {
                return $stream;
            }
        }

        return null;
    }
}

// EOF: includes/Probe.php
