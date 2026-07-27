=== Argent Video Processor ===
Contributors: thystra
Tags: video, ffmpeg, hls, adaptive streaming, webm, media
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Queues uploaded videos and creates privacy-cleaned adaptive HLS and progressive streaming derivatives with a low-priority FFmpeg worker.

== Description ==

Argent Video Processor preserves each original WordPress video attachment and creates smaller derivatives suitable for browser playback on connections ranging from slow DSL to broadband.

The default configuration creates an adaptive HLS ladder at 360p, 480p, and 720p where the source resolution permits. It also creates an open VP9/Opus WebM progressive source and an H.264/AAC MP4 fallback. The MP4 derivative uses fast-start indexing. All generated outputs strip embedded GPS and device metadata by default and normalize rotation metadata into the encoded pixels.

Native HLS is used where the browser supports it. Other compatible browsers use the locally bundled hls.js player. Progressive WebM and MP4 sources remain available as fallbacks.

The plugin's five-minute WordPress event only launches a detached WP-CLI worker. FFmpeg does not run inside the shared WP-Cron callback. Backlog jobs and new uploads are processed one video at a time.

== Installation ==

1. Install a current security-maintained FFmpeg, FFprobe, and WP-CLI on the WordPress server.
2. Upload the tagged-release ZIP through Plugins > Add New > Upload Plugin.
3. Activate Argent Video Processor.
4. Open Settings > Argent Video and review diagnostics.
5. Upload a video normally or use Process existing videos to queue the current media backlog.

== Frequently Asked Questions ==

= Are original videos deleted? =

No. Argent Video Processor always preserves the original attachment.

= What does adaptive streaming do? =

The plugin produces multiple HLS renditions. The player can move between 360p, 480p, and 720p as available bandwidth and player size change.

= Can I process videos already in the Media Library? =

Yes. Settings > Argent Video provides Smart queue, Add adaptive HLS only, and Force reprocess all operations, with an optional upload-date range.

= Is all processing performed in PHP? =

No. WordPress manages the queue, while an external low-priority FFmpeg process performs the encode through a detached WP-CLI worker.

= Which codecs are used? =

Adaptive HLS uses H.264/AAC fragmented MP4 segments for broad browser compatibility. The default progressive fallbacks use VP9/Opus WebM first and H.264/AAC MP4 second. Either progressive output can be selected by itself.

= Does the plugin bundle FFmpeg? =

No. It uses the configured system FFmpeg and FFprobe binaries and checks their version, encoders, HLS muxer, and fragmented MP4 support in diagnostics.

= Does the plugin remove location metadata? =

Yes, generated derivatives and HLS renditions strip metadata by default. The source attachment is not modified.

== Changelog ==

= 0.2.2 =
* Fix release ZIP builds when the exact npm package license text differs from the repository snapshot.
* Validate the package SPDX identity and substantive Apache-2.0 text, then ship the package-provided license.
* Keep all HLS.js release assets generated and untracked, and remove them from the source tree after packaging.

= 0.2.1 =
* Fix release ZIP builds by validating the exact hls.js npm package and its runtime version instead of searching the minified file for a human-readable banner.
* Include the vendored player version and SHA-256 record in release packages.
* Add a regression test for HLS.js vendoring.

= 0.2.0 =
* Add adaptive HLS with 360p, 480p, and 720p H.264/AAC fragmented MP4 renditions where source resolution permits.
* Add native-HLS playback plus a pinned hls.js player, while retaining progressive WebM/MP4 fallbacks.
* Add a WordPress admin backlog interface for smart processing, adaptive-only additions, or forced reprocessing of existing videos.
* Add upload-date filtering and corresponding `wp argent-video scan` modes.
* Add system FFmpeg version, encoder, HLS muxer, fragmented MP4, and player diagnostics.
* Add real FFmpeg HLS integration tests and release-time hls.js vendoring.

= 0.1.1 =
* Fix FFmpeg compatibility by relying on default input autorotation instead of passing the explicit `-autorotate` flag.
* Report failed job and attachment details in manual WP-CLI worker output.
* Check the encoders required by the configured profile in diagnostics.
* Add a real FFmpeg integration test covering rotation normalization and location-metadata removal.

= 0.1.0 =
* Initial queue, detached worker, FFmpeg processing, validation, metadata stripping, render substitution, admin controls, WP-CLI commands, and tag release workflow.
