=== Argent Video Processor ===
Contributors: thystra
Tags: video, ffmpeg, webm, streaming, media
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Queues uploaded videos and creates privacy-cleaned, streaming-friendly WebM and MP4 derivatives with a low-priority FFmpeg worker.

== Description ==

Argent Video Processor preserves each original WordPress video attachment and creates smaller derivatives suitable for progressive browser playback.

The default profile creates an open VP9/Opus WebM source and an H.264/AAC MP4 fallback. The MP4 derivative uses fast-start indexing. Derivatives strip embedded GPS and device metadata by default and normalize rotation metadata into the encoded pixels.

The plugin's five-minute WordPress event only launches a detached WP-CLI worker. FFmpeg does not run inside the shared WP-Cron callback.

== Installation ==

1. Install FFmpeg, FFprobe, and WP-CLI on the WordPress server.
2. Upload the release ZIP through Plugins > Add New > Upload Plugin.
3. Activate Argent Video Processor.
4. Open Settings > Argent Video and run diagnostics.
5. Upload a video normally or queue an existing video from the Media Library.

== Frequently Asked Questions ==

= Are original videos deleted? =

No. Version 0.1.0 always preserves the original attachment.

= Is all processing performed in PHP? =

No. WordPress manages the queue, while an external low-priority FFmpeg process performs the encode through a detached WP-CLI worker.

= Which codecs are used? =

The default profile uses VP9/Opus WebM first and H.264/AAC MP4 as a compatibility fallback. Either output can be selected by itself.

= Does the plugin remove location metadata? =

Yes, generated derivatives strip metadata by default. The source attachment is not modified.

== Changelog ==

= 0.1.0 =
* Initial queue, detached worker, FFmpeg processing, validation, metadata stripping, render substitution, admin controls, WP-CLI commands, and tag release workflow.
