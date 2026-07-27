<!-- /home/alan/src/wp-argent-video-processor/CHANGELOG.md -->
# Changelog

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
