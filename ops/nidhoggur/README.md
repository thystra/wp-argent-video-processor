<!-- /home/alan/src/wp-argent-video-processor/ops/nidhoggur/README.md -->
# nidhoggur operator notes

The plugin is designed to work with the established system-invoked WP-Cron runner without changing `/root/scripts/wordpress-cron-jobs.sh`.

The `wolfandraven` cron pass loads the plugin and runs the due `argent_video_processor_dispatch` event. That callback launches this detached command as the already-correct `wolfandraven` operating user:

```text
/usr/local/bin/wp --path=/var/www/wolfandraven.blog/public_html argent-video worker --once --quiet
```

The launch inherits configured `nice` and `ionice` values. If PHP `exec()` is unavailable, disable automatic dispatch in Settings > Argent Video and add a separate system-scheduled worker invocation rather than inserting FFmpeg into the shared cron script.

Use the system-managed `/usr/bin/ffmpeg` and `/usr/bin/ffprobe`. They were upgraded during Mastodon maintenance to a current security-patched build. The plugin must diagnose capabilities dynamically and must not install or bundle a second FFmpeg build.

Production checks after installation:

```text
sudo -u wolfandraven /usr/local/bin/wp --path=/var/www/wolfandraven.blog/public_html argent-video diagnose
sudo -u wolfandraven /usr/local/bin/wp --path=/var/www/wolfandraven.blog/public_html argent-video jobs
sudo -u wolfandraven /usr/local/bin/wp --path=/var/www/wolfandraven.blog/public_html cron event list --fields=hook,next_run_relative | grep argent_video
```

For the v0.1.1 production attachment, the v0.2.0 Smart queue should create an `adaptive-only` job: it preserves the validated WebM and MP4 files and adds only the HLS directory. Older videos without derivatives receive the current full profile.

After an HLS job completes, verify that the master playlist and rendition trees exist as `wolfandraven`:

```text
sudo -u wolfandraven find /var/www/wolfandraven.blog/public_html/wp-content/uploads \
  -type f \
  \( -name 'master.m3u8' -o -name 'index.m3u8' -o -name '*.m4s' \) \
  -printf '%s %p\n'
```

<!-- EOF: /home/alan/src/wp-argent-video-processor/ops/nidhoggur/README.md -->
