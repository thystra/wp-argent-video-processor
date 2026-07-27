<?php
/**
 * /home/alan/src/wp-argent-video-processor/includes/Probe.php
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
        foreach ((array) ($probe['streams'] ?? array()) as $stream) {
            if (is_array($stream) && 'video' === ($stream['codec_type'] ?? '')) {
                return $stream;
            }
        }

        return null;
    }
}

// EOF: /home/alan/src/wp-argent-video-processor/includes/Probe.php
