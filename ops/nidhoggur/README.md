<!-- /home/alan/src/wp-argent-video-processor/ops/nidhoggur/README.md -->
# nidhoggur operator notes

The plugin is designed to work with the established system-invoked WP-Cron runner without changing `/root/scripts/wordpress-cron-jobs.sh`.

The `wolfandraven` cron pass loads the plugin and runs the due `argent_video_processor_dispatch` event. That callback launches this detached command as the already-correct `wolfandraven` operating user:

```text
/usr/local/bin/wp --path=/var/www/wolfandraven.blog/public_html argent-video worker --once --quiet
```

The launch inherits configured `nice` and `ionice` values. If PHP `exec()` is unavailable, disable automatic dispatch in Settings > Argent Video and add a separate system-scheduled worker invocation rather than inserting FFmpeg into the shared cron script.

Production checks after installation:

```text
sudo -u wolfandraven /usr/local/bin/wp --path=/var/www/wolfandraven.blog/public_html argent-video diagnose
sudo -u wolfandraven /usr/local/bin/wp --path=/var/www/wolfandraven.blog/public_html argent-video jobs
sudo -u wolfandraven /usr/local/bin/wp --path=/var/www/wolfandraven.blog/public_html cron event list --fields=hook,next_run_relative | grep argent_video
```

<!-- EOF: /home/alan/src/wp-argent-video-processor/ops/nidhoggur/README.md -->
