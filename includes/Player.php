<?php
/**
 * File: includes/Player.php
 */

declare(strict_types=1);

namespace ArgentVideo;

final class Player
{
    public const HLS_JS_VERSION = '1.6.16';
    private bool $registered = false;

    public function enqueue(): void
    {
        if (! $this->registered) {
            $this->register();
        }
        wp_enqueue_script('argent-video-player');
    }

    public static function has_local_hls_js(): bool
    {
        $path = ARGENT_VIDEO_DIR . 'assets/vendor/hls.min.js';
        return is_file($path) && filesize($path) > 100000;
    }

    private function register(): void
    {
        $dependencies = array();
        if (self::has_local_hls_js()) {
            wp_register_script(
                'argent-video-hls-js',
                ARGENT_VIDEO_URL . 'assets/vendor/hls.min.js',
                array(),
                self::HLS_JS_VERSION,
                array('in_footer' => true, 'strategy' => 'defer')
            );
            $dependencies[] = 'argent-video-hls-js';
        }

        wp_register_script(
            'argent-video-player',
            ARGENT_VIDEO_URL . 'assets/js/argent-video-player.js',
            $dependencies,
            ARGENT_VIDEO_VERSION,
            array('in_footer' => true, 'strategy' => 'defer')
        );
        $this->registered = true;
    }
}

// EOF: includes/Player.php
