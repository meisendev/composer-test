<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-02-05
 * Time: 14:42
 */

namespace Meisendev\LaravelDaemon;

use Exception;
use RuntimeException;
use Illuminate\Support\Facades\Artisan;

class CommandRunner
{
    /** @var  int */
    protected $parallelIndex;

    protected $name;
    protected $args = [];

    protected $once = false;
    protected $interval = 0;
    protected $frequency = 0;
    protected $frequencyFixed = false;

    protected $lastRun = 0;
    protected $nextRun = 0;
    protected $currentPid = 0;
    protected $alert = true;
    protected $stopped = false;

    public function __construct($parallelIndex, array $command)
    {
        $this->parallelIndex = $parallelIndex;

        $this->name = $command['name'];
        $this->args = $command['args'];
        $this->once = $command['once'];
        $this->interval = $command['interval'];
        $this->frequency = $command['frequency'];
        $this->frequencyFixed = $command['frequency_fixed'];
        $this->alert = $command['alert'];

        $this->nextRun = time();
    }

    public function shouldStartNextRunWhenNotFinished(): bool
    {
        if (!$this->frequencyFixed || !$this->frequency || $this->once) {
            return false;
        }

        if ($this->lastRun + $this->frequency <= time()) {
            return true;
        } else {
            return false;
        }
    }

    public function cloneEarlyRunner(): CommandRunner
    {
        $ret = clone $this;
        $ret->nextRun = time();
        $this->once = true;

        return $ret;
    }

    public function __clone()
    {
        $this->lastRun = 0;
        $this->nextRun = 0;
        $this->currentPid = 0;
    }

    public function onProcessExit()
    {
        if ($this->once) {
            $this->stopped = true;
        } else {
            $this->nextRun = time();

            if ($this->frequency) {
                if ($this->nextRun - $this->lastRun < $this->frequency) {
                    $this->nextRun = $this->lastRun + $this->frequency;
                }
            }

            if ($this->interval && $this->nextRun - time() < $this->interval) {
                $this->nextRun = time() + $this->interval;
            }
        }
    }

    public function run()
    {
        if ($this->stopped) {
            return 0;
        }

        $pid = pcntl_fork();
        if ($pid < 0) {
            $errno = pcntl_get_last_error();
            throw new RuntimeException("Cannot fork process, error = " . pcntl_strerror($errno));
        } elseif ($pid == 0) {
            $now = time();
            if ($now < $this->nextRun) {
                sleep($this->nextRun - $now);
            }

            try {
                $ret = Artisan::call($this->name, $this->args);
            } catch (Exception $e) {
                $ret = AbstractDaemonSentinelCommand::EXIT_CODE_COMMON_ERROR;
            }

            if ($ret != AbstractDaemonSentinelCommand::EXIT_CODE_OK && $this->alert) {
                exit(AbstractDaemonSentinelCommand::EXIT_CODE_OK);
            } else {
                exit($ret);
            }
        } else {
            $this->lastRun = $this->nextRun;
            $this->currentPid = $pid;

            return $pid;
        }
    }
}
