<?php
/**
 * /home/alan/src/wp-argent-video-processor/includes/Admin.php
 */

declare(strict_types=1);

namespace ArgentVideo;

use RuntimeException;

final class Admin
{
    public function __construct(
        private readonly Job_Repository $jobs,
        private readonly Queue $queue,
        private readonly Worker_Launcher $launcher,
        private readonly Diagnostics $diagnostics
    ) {
    }

    public function register(): void
    {
        register_setting(
            'argent_video_processor',
            Settings::OPTION,
            array('sanitize_callback' => array(Settings::class, 'sanitize'))
        );
    }

    public function menu(): void
    {
        add_options_page(
            __('Argent Video Processor', 'wp-argent-video-processor'),
            __('Argent Video', 'wp-argent-video-processor'),
            'manage_options',
            'argent-video-processor',
            array($this, 'page')
        );
    }

    /** @param array<string, string> $columns
     *  @return array<string, string>
     */
    public function media_columns(array $columns): array
    {
        $columns['argent_video_status'] = __('Video processing', 'wp-argent-video-processor');
        return $columns;
    }

    public function media_column(string $column, int $attachment_id): void
    {
        if ('argent_video_status' !== $column || ! str_starts_with((string) get_post_mime_type($attachment_id), 'video/')) {
            return;
        }

        $status = (string) get_post_meta($attachment_id, '_argent_video_status', true);
        if ('' === $status) {
            $status = 'not queued';
        }

        echo '<strong>' . esc_html(ucfirst($status)) . '</strong>';
        $error = (string) get_post_meta($attachment_id, '_argent_video_last_error', true);
        if ('' !== $error) {
            echo '<br><span class="description" title="' . esc_attr($error) . '">' . esc_html(wp_trim_words($error, 10)) . '</span>';
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=argent_video_queue_attachment&attachment_id=' . $attachment_id),
            'argent_video_queue_' . $attachment_id
        );
        echo '<br><a href="' . esc_url($url) . '">' . esc_html__('Queue or reprocess', 'wp-argent-video-processor') . '</a>';

        if (in_array($status, array('queued', 'failed'), true)) {
            $cancel = wp_nonce_url(
                admin_url('admin-post.php?action=argent_video_cancel_attachment&attachment_id=' . $attachment_id),
                'argent_video_cancel_' . $attachment_id
            );
            echo ' | <a href="' . esc_url($cancel) . '">' . esc_html__('Cancel', 'wp-argent-video-processor') . '</a>';
        }
    }

    public function queue_action(): void
    {
        if (! current_user_can('upload_files')) {
            wp_die(esc_html__('You are not allowed to process media.', 'wp-argent-video-processor'));
        }

        $attachment_id = isset($_GET['attachment_id']) ? absint($_GET['attachment_id']) : 0;
        check_admin_referer('argent_video_queue_' . $attachment_id);

        try {
            $this->queue->enqueue($attachment_id, true);
            $this->launcher->dispatch();
            $this->redirect('queued');
        } catch (RuntimeException $error) {
            $this->redirect('error', $error->getMessage());
        }
    }

    public function cancel_action(): void
    {
        if (! current_user_can('upload_files')) {
            wp_die(esc_html__('You are not allowed to process media.', 'wp-argent-video-processor'));
        }

        $attachment_id = isset($_GET['attachment_id']) ? absint($_GET['attachment_id']) : 0;
        check_admin_referer('argent_video_cancel_' . $attachment_id);
        $this->jobs->cancel($attachment_id);
        update_post_meta($attachment_id, '_argent_video_status', 'cancelled');
        $this->redirect('cancelled');
    }

    public function dispatch_action(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to launch the worker.', 'wp-argent-video-processor'));
        }
        check_admin_referer('argent_video_dispatch');
        $result = $this->launcher->launch();
        update_option('argent_video_processor_last_launch', $result, false);
        $this->redirect(! empty($result['ok']) ? 'launched' : 'error', (string) ($result['message'] ?? ''));
    }

    public function notices(): void
    {
        if (empty($_GET['argent_video_notice'])) {
            return;
        }

        $notice = sanitize_key((string) $_GET['argent_video_notice']);
        $message = isset($_GET['argent_video_message']) ? sanitize_text_field(wp_unslash((string) $_GET['argent_video_message'])) : '';
        $labels = array(
            'queued'    => __('Video queued for processing.', 'wp-argent-video-processor'),
            'cancelled' => __('Queued video processing was cancelled.', 'wp-argent-video-processor'),
            'launched'  => __('The background video worker was launched.', 'wp-argent-video-processor'),
            'error'     => $message ?: __('The video action failed.', 'wp-argent-video-processor'),
        );
        $class = 'error' === $notice ? 'notice notice-error' : 'notice notice-success';
        if (isset($labels[$notice])) {
            echo '<div class="' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($labels[$notice]) . '</p></div>';
        }
    }

    public function page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = Settings::all();
        $last_launch = get_option('argent_video_processor_last_launch', array());
        $last_worker = get_option('argent_video_processor_last_worker_run', array());
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Argent Video Processor', 'wp-argent-video-processor'); ?></h1>
            <p><?php esc_html_e('Uploaded originals are preserved. Streaming derivatives remove embedded metadata, normalize rotation, and are generated by a detached low-priority WP-CLI worker.', 'wp-argent-video-processor'); ?></p>

            <h2><?php esc_html_e('Queue status', 'wp-argent-video-processor'); ?></h2>
            <table class="widefat striped" style="max-width:900px"><tbody>
                <?php foreach (array('queued', 'processing', 'complete', 'failed', 'cancelled') as $status) : ?>
                    <tr><th><?php echo esc_html(ucfirst($status)); ?></th><td><?php echo esc_html((string) $this->jobs->count($status)); ?></td></tr>
                <?php endforeach; ?>
                <tr><th><?php esc_html_e('Last launch', 'wp-argent-video-processor'); ?></th><td><code><?php echo esc_html(wp_json_encode($last_launch, JSON_UNESCAPED_SLASHES)); ?></code></td></tr>
                <tr><th><?php esc_html_e('Last worker run', 'wp-argent-video-processor'); ?></th><td><code><?php echo esc_html(wp_json_encode($last_worker, JSON_UNESCAPED_SLASHES)); ?></code></td></tr>
            </tbody></table>
            <p><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=argent_video_dispatch'), 'argent_video_dispatch')); ?>"><?php esc_html_e('Launch worker now', 'wp-argent-video-processor'); ?></a></p>

            <h2><?php esc_html_e('Diagnostics', 'wp-argent-video-processor'); ?></h2>
            <table class="widefat striped" style="max-width:900px"><thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead><tbody>
                <?php foreach ($this->diagnostics->checks() as $check) : ?>
                    <tr><td><?php echo esc_html($check['check']); ?></td><td><?php echo esc_html(strtoupper($check['status'])); ?></td><td><code><?php echo esc_html($check['detail']); ?></code></td></tr>
                <?php endforeach; ?>
            </tbody></table>

            <form method="post" action="options.php">
                <?php settings_fields('argent_video_processor'); ?>
                <h2><?php esc_html_e('Settings', 'wp-argent-video-processor'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">Automation</th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION); ?>[auto_queue]" value="1" <?php checked(! empty($settings['auto_queue'])); ?>> Queue newly uploaded videos</label><br>
                        <label><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION); ?>[auto_dispatch]" value="1" <?php checked(! empty($settings['auto_dispatch'])); ?>> Launch detached worker from the five-minute WP-Cron dispatcher</label><br>
                        <label><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION); ?>[strip_metadata]" value="1" <?php checked(! empty($settings['strip_metadata'])); ?>> Strip GPS, device, chapter, and other source metadata from derivatives</label>
                    </td></tr>
                    <tr><th scope="row"><label for="argent-profile">Output profile</label></th><td>
                        <select id="argent-profile" name="<?php echo esc_attr(Settings::OPTION); ?>[profile]">
                            <option value="dual" <?php selected($settings['profile'], 'dual'); ?>>Open WebM preferred + compatible MP4 fallback</option>
                            <option value="open" <?php selected($settings['profile'], 'open'); ?>>Open WebM only (original MP4 remains final fallback)</option>
                            <option value="compatibility" <?php selected($settings['profile'], 'compatibility'); ?>>Compatible MP4 only</option>
                        </select>
                    </td></tr>
                    <?php $this->number_row('max_width', 'Maximum width', $settings); ?>
                    <?php $this->number_row('max_height', 'Maximum height', $settings); ?>
                    <?php $this->number_row('mp4_crf', 'MP4 CRF', $settings); ?>
                    <?php $this->number_row('mp4_maxrate_kbps', 'MP4 maximum video rate (kb/s)', $settings); ?>
                    <?php $this->number_row('webm_crf', 'WebM CRF', $settings); ?>
                    <?php $this->number_row('webm_maxrate_kbps', 'WebM maximum video rate (kb/s)', $settings); ?>
                    <?php $this->number_row('audio_bitrate_kbps', 'Audio rate (kb/s)', $settings); ?>
                    <?php $this->path_row('ffmpeg_path', 'FFmpeg path', $settings); ?>
                    <?php $this->path_row('ffprobe_path', 'FFprobe path', $settings); ?>
                    <?php $this->path_row('wp_cli_path', 'WP-CLI path', $settings); ?>
                    <?php $this->number_row('nice_level', 'CPU nice level', $settings); ?>
                    <?php $this->number_row('ionice_class', 'I/O scheduling class', $settings); ?>
                    <?php $this->number_row('ionice_level', 'I/O priority level', $settings); ?>
                    <?php $this->number_row('stale_job_minutes', 'Stale worker recovery (minutes)', $settings); ?>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e('WP-CLI', 'wp-argent-video-processor'); ?></h2>
            <pre>wp argent-video diagnose
wp argent-video jobs --status=failed
wp argent-video enqueue &lt;attachment-id&gt; --force
wp argent-video worker --once</pre>
        </div>
        <?php
    }

    /** @param array<string, mixed> $settings */
    private function number_row(string $key, string $label, array $settings): void
    {
        ?>
        <tr><th scope="row"><label for="argent-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td>
            <input class="small-text" type="number" id="argent-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(Settings::OPTION); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $settings[$key]); ?>">
        </td></tr>
        <?php
    }

    /** @param array<string, mixed> $settings */
    private function path_row(string $key, string $label, array $settings): void
    {
        ?>
        <tr><th scope="row"><label for="argent-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td>
            <input class="regular-text code" type="text" id="argent-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(Settings::OPTION); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $settings[$key]); ?>">
        </td></tr>
        <?php
    }

    private function redirect(string $notice, string $message = ''): never
    {
        $url = add_query_arg(
            array(
                'page'                 => 'argent-video-processor',
                'argent_video_notice'  => $notice,
                'argent_video_message' => $message,
            ),
            admin_url('options-general.php')
        );
        wp_safe_redirect($url);
        exit;
    }
}

// EOF: /home/alan/src/wp-argent-video-processor/includes/Admin.php
