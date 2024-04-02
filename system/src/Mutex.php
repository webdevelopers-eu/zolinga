<?php

declare(strict_types=1);

namespace Zolinga\System;

/**
 * A mutex is a mutual exclusion object that allows multiple processes to
 * synchronize access to a shared resource. In this case, the shared resource
 * is the long task that we want to run only once at a time.
 * 
 * Example:
 * 
 * $mutex = new \Zolinga\System\Mutex('my-long-task');
 * if ($mutex->lock()) {
 *    // Do the long task
 *    $mutex->unlock();
 * } else {
 *    // The task is already running
 * }
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 */
class Mutex
{
    private ?string $lockFile;
    private mixed $fileHandle;
    public readonly string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
        $friendlyName = preg_replace('/[^a-zA-Z0-9\-\_]/', '-', $name);
        $this->lockFile = sys_get_temp_dir() . "/zolinga-{$friendlyName}-" . md5($name) . ".lock";
    }

    public function lock(): bool
    {
        $this->fileHandle = fopen($this->lockFile, 'w');

        if ($this->fileHandle === false) {
            throw new \Exception("Unable to create lock file: {$this->lockFile}");
        }

        if (flock($this->fileHandle, LOCK_EX | LOCK_NB)) {
            return true;
        }

        fclose($this->fileHandle);
        return false;
    }

    public function unlock(): void
    {
        flock($this->fileHandle, LOCK_UN);
        fclose($this->fileHandle);
        unlink($this->lockFile);
        $this->fileHandle = null;
    }

    public function __destruct() {
        if ($this->fileHandle) {
            $this->unlock();
        }
    }

    public function __toString()
    {
        return "Mutex({$this->name})";
    }
}
