<!-- /home/alan/src/wp-argent-video-processor/AGENTS-PROFILE.md -->
# Alan’s Cross-Project Agent and Operator Preferences

This file contains reusable personal workflow preferences and general host
context. Project-specific architecture, deployment state, paths, and TODO
handoffs remain in each project’s `AGENTS.md`.

Do not store passwords, private keys, certificate contents, API tokens, or
other secrets here.

## Communication and architecture

- Be helpful and conversational. Use dialogue to explore architecture,
  alternatives, risks, and operational consequences.
- Explain the rationale and principles behind recommendations, including
  tradeoffs and considerations Alan may not yet have raised.
- Clearly distinguish verified facts, assumptions, proposed design, and
  production state.
- Do not claim that a patch, package, or deployment succeeded until the
  corresponding output supports that conclusion.

## Commands and host identification

- Before every command block, state the exact computer where it runs:
  `fafnir`, `nidhoggur`, `heimdall`, `hermod`, or another explicitly named
  host.
- Commands should be complete and directly copy-pasteable.
- When a root shell is expected, say so. On `nidhoggur`, Alan often uses
  `sudo -i`; therefore user-owned paths must use `/home/alan/...`, not `~/...`.
- Never confuse a ChatGPT sandbox path such as `/mnt/data/...` with a path on
  one of Alan’s computers.
- When asking Alan to edit a file, always give its full absolute path and the
  host where it exists.

## Generated files and patches

- Prefer versioned applicator/patch scripts that operate against the specified
  checkout or deployment directory.
- Put backups outside the repository working tree.
- Validate syntax, run the relevant focused and full tests, run
  `git diff --check`, and clean package output before release builds.
- Include complete review and release commands:
  `git status`, `git diff`, `git add`, `git commit`, `git push`, and an
  annotated version tag where appropriate.
- When the file format permits comments:
  - place a comment near the top containing the full source path and filename;
  - finish with a commented `EOF` marker containing that path.
- Do not add comments to formats that prohibit them, such as strict JSON.
- Preserve local work and stop on unexpected anchors rather than guessing.

## General host and network layout

### `fafnir`

- Alan’s desktop and normal development workstation.
- Normal user: `alan`.
- Source checkouts are generally below `/home/alan/src/`.
- Browser downloads are in `/home/alan/Downloads/`.
- Build and test here before production deployment.

### `nidhoggur`

- Primary Ubuntu production server and current central Argent Sentinel node.
- Hostname: `nidhoggur.argentwolf.org`.
- Known LAN addresses include `192.168.1.25` and `192.168.1.29`; verify before
  relying on a specific address.
- Runs major self-hosted services, including web, mail, WordPress, Nextcloud,
  and central security/reporting workloads.
- Production changes should be explicit, reversible, and followed by service,
  log, and path verification.
- Root login via SSH is not permitted; login commands should use `alan@nidhoggur.argentwolf.org`. Use `sudo` or `sudo -i` after connecting when root privileges are required.

### `heimdall`

- LAN controller and VPN-related host.
- Hostname: `heimdall.argentwolf.org`.
- Known LAN address: `192.168.1.149`; verify before relying on it.
- May operate as a remote Argent Sentinel node rather than the central server.

### `hermod`

- DigitalOcean VPS and remote/public infrastructure host.
- Hostname: `hermod.argentwolf.org`.
- Treat as a separate remote node with explicit transport and enrollment
  configuration.

## WordPress hosting and scheduled work on `nidhoggur`

- WordPress sites use per-site operating/PHP users. Commands that read or modify a
  site must run as the corresponding site user rather than as `root` unless the
  operation specifically requires root privileges.
- `wolfandraven.blog` uses:
  - document root: `/var/www/wolfandraven.blog/public_html`;
  - per-site PHP/WordPress user: `wolfandraven`;
  - WP-CLI operations should therefore use `sudo -u wolfandraven` with the full
    `--path=/var/www/wolfandraven.blog/public_html` argument.
- Front-end WP-Cron spawning is disabled with `DISABLE_WP_CRON=1` on the five
  established sites: `allaboardacres`, `allaboardbouncers`, `wolfandraven`,
  `lonewolftech`, and `troop20web`.
- System-managed WordPress cron runs every five minutes. The root crontab entry
  for the four central sites is:
  `*/5 * * * * /root/scripts/wordpress-cron-jobs.sh`
- `/root/scripts/wordpress-cron-jobs.sh` handles `allaboardacres`,
  `allaboardbouncers`, `wolfandraven`, and `lonewolftech`. Its verified
  2026-07-26 execution order is serial: it runs `wp cron event run --due-now`
  as each site user; runs WooCommerce Action Scheduler only for
  `allaboardacres` and `allaboardbouncers`; then runs Nextcloud cron as
  `nextcloud`; then runs the Friendica worker as `friendica`.
- The script currently uses `set -uo pipefail` but not `set -e` and does not
  use a whole-script `flock`. Its `run_wp_cron()` function logs WP-Cron
  failures and continues. Long-running plugin callbacks would delay later
  sites, Nextcloud, and Friendica, so FFmpeg and similar expensive work must
  be detached or invoked by a separate worker rather than performed inside
  the shared `wp cron event run --due-now` process.
- The Troop 20 site uses its own `troop20web` crontab entry:
  `*/5 * * * * /usr/bin/flock -n /tmp/troop20opfl-wp-cron.lock /usr/local/bin/wp --path=/home/troop20web/public_html cron event run --due-now --quiet >/dev/null 2>&1`
- There are no active HTTP `wget ... wp-cron.php` jobs in the final topology.
- Do not describe WP-Cron on these sites as dependent on visitor traffic. The
  production scheduler is system-managed. For long-running work, distinguish
  between using WP-Cron to enqueue/dispatch a job and running the expensive
  worker itself; avoid blocking the shared central cron runner without an
  explicit concurrency and locking design.


## System media tooling on `nidhoggur`

- The system FFmpeg and FFprobe binaries at `/usr/bin/ffmpeg` and
  `/usr/bin/ffprobe` were deliberately upgraded during Mastodon maintenance to
  a current security-patched build in response to a CVE.
- Server-side media applications should use the system-managed binaries,
  inspect their actual version and capabilities dynamically, and avoid bundling
  or pinning a private FFmpeg build unless that architecture is explicitly
  reconsidered.
- Argent Video Processor v0.1.1 is production-validated on
  `wolfandraven.blog`: attachment 6878 completed VP9/Opus and H.264/AAC
  processing, browser range delivery worked, and a real DSL test played for
  more than two minutes without pauses.
- Argent Video Processor v0.2.0 adds adaptive HLS and an administrator backlog
  queue while retaining the one-at-a-time detached worker model.
- Argent Video Processor v0.2.1 fixes release-player vendoring by validating
  the exact npm package and runtime `Hls.version` rather than relying on a banner
  string in the minified JavaScript file.
- Argent Video Processor v0.2.2 fixes release-license validation by checking
  the exact npm package SPDX identity and substantive Apache-2.0 license text,
  then packaging the license shipped by that verified package rather than
  requiring byte-for-byte equality with a repository snapshot.

## WordPress plugin packaging and releases

- WordPress plugin repositories should include a GitHub Actions release workflow
  triggered by version tags such as `v0.1.0`.
- The workflow should validate the plugin version, lint/test the source, build an
  installable ZIP, generate checksums, and attach the ZIP to the corresponding
  GitHub Release.
- The release ZIP must contain exactly one top-level directory using the stable
  plugin slug. For the Argent video project, that directory is
  `wp-argent-video-processor/`; do not rely on GitHub's automatically generated
  source archive as the WordPress installation package.
- Exclude repository-only material such as `.git`, `.github`, tests, local
  development files, prior build output, and agent handoff files unless a file is
  intentionally required at runtime.
- Prefer installing and upgrading released plugin ZIPs through the normal
  WordPress web UI so the tested workflow matches future deployment across the
  other WordPress sites.

## General infrastructure conventions

- Publicly routed residential IPv6 may be used on the LAN; ULA ranges alone
  are not a complete local-network allowlist.
- Stable service names are preferred over embedding a particular current
  server hostname in clients.
- Use restricted service accounts, mTLS, narrowly scoped SSH/rsync, and
  explicit drop directories instead of broad shared permissions.
- Keep sanitization and presentation tiers separate from raw security data.
- Project-specific host paths, package versions, and service behavior belong
  in that project’s `AGENTS.md`.

<!-- EOF: /home/alan/src/wp-argent-video-processor/AGENTS-PROFILE.md -->
