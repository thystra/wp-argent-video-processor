<?php
/**
 * /home/alan/src/wp-argent-video-processor/includes/CLI_Command.php
 */

declare(strict_types=1);

namespace ArgentVideo;

use RuntimeException;
use WP_CLI;
use WP_CLI\Utils;

final class CLI_Command
{
    public function __construct(
        private readonly Job_Repository $jobs,
        private readonly Queue $queue,
        private readonly Worker $worker,
        private readonly Diagnostics $diagnostics
    ) {
    }

    /**
     * Process queued videos.
     *
     * ## OPTIONS
     *
     * [--once]
     * : Process one queued job.
     *
     * [--limit=<count>]
     * : Process up to this many jobs. Default 1.
     */
    public function worker(array $args, array $assoc_args): void
    {
        unset($args);
        $limit = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 1;
        if (isset($assoc_args['once'])) {
            $limit = 1;
        }

        try {
            $result = $this->worker->run($limit);
            foreach ($result['errors'] as $failure) {
                WP_CLI::warning(sprintf(
                    'Job %d for attachment %d failed: %s',
                    $failure['job_id'],
                    $failure['attachment_id'],
                    $this->error_summary($failure['message'])
                ));
            }

            WP_CLI::success(sprintf(
                'Worker complete: %d processed, %d failed, %d stale recovered.',
                $result['processed'],
                $result['failed'],
                $result['recovered']
            ));
        } catch (RuntimeException $error) {
            WP_CLI::error($error->getMessage());
        }
    }

    /** Display configuration and executable checks. */
    public function diagnose(): void
    {
        Utils\format_items('table', $this->diagnostics->checks(), array('check', 'status', 'detail'));
    }

    /**
     * Queue a video attachment.
     *
     * ## OPTIONS
     *
     * <attachment-id>
     * : WordPress media attachment ID.
     *
     * [--force]
     * : Reprocess even when the source signature is unchanged.
     */
    public function enqueue(array $args, array $assoc_args): void
    {
        $attachment_id = (int) ($args[0] ?? 0);
        try {
            $job_id = $this->queue->enqueue($attachment_id, isset($assoc_args['force']));
            WP_CLI::success('Queued job ' . $job_id . '.');
        } catch (RuntimeException $error) {
            WP_CLI::error($error->getMessage());
        }
    }

    /**
     * List video jobs.
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter by status.
     *
     * [--limit=<count>]
     * : Maximum jobs. Default 50.
     *
     * [--format=<format>]
     * : table, json, csv, yaml, or count.
     */
    public function jobs(array $args, array $assoc_args): void
    {
        unset($args);
        $status = sanitize_key((string) ($assoc_args['status'] ?? ''));
        $limit = (int) ($assoc_args['limit'] ?? 50);
        $format = (string) ($assoc_args['format'] ?? 'table');
        $rows = $this->jobs->list($limit, $status);
        Utils\format_items($format, $rows, array('id', 'attachment_id', 'profile', 'status', 'attempts', 'updated_at', 'error_message'));
    }

    /**
     * Queue existing video attachments that do not yet have jobs.
     *
     * ## OPTIONS
     *
     * [--limit=<count>]
     * : Maximum attachments to inspect. Default 100.
     */
    public function scan(array $args, array $assoc_args): void
    {
        unset($args);
        $limit = max(1, min(1000, (int) ($assoc_args['limit'] ?? 100)));
        $query = new \WP_Query(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'post_mime_type' => 'video',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ));

        $queued = 0;
        foreach ($query->posts as $attachment_id) {
            if (null === $this->jobs->find_by_attachment((int) $attachment_id)) {
                $this->queue->enqueue((int) $attachment_id, false);
                $queued++;
            }
        }
        WP_CLI::success('Queued ' . $queued . ' existing video attachment(s).');
    }

    private function error_summary(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message)) ?? trim($message);
        if (strlen($message) <= 500) {
            return $message;
        }

        return substr($message, 0, 497) . '...';
    }
}

// EOF: /home/alan/src/wp-argent-video-processor/includes/CLI_Command.php
