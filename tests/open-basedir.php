<?php
/**
 * /home/alan/src/wp-argent-video-processor/tests/open-basedir.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/Shell_Probe.php';

use ArgentVideo\Shell_Probe;

if ('' === (string) ini_get('open_basedir')) {
    fwrite(STDERR, "open_basedir regression test must run with a restriction.\n");
    exit(1);
}
if (@is_executable(PHP_BINARY)) {
    fwrite(STDERR, "PHP filesystem stat unexpectedly sees the external PHP binary.\n");
    exit(1);
}
if (! Shell_Probe::path_executable(PHP_BINARY)) {
    fwrite(STDERR, "Shell probe could not find the executable outside open_basedir.\n");
    exit(1);
}
$result = Shell_Probe::run(array(PHP_BINARY, '-v'));
if (! $result['ok'] || ! str_contains($result['output'], 'PHP ')) {
    fwrite(STDERR, "Shell probe could not execute the binary outside open_basedir.\n");
    exit(1);
}

fwrite(STDOUT, "open_basedir shell-probe regression test passed.\n");

// EOF: /home/alan/src/wp-argent-video-processor/tests/open-basedir.php
