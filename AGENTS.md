<!-- /home/alan/src/wp-argent-video-processor/AGENTS.md -->
# Argent Video Processor project state

Read `AGENTS-PROFILE.md` first for Alan's cross-project operating conventions.

## Repository and release

- GitHub: `https://github.com/thystra/wp-argent-video-processor`
- Development checkout on `fafnir`: `/home/alan/src/wp-argent-video-processor`
- Stable WordPress plugin slug and release ZIP root: `wp-argent-video-processor`
- Initial version: `0.1.0`
- Last production-validated version: `0.1.1`
- Current development version: `0.2.0`
- Normal deployment method: download the tag-built GitHub release ZIP and install or upgrade it through the WordPress web UI.
- Tagged release builds must vendor the pinned hls.js runtime; do not use GitHub's automatically generated source archive as the WordPress package.
- hls.js is pinned to exact stable version `1.6.16`. Do not use a broad `@1` range or canary packages; specific 1.7.0 alpha-canary package versions were reported as malicious in 2026.

## Production target and validation

- Host: `nidhoggur`
- Site: `wolfandraven.blog`
- WordPress path: `/var/www/wolfandraven.blog/public_html`
- Site/PHP user: `wolfandraven`
- WP-CLI commands must run as `wolfandraven`, not root.
- Attachment `6878`, `20260725_160400.mp4`, completed the first full v0.1.1 dual-profile production conversion.
- Browser validation confirmed the VP9 derivative was selected and served with HTTP 206 byte ranges.
- The real-world DSL test played for more than two minutes without pauses and materially improved the original poor-playback complaint.

## System FFmpeg state

- Use `/usr/bin/ffmpeg` and `/usr/bin/ffprobe` by default.
- These are system-managed binaries that were deliberately upgraded during Mastodon maintenance to a current security-patched build in response to a CVE.
- Do not bundle or pin an FFmpeg binary in this plugin unless that architecture is explicitly reconsidered.
- Detect the actual installed version, encoders, HLS muxer, and fragmented MP4 support dynamically.
- Required default encoders are `libx264`, `aac`, `libvpx-vp9`, and `libopus`.

## Existing scheduler topology

Root runs `/root/scripts/wordpress-cron-jobs.sh` every five minutes. The script:

1. runs `wp cron event run --due-now` serially as each site's PHP user for `allaboardacres`, `allaboardbouncers`, `wolfandraven`, and `lonewolftech`;
2. additionally runs WooCommerce Action Scheduler for the first two sites;
3. runs Nextcloud cron as `nextcloud`;
4. runs the Friendica worker as `friendica`.

Because those tasks are serial, an Argent Video cron callback must not run FFmpeg synchronously. The recurring callback checks the queue, starts a detached low-priority `wp argent-video worker --once` process as the current site user, and returns immediately.

## v0.2.0 behavior

- `add_attachment` queues local `video/*` attachments.
- The original attachment is always preserved.
- Default progressive profile remains `dual`:
  - VP9/Opus WebM, bounded to 1280x720, preferred progressive source;
  - H.264/AAC MP4, bounded to 1280x720, `+faststart`, compatibility fallback.
- Adaptive HLS is enabled by default:
  - 360p, 480p, and 720p H.264/AAC renditions where source resolution permits;
  - fragmented MP4 initialization and `.m4s` media segments;
  - a master playlist used by native HLS or the pinned hls.js player.
- Progressive sources remain in the video element if HLS is unavailable.
- Generated outputs strip source metadata, including GPS/device metadata, by default.
- FFmpeg default autorotation normalizes display orientation into encoded pixels.
- One site worker runs at a time; job claims are atomic and stale processing jobs can be recovered.
- Temporary outputs and HLS trees are validated before atomic installation.
- Existing block content is not rewritten. `core/video` and video-shortcode output is filtered at render time.
- Deleting an attachment deletes known progressive derivatives, its HLS directory, and its job row.
- Uninstall preserves data unless `ARGENT_VIDEO_REMOVE_DATA_ON_UNINSTALL` is explicitly true.

## Backlog behavior

Settings > Argent Video exposes:

- **Smart queue:** process videos with no outputs; for videos with progressive outputs but no HLS, queue `adaptive-only` jobs.
- **Add adaptive HLS only:** queue only completed videos with progressive output and no HLS.
- **Force reprocess all:** recreate all outputs using current settings.
- Optional upload-date limits.

Backlog actions only populate the existing one-at-a-time queue. They do not run FFmpeg in the web request. CLI equivalents are `wp argent-video scan --mode=smart|adaptive|all`.

## Known limitations

- No percent-complete UI and no signal-based cancellation of a currently running FFmpeg process.
- Automatic dispatch requires PHP `exec()`. Encoding requires `proc_open()`.
- HTTP range and correct `.m3u8`, `.m4s`, and `.mp4` content delivery remain web-server/proxy concerns.
- HLS uses H.264/AAC for client compatibility even when the selected progressive profile is open-only.
- HLS renditions are encoded sequentially in v0.2.0, then the next queued video begins.
- The plugin does not delete or relocate original videos.

## Release validation

Before a release:

- run PHP lint across runtime and test files;
- run `php tests/run.php`;
- run `php tests/smoke-load.php`;
- run `php tests/ffmpeg-integration.php`;
- run `node --check assets/js/argent-video-player.js`;
- run `git diff --check`;
- run `bash build/fetch-hls-js.sh`;
- run `bash build/build-plugin.sh <current-version>`;
- verify the ZIP has exactly one `wp-argent-video-processor/` top-level directory;
- verify the ZIP contains `assets/vendor/hls.min.js` and its license;
- inspect `git status` and `git diff` before commit;
- push an annotated `vX.Y.Z` tag only after the branch commit is pushed.

<!-- EOF: /home/alan/src/wp-argent-video-processor/AGENTS.md -->
