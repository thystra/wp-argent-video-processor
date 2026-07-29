# ArgentWolf Video Processor agent instructions

This file contains public, project-specific guidance for contributors and coding
agents. Private hostnames, user names, deployment paths, and production state do
not belong in this repository.

## Canonical identity

- Product name: `ArgentWolf Video Processor`
- GitHub repository: `https://github.com/thystra/wp-argentwolf-video-processor`
- WordPress.org target slug: `argentwolf-video-processor`
- Main plugin file: `argentwolf-video-processor.php`
- Text domain: `argentwolf-video-processor`
- PHP namespace retained for compatibility: `ArgentVideo`
- WP-CLI command retained for compatibility: `wp argent-video`
- Current submission-preparation version: `0.3.0`

Do not shorten the public product name to “Argent Video Processor.”

## Compatibility invariants

The public rename must not reset or migrate established installation data without
a separately reviewed migration. Retain the existing:

- `argent_video_processor_*` options;
- `_argent_video_*` attachment metadata;
- `argent_video_jobs` database table;
- `argent_video_*` hooks and cron identifiers;
- `wp argent-video` CLI command;
- Settings page slug `argent-video-processor`.

The directory and main-file rename requires an explicit upgrade test from the
legacy `wp-argent-video-processor/wp-argent-video-processor.php` basename.

## Architecture invariants

- Preserve every original WordPress attachment.
- Never run FFmpeg inside the recurring WP-Cron callback or an administrator web
  request.
- The recurring event may only inspect the queue and launch a detached worker.
- Run at most one worker per WordPress site.
- Claim jobs atomically and recover stale jobs safely.
- Build output in temporary locations and validate it before atomic installation.
- Strip generated-file metadata when enabled, but do not claim the original was
  sanitized.
- Keep progressive fallbacks when adaptive HLS is enabled.
- Use administrator-configured system FFmpeg, FFprobe, and WP-CLI binaries. Do
  not bundle FFmpeg.
- Treat shell arguments as untrusted and quote or validate them before execution.

## Source layout

- `argentwolf-video-processor.php`: metadata, constants, dependency loading, and
  bootstrap only.
- `includes/`: runtime services.
- `assets/js/`: locally maintained browser player integration.
- `assets/vendor/`: generated, pinned hls.js release assets.
- `build/`: deterministic release tooling.
- `tests/`: dependency-free, open_basedir, smoke, vendor, and FFmpeg tests.
- `.github/workflows/`: CI and tagged-release workflows.
- `ARCHITECTURE.md`: design and invariants.
- `TODO.md`: milestones and release gates.

Prefer focused classes over adding substantial logic to the main plugin file.

## Editing and patching

- Require a clean worktree before broad transformations.
- Back up outside the checkout, preferably under
  `~/src/backups/wp-argentwolf-video-processor-backups/`.
- Build and validate a prospective tree before modifying the checkout.
- Prefer complete-file installation or reviewed unified patches over global
  substring-count anchors.
- Do not add private operator information to public documentation.
- Do not commit generated hls.js files or release ZIPs.
- Preserve unexpected local work and stop rather than guessing.

## Validation

Before commit:

```bash
find . -type f -name '*.php' -not -path './dist/*' -print0 |
  sort -z |
  xargs -0 -n1 php -l

php tests/run.php
php -d open_basedir="${PWD}:/tmp" tests/open-basedir.php
php tests/smoke-load.php
php tests/ffmpeg-integration.php
bash tests/vendor-fetch.sh
node --check assets/js/argent-video-player.js
git diff --check
```

Build the installable ZIP with:

```bash
bash build/build-plugin.sh 0.3.0
```

The release ZIP must contain one top-level `argentwolf-video-processor/`
directory and only runtime files, `LICENSE`, and `readme.txt`. It must contain
the pinned hls.js runtime, license, version, and checksum records.

## WordPress.org release gate

Before submission:

- run the official Plugin Check plugin against the exact release ZIP;
- resolve or document every finding;
- test a clean installation;
- test an upgrade from version `0.2.3`, including the basename transition;
- verify settings, queue rows, attachment metadata, generated outputs, cron
  scheduling, CLI commands, rendering, and uninstall behavior;
- confirm no custom update checker or telemetry is present;
- confirm all external requirements and privacy behavior are disclosed;
- verify the settings page and plugin action links point to the GitHub project;
- inspect the final package manifest and checksum;
- tag only after the reviewed commit is pushed.

GitHub publication, WordPress.org submission, WordPress.org approval, staging
installation, and production deployment are separate states.
