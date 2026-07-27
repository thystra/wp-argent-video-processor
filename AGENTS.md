<!-- /home/alan/src/wp-argent-video-processor/AGENTS.md -->
# Argent Video Processor project state

Read `AGENTS-PROFILE.md` first for Alan's cross-project operating conventions.

## Repository and release

- GitHub: `https://github.com/thystra/wp-argent-video-processor`
- Development checkout on `fafnir`: `/home/alan/src/wp-argent-video-processor`
- Stable WordPress plugin slug and release ZIP root: `wp-argent-video-processor`
- Initial version: `0.1.0`
- Normal deployment method: download the tag-built GitHub release ZIP and install or upgrade it through the WordPress web UI.

## Initial production target

- Host: `nidhoggur`
- Site: `wolfandraven.blog`
- WordPress path: `/var/www/wolfandraven.blog/public_html`
- Site/PHP user: `wolfandraven`
- WP-CLI commands must run as `wolfandraven`, not root.

## Existing scheduler topology

Root runs `/root/scripts/wordpress-cron-jobs.sh` every five minutes. The script:

1. runs `wp cron event run --due-now` serially as each site's PHP user for `allaboardacres`, `allaboardbouncers`, `wolfandraven`, and `lonewolftech`;
2. additionally runs WooCommerce Action Scheduler for the first two sites;
3. runs Nextcloud cron as `nextcloud`;
4. runs the Friendica worker as `friendica`.

Because those tasks are serial, an Argent Video cron callback must not run FFmpeg synchronously. Version 0.1.0's recurring callback checks the queue, starts a detached low-priority `wp argent-video worker --once` process as the current site user, and returns immediately.

## Version 0.1.0 behavior

- `add_attachment` queues local `video/*` attachments.
- The original attachment is always preserved.
- Default output profile is `dual`:
  - VP9/Opus WebM, bounded to 1280x720, preferred in rendered output;
  - H.264/AAC MP4, bounded to 1280x720, `+faststart`, compatibility fallback.
- Generated derivatives strip source metadata, including GPS/device metadata, by default.
- FFmpeg autorotation is enabled and generated rotation metadata is reset.
- One site worker runs at a time; job claims are atomic and stale processing jobs can be recovered.
- Temporary outputs are validated with FFprobe before atomic rename.
- Existing block content is not rewritten. `core/video` and video shortcode output is filtered at render time.
- Deleting an attachment deletes known derivatives and its job row.
- Uninstall preserves data unless `ARGENT_VIDEO_REMOVE_DATA_ON_UNINSTALL` is explicitly true.

## Known initial limitations

- No adaptive HLS/DASH ladder yet; version 0.1.0 uses progressive WebM/MP4 derivatives.
- No percent-complete UI and no signal-based cancellation of a currently running FFmpeg process.
- Automatic dispatch requires PHP `exec()`. Encoding requires `proc_open()`.
- HTTP byte-range behavior remains a server/proxy concern and is not changed by this plugin.
- Version 0.1.0 does not delete or relocate original videos.

## Release validation

Before a release:

- run PHP lint across runtime and test files;
- run `php tests/run.php`;
- run `git diff --check`;
- run `bash build/build-plugin.sh 0.1.0` or the current version;
- verify the ZIP has exactly one `wp-argent-video-processor/` top-level directory;
- inspect `git status` and `git diff` before commit;
- push an annotated `vX.Y.Z` tag only after the branch commit is pushed.

<!-- EOF: /home/alan/src/wp-argent-video-processor/AGENTS.md -->
