<?php
/**
 * /home/alan/src/wp-argent-video-processor/tests/smoke-load.php
 */

declare(strict_types=1);

define('ABSPATH', '/tmp/wordpress/');
define('MINUTE_IN_SECONDS', 60);
$GLOBALS['wpdb'] = (object) array('prefix' => 'wp_');

function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
function plugin_dir_url(string $file): string { return 'https://example.test/wp-content/plugins/' . basename(dirname($file)) . '/'; }
function register_activation_hook(string $file, callable $callback): void { unset($file, $callback); }
function register_deactivation_hook(string $file, callable $callback): void { unset($file, $callback); }
function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void { unset($hook, $callback, $priority, $accepted_args); }
function is_admin(): bool { return false; }

require dirname(__DIR__) . '/wp-argent-video-processor.php';

if (! defined('ARGENT_VIDEO_VERSION') || '0.1.0' !== ARGENT_VIDEO_VERSION) {
    fwrite(STDERR, "Plugin smoke load failed.\n");
    exit(1);
}

fwrite(STDOUT, "Plugin smoke load passed.\n");

// EOF: /home/alan/src/wp-argent-video-processor/tests/smoke-load.php
