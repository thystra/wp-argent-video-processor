<!-- /home/alan/src/wp-argent-video-processor/README.md -->
# Argent Video Processor

Argent Video Processor is a lightweight WordPress plugin that queues uploaded videos and creates privacy-cleaned, streaming-friendly derivatives on the WordPress server with the system FFmpeg and FFprobe binaries.

The original attachment remains untouched. Processed copies:

- remove GPS, device, chapter, and other embedded metadata by default;
- normalize stored display rotation into the encoded pixels;
- reduce resolution and bitrate for practical progressive playback;
- place MP4 indexing data at the front of compatibility files with `+faststart`;
- optionally create an open VP9/Opus WebM source ahead of the MP4 fallback;
- create an adaptive HLS ladder with fragmented MP4 segments;
- replace Video block and WordPress video-shortcode sources only at render time.

## Default output

The default configuration produces:

1. An adaptive HLS master playlist with 360p, 480p, and 720p H.264/AAC renditions where the source resolution permits. The browser can switch renditions as available bandwidth changes.
2. A 720p-bounded VP9/Opus WebM progressive fallback.
3. A 720p-bounded H.264/AAC MP4 progressive fallback.

The HLS player uses native browser HLS support where available and a locally vendored, pinned hls.js build elsewhere. Progressive files remain in the rendered `<video>` element as a fallback when adaptive playback is unavailable.

Only one worker runs per site. FFmpeg inherits a configurable CPU nice level and I/O priority. The recurring WordPress event merely starts a detached WP-CLI worker and returns; it does not encode inside the shared WP-Cron process.

## Process existing videos

Settings > Argent Video includes a backlog section with three operations:

- **Smart queue:** process videos that have no derivatives and add HLS to completed videos without recreating valid progressive files.
- **Add adaptive HLS only:** add HLS to completed videos that do not already have it.
- **Force reprocess all:** recreate every selected video using the current settings.

An optional upload-date range can limit the backlog. The web request only adds jobs to the database queue. The existing detached one-at-a-time worker performs the actual encoding.

## Requirements

- WordPress 6.4 or newer; tested through WordPress 7.0.
- PHP 8.1 or newer.
- WP-CLI, default `/usr/local/bin/wp`.
- A current security-maintained system FFmpeg and FFprobe, defaults `/usr/bin/ffmpeg` and `/usr/bin/ffprobe`.
- FFmpeg encoders: `libx264`, `aac`, plus `libvpx-vp9` and `libopus` when the open progressive profile is enabled.
- FFmpeg HLS muxer with fragmented MP4 segment support when adaptive HLS is enabled.
- PHP `proc_open()` for encodes.
- PHP `exec()` for automatic detached dispatch. When `exec()` is disabled, run `wp argent-video worker --once` from the system scheduler.

The plugin does not bundle or pin FFmpeg. Diagnostics inspect the configured system binaries and required capabilities at runtime.

## WP-CLI

```bash
wp argent-video diagnose
wp argent-video jobs
wp argent-video jobs --status=failed
wp argent-video enqueue 123 --force
wp argent-video scan --mode=smart
wp argent-video scan --mode=adaptive
wp argent-video scan --mode=all --after=2026-01-01 --through=2026-07-31
wp argent-video worker --once
wp argent-video worker --limit=3
```

## Release ZIPs

Push an annotated semantic version tag such as `v0.2.0`. The release workflow validates versions, lints PHP, runs dependency-free and real FFmpeg tests, fetches the pinned hls.js player, builds an installable ZIP whose only top-level directory is `wp-argent-video-processor/`, creates `SHA256SUMS`, and attaches both files to the GitHub release.

Use the ZIP attached to the GitHub Release, not GitHub's automatically generated source archives.

## Privacy

Metadata removal applies to generated progressive derivatives and adaptive renditions, not to the original uploaded file. The original remains in the WordPress media library unless the operator removes it separately.

## License

GPL-2.0-or-later. The distributed hls.js runtime is provided under its Apache-2.0 license in `assets/vendor/hls.LICENSE`.

<!-- EOF: /home/alan/src/wp-argent-video-processor/README.md -->
