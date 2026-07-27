<?php
/**
 * /home/alan/src/wp-argent-video-processor/tests/ffmpeg-integration.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/Command_Builder.php';

use ArgentVideo\Command_Builder;

$ffmpeg = '/usr/bin/ffmpeg';
$ffprobe = '/usr/bin/ffprobe';
if (! is_executable($ffmpeg) || ! is_executable($ffprobe)) {
    fwrite(STDOUT, "FFmpeg integration test skipped: ffmpeg or ffprobe is unavailable.\n");
    exit(0);
}

$directory = sys_get_temp_dir() . '/argent-video-test-' . bin2hex(random_bytes(6));
if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
    fwrite(STDERR, "FAIL: could not create integration-test directory.\n");
    exit(1);
}

$cleanup = static function () use ($directory): void {
    $files = glob($directory . '/*');
    if (is_array($files)) {
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    @rmdir($directory);
};
register_shutdown_function($cleanup);

$run = static function (array $command): array {
    $descriptors = array(
        0 => array('file', '/dev/null', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $process = proc_open($command, $descriptors, $pipes, null, null, array('bypass_shell' => true));
    if (! is_resource($process)) {
        return array('exit_code' => 255, 'stdout' => '', 'stderr' => 'proc_open failed');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return array(
        'exit_code' => proc_close($process),
        'stdout' => false === $stdout ? '' : $stdout,
        'stderr' => false === $stderr ? '' : $stderr,
    );
};

$base = $directory . '/base.mp4';
$source = $directory . '/source.mp4';
$mp4 = $directory . '/output.mp4';
$webm = $directory . '/output.webm';

$result = $run(array(
    $ffmpeg,
    '-hide_banner',
    '-loglevel', 'error',
    '-y',
    '-f', 'lavfi',
    '-i', 'testsrc2=size=320x240:rate=10',
    '-t', '1',
    '-c:v', 'libx264',
    '-pix_fmt', 'yuv420p',
    $base,
));
if (0 !== $result['exit_code']) {
    fwrite(STDERR, "FAIL: synthetic source encode failed: {$result['stderr']}\n");
    exit(1);
}

$result = $run(array(
    $ffmpeg,
    '-hide_banner',
    '-loglevel', 'error',
    '-y',
    '-display_rotation:v:0', '90',
    '-i', $base,
    '-map', '0',
    '-c', 'copy',
    '-metadata', 'location=+30.1161-081.8837/',
    $source,
));
if (0 !== $result['exit_code']) {
    fwrite(STDERR, "FAIL: synthetic metadata remux failed: {$result['stderr']}\n");
    exit(1);
}

$settings = array(
    'ffmpeg_path'        => $ffmpeg,
    'max_width'          => 1280,
    'max_height'         => 720,
    'mp4_crf'            => 23,
    'mp4_maxrate_kbps'   => 1000,
    'webm_crf'           => 32,
    'webm_maxrate_kbps'  => 800,
    'audio_bitrate_kbps' => 96,
    'strip_metadata'     => true,
);

foreach (array(
    'mp4' => Command_Builder::mp4($source, $mp4, $settings),
    'webm' => Command_Builder::webm($source, $webm, $settings),
) as $type => $command) {
    $result = $run($command);
    if (0 !== $result['exit_code']) {
        fwrite(STDERR, "FAIL: {$type} command failed: {$result['stderr']}\n");
        exit(1);
    }
}

$probe = static function (string $path) use ($ffprobe, $run): array {
    $result = $run(array(
        $ffprobe,
        '-v', 'error',
        '-show_entries',
        'format=duration:format_tags:stream=codec_type,codec_name,width,height:stream_tags=rotate:stream_side_data=rotation',
        '-of', 'json',
        $path,
    ));
    if (0 !== $result['exit_code']) {
        throw new RuntimeException('FFprobe failed: ' . $result['stderr']);
    }

    $decoded = json_decode($result['stdout'], true);
    if (! is_array($decoded)) {
        throw new RuntimeException('FFprobe returned invalid JSON.');
    }

    return $decoded;
};

try {
    $mp4_probe = $probe($mp4);
    $webm_probe = $probe($webm);
} catch (RuntimeException $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}

$video_stream = static function (array $data): array {
    foreach ((array) ($data['streams'] ?? array()) as $stream) {
        if (is_array($stream) && 'video' === ($stream['codec_type'] ?? '')) {
            return $stream;
        }
    }
    return array();
};

$failures = array();
foreach (array('mp4' => $mp4_probe, 'webm' => $webm_probe) as $type => $data) {
    $stream = $video_stream($data);
    $expected_codec = 'mp4' === $type ? 'h264' : 'vp9';
    if ($expected_codec !== ($stream['codec_name'] ?? '')) {
        $failures[] = "{$type} codec was not {$expected_codec}.";
    }
    if (240 !== (int) ($stream['width'] ?? 0) || 320 !== (int) ($stream['height'] ?? 0)) {
        $failures[] = "{$type} did not normalize the 90-degree display rotation into 240x320 pixels.";
    }
    if (isset($stream['tags']['rotate']) || isset($stream['side_data_list'][0]['rotation'])) {
        $failures[] = "{$type} retained rotation metadata after normalization.";
    }
    $format_tags = (array) ($data['format']['tags'] ?? array());
    foreach (array_keys($format_tags) as $tag) {
        if (str_contains(strtolower((string) $tag), 'location')) {
            $failures[] = "{$type} retained location metadata.";
        }
    }
}

if ([] !== $failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "FFmpeg integration test passed.\n");

// EOF: /home/alan/src/wp-argent-video-processor/tests/ffmpeg-integration.php
