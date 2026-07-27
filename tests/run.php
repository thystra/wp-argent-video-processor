<?php
/**
 * /home/alan/src/wp-argent-video-processor/tests/run.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/Output_Namer.php';
require_once dirname(__DIR__) . '/includes/Command_Builder.php';
require_once dirname(__DIR__) . '/includes/Probe.php';
require_once dirname(__DIR__) . '/includes/Adaptive_HLS.php';

use ArgentVideo\Adaptive_HLS;
use ArgentVideo\Command_Builder;
use ArgentVideo\Output_Namer;
use ArgentVideo\Probe;

$failures = array();
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$source = '/srv/uploads/video.phone.mp4';
$assert('/srv/uploads/video.phone-argent-720p.mp4' === Output_Namer::derivative($source, '720p', 'mp4'), 'Progressive output naming failed.');
$assert('/srv/uploads/video.phone-argent-hls' === Output_Namer::adaptive_directory($source), 'HLS directory naming failed.');
$temporary = Output_Namer::temporary('/srv/uploads/video-argent-720p.mp4');
$assert(str_ends_with($temporary, '.mp4') && str_contains($temporary, '.tmp-'), 'Temporary files must retain the container extension and include a token.');
$assert(str_contains(Output_Namer::temporary_directory('/srv/uploads/video-argent-hls'), '.tmp-'), 'Temporary HLS directories must include a token.');

$settings = array(
    'ffmpeg_path' => '/usr/bin/ffmpeg', 'max_width' => 1280, 'max_height' => 720,
    'mp4_crf' => 23, 'mp4_maxrate_kbps' => 2500, 'webm_crf' => 32,
    'webm_maxrate_kbps' => 1800, 'audio_bitrate_kbps' => 128,
    'strip_metadata' => true, 'hls_segment_seconds' => 6,
    'hls_360_video_kbps' => 650, 'hls_480_video_kbps' => 1100,
    'hls_720_video_kbps' => 2200, 'hls_audio_bitrate_kbps' => 96,
    'hls_preset' => 'medium',
);
$mp4 = Command_Builder::mp4('/in.mp4', '/out.mp4', $settings);
$webm = Command_Builder::webm('/in.mp4', '/out.webm', $settings);
$source_probe = array('streams' => array(array('codec_type' => 'video', 'width' => 1920, 'height' => 1080)));
$renditions = Adaptive_HLS::renditions($settings, $source_probe);
$hls = Command_Builder::hls('/in.mp4', '/hls/360p/index.m3u8', '/hls/360p/segment-%05d.m4s', $settings, $renditions[0], true);

foreach (array($mp4, $webm, $hls) as $command) {
    $input_index = array_search('-i', $command, true);
    $assert(false !== $input_index && '/in.mp4' === ($command[$input_index + 1] ?? ''), 'The source must immediately follow -i.');
    $assert(! in_array('-autorotate', $command, true), 'Commands must rely on FFmpeg default autorotation.');
    $assert(in_array('-map_metadata', $command, true) && in_array('-map_chapters', $command, true), 'Commands must strip source metadata and chapters.');
}
$assert(in_array('+faststart', $mp4, true), 'MP4 must enable fast-start indexing.');
$assert(in_array('libvpx-vp9', $webm, true) && in_array('libopus', $webm, true), 'WebM must use VP9/Opus.');
$assert(in_array('hls', $hls, true) && in_array('fmp4', $hls, true), 'Adaptive output must use the HLS muxer with fragmented MP4 segments.');
$assert((bool) array_filter($hls, static fn(string $value): bool => str_ends_with($value, 'segment-%05d.m4s')), 'HLS command must use .m4s media segments.');
$assert(3 === count($renditions), 'A 1080p source should receive 360p, 480p, and 720p renditions.');
$master_test = sys_get_temp_dir() . '/argent-video-master-' . bin2hex(random_bytes(4)) . '.m3u8';
Adaptive_HLS::write_master($master_test, array($renditions[0]));
$master_contents = (string) file_get_contents($master_test);
@unlink($master_test);
$assert(str_contains($master_contents, 'CODECS="avc1.640028,mp4a.40.2"'), 'HLS master must declare H.264/AAC codecs.');
$assert(! str_contains($master_contents, 'NAME='), 'HLS stream information must not use the media-playlist NAME attribute.');

$rotated = array('streams' => array(array(
    'codec_type' => 'video', 'width' => 1920, 'height' => 1080,
    'side_data_list' => array(array('rotation' => 90)),
)));
$assert(array(1080, 1920) === Probe::display_dimensions($rotated), 'Display dimensions must account for 90-degree rotation.');

$plugin = file_get_contents(dirname(__DIR__) . '/wp-argent-video-processor.php');
$readme = file_get_contents(dirname(__DIR__) . '/readme.txt');
$assert(false !== $plugin && str_contains($plugin, 'Version: 0.2.1'), 'Plugin header version must be 0.2.1.');
$assert(false !== $readme && str_contains($readme, 'Stable tag: 0.2.1'), 'Stable tag must be 0.2.1.');

if ([] !== $failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}
fwrite(STDOUT, "All dependency-free tests passed.\n");

// EOF: /home/alan/src/wp-argent-video-processor/tests/run.php
