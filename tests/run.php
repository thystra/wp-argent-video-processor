<?php
/**
 * /home/alan/src/wp-argent-video-processor/tests/run.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/Output_Namer.php';
require_once dirname(__DIR__) . '/includes/Command_Builder.php';

use ArgentVideo\Command_Builder;
use ArgentVideo\Output_Namer;

$failures = array();

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$source = '/srv/uploads/video.phone.mp4';
$assert(
    '/srv/uploads/video.phone-argent-720p.mp4' === Output_Namer::derivative($source, '720p', 'mp4'),
    'Output naming should preserve the source stem and add the Argent profile suffix.'
);

$temporary = Output_Namer::temporary('/srv/uploads/video-argent-720p.mp4');
$assert(str_ends_with($temporary, '.mp4'), 'Temporary files must keep the final container extension for FFmpeg format detection.');
$assert(str_contains($temporary, '.tmp-'), 'Temporary files must include an unpredictable temporary marker.');

$settings = array(
    'ffmpeg_path'        => '/usr/bin/ffmpeg',
    'max_width'          => 1280,
    'max_height'         => 720,
    'mp4_crf'            => 23,
    'mp4_maxrate_kbps'   => 2500,
    'webm_crf'           => 32,
    'webm_maxrate_kbps'  => 1800,
    'audio_bitrate_kbps' => 128,
    'strip_metadata'     => true,
);

$mp4 = Command_Builder::mp4('/in.mp4', '/out.mp4', $settings);
$webm = Command_Builder::webm('/in.mp4', '/out.webm', $settings);

$mp4_input_index = array_search('-i', $mp4, true);
$webm_input_index = array_search('-i', $webm, true);
$assert(false !== $mp4_input_index && '/in.mp4' === ($mp4[$mp4_input_index + 1] ?? ''), 'MP4 source must immediately follow the -i input option.');
$assert(false !== $webm_input_index && '/in.mp4' === ($webm[$webm_input_index + 1] ?? ''), 'WebM source must immediately follow the -i input option.');
$assert(! in_array('-autorotate', $mp4, true), 'MP4 command must rely on FFmpeg default autorotation rather than the fragile explicit input flag.');
$assert(! in_array('-autorotate', $webm, true), 'WebM command must rely on FFmpeg default autorotation rather than the fragile explicit input flag.');

$assert(in_array('-map_metadata', $mp4, true), 'MP4 command must strip source metadata.');
$assert(in_array('-map_chapters', $mp4, true), 'MP4 command must strip chapters.');
$assert(in_array('+faststart', $mp4, true), 'MP4 command must enable fast-start indexing.');
$assert(in_array('libx264', $mp4, true), 'MP4 command must use libx264.');
$assert(in_array('libvpx-vp9', $webm, true), 'WebM command must use VP9.');
$assert(in_array('libopus', $webm, true), 'WebM command must use Opus.');
$assert(! in_array('rotate=0', $mp4, true) && ! in_array('rotate=0', $webm, true), 'Generated files must not add replacement rotation tags after metadata stripping.');

$plugin = file_get_contents(dirname(__DIR__) . '/wp-argent-video-processor.php');
$readme = file_get_contents(dirname(__DIR__) . '/readme.txt');
$assert(false !== $plugin && str_contains($plugin, 'Version: 0.1.1'), 'Plugin header version must be 0.1.1.');
$assert(false !== $readme && str_contains($readme, 'Stable tag: 0.1.1'), 'Stable tag must be 0.1.1.');

if ([] !== $failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "All dependency-free tests passed.\n");

// EOF: /home/alan/src/wp-argent-video-processor/tests/run.php
