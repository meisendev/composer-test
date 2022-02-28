<?php
/**
 * User: meisen
 * Date: 2022/2/28
 */

namespace App\Daemon;

class DaemonSentinelCommand extends AbstractDaemonSentinelCommand
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sentinel:run {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs as sentinel for a list of daemon commands';
}
