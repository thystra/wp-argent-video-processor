<!-- /home/alan/src/wp-argent-video-processor/README.md -->
# Argent Video Processor

Argent Video Processor is a lightweight WordPress plugin that queues uploaded videos and creates streaming-friendly derivatives on the WordPress server with FFmpeg.

The original attachment remains untouched. Processed copies:

- remove GPS, device, chapter, and other embedded metadata by default;
- normalize stored display rotation into the encoded pixels;
- reduce resolution and bitrate for practical progressive playback;
- place MP4 indexing data at the front of compatibility files with `+faststart`;
- optionally create an open VP9/Opus WebM source ahead of the MP4 fallback;
- replace Video block and WordPress video-shortcode sources only at render time.

## Default profile

The default `dual` profile produces:

1. 720p-bounded VP9/Opus WebM, preferred by supporting browsers.
2. 720p-bounded H.264/AAC MP4, optimized for compatibility and progressive playback.

Only one worker runs per site. FFmpeg inherits a configurable CPU nice level and I/O priority. The recurring WordPress event merely starts a detached WP-CLI worker and returns; it does not encode inside the shared WP-Cron process.

## Requirements

- WordPress 6.4 or newer; tested through WordPress 7.0.
- PHP 8.1 or newer.
- WP-CLI, default `/usr/local/bin/wp`.
- FFmpeg and FFprobe, defaults `/usr/bin/ffmpeg` and `/usr/bin/ffprobe`.
- PHP `proc_open()` for encodes.
- PHP `exec()` for automatic detached dispatch. When `exec()` is disabled, run `wp argent-video worker --once` from the system scheduler.

## WP-CLI

```bash
wp argent-video diagnose
wp argent-video jobs
wp argent-video jobs --status=failed
wp argent-video enqueue 123 --force
wp argent-video worker --once
wp argent-video worker --limit=3
wp argent-video scan --limit=500
```

## Release ZIPs

Push an annotated semantic version tag such as `v0.1.0`. The release workflow validates the versions, lints PHP, runs the dependency-free tests, builds an installable ZIP whose only top-level directory is `wp-argent-video-processor/`, creates `SHA256SUMS`, and attaches both files to the GitHub release.

## Privacy

Metadata removal applies to generated derivatives, not to the original uploaded file. The original remains in the WordPress media library unless the operator removes it separately.

## License

GPL-2.0-or-later.

<!-- EOF: /home/alan/src/wp-argent-video-processor/README.md -->
