<?php

declare(strict_types=1);

namespace Tests\Support\Extension;

use Codeception\Event\SuiteEvent;
use Codeception\Events;
use Codeception\Exception\ExtensionException;
use Codeception\Extension;
use Symfony\Component\Process\Process;

/**
 * Builds tests/Support/Data/dump.sql from a clean install before the suite, so the dump
 * always matches the WooCommerce version composer installed rather than a committed one.
 *
 * On MODULE_INIT below the Symlinker's priority: after the plugins are linked in, and
 * before WPDb opens the database the build replaces or reads the dump it loads.
 */
class DumpBuilder extends Extension
{
    public static array $events = [
        Events::MODULE_INIT => ['build', -10],
    ];

    public function build(SuiteEvent $event): void
    {
        if (($event->getSettings()['suite'] ?? '') !== 'EndToEnd') {
            return;
        }

        $this->writeln('DumpBuilder: building tests/Support/Data/dump.sql from a clean install ...');

        // The credentials and admin password the seed writes, from tests/.env via codeception.yml.
        $process = new Process(['bash', 'tests/_support_scripts/build-dump.sh'], codecept_root_dir(), $this->config['env'] ?? []);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ExtensionException(
                $this,
                "Building the dump failed:\n" . $process->getOutput() . $process->getErrorOutput()
            );
        }
    }
}
