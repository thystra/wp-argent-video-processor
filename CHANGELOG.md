<!-- /home/alan/src/wp-argent-video-processor/CHANGELOG.md -->
# Changelog

## 0.2.0 - 2026-07-27

- Add adaptive HLS output with 360p, 480p, and 720p H.264/AAC renditions where source resolution permits.
- Use fragmented MP4 initialization/media segments and an adaptive master playlist.
- Add native HLS playback and a pinned hls.js player for Media Source Extensions browsers.
- Preserve current VP9/Opus WebM and H.264/AAC MP4 progressive sources as fallbacks.
- Add a Settings > Argent Video backlog interface for smart processing, adaptive-only additions, and forced full reprocessing.
- Add optional upload-date filtering to backlog operations.
- Add matching `wp argent-video scan --mode=smart|adaptive|all` behavior.
- Add adaptive-only jobs so v0.1.x progressive outputs can gain HLS without being recreated.
- Extend diagnostics with the configured FFmpeg version, required encoders, HLS muxer, fragmented MP4 support, and hls.js status.
- Dynamically use the configured system FFmpeg/FFprobe instead of bundling a media binary.
- Add a real FFmpeg integration test for adaptive playlists, initialization segments, media segments, codecs, rotation normalization, and metadata removal.
- Fetch and vendor the pinned hls.js runtime during CI and tagged release builds.
- Clean incomplete temporary HLS directories when an adaptive encode fails.
- Preserve completed attachment status when an idempotent enqueue request finds an already-current job.

## 0.1.1 - 2026-07-27

- Fix FFmpeg compatibility by relying on default input autorotation instead of passing the explicit `-autorotate` flag.
- Show failed job and attachment details in manual WP-CLI worker output.
- Check required H.264/AAC and VP9/Opus encoders in diagnostics according to the selected profile.
- Add an FFmpeg integration test covering command execution, rotation normalization, codec output, and location-metadata removal.

## 0.1.0 - 2026-07-26

- Queue newly uploaded video attachments.
- Add an idempotent database-backed job queue.
- Add a five-minute lightweight dispatcher compatible with system-invoked WP-Cron.
- Launch a detached, low-priority WP-CLI worker rather than encoding inside WP-Cron.
- Produce configurable VP9/Opus WebM and H.264/AAC MP4 derivatives.
- Strip source metadata and normalize display rotation in generated files.
- Validate codec, dimensions, and duration with FFprobe before atomic installation.
- Substitute processed sources for Gutenberg Video blocks and WordPress video shortcodes.
- Add Media Library status/actions, settings, diagnostics, and WP-CLI commands.
- Add CI and semantic-tag GitHub release ZIP workflow.

<!-- EOF: /home/alan/src/wp-argent-video-processor/CHANGELOG.md -->
