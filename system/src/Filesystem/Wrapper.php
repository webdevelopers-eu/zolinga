<?php
declare(strict_types=1);

namespace Zolinga\System\Filesystem;
use Exception;

/**
 * This is the URL wrapper that allows to use the Zolinga Filesystem URI scheme.
 * 
 * For more information see WrapperService.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2024-02-02
 */
class Wrapper {
    public mixed $context;

    /**
     * @param string $path
     * @return string
     */
    private function realPath(string $path): string { 
        global $api;
        return $api->fs->toPath($path);
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool { 
        $fileName = $this->realPath($path);
        $this->context = fopen($fileName, $mode);
        return (bool) $this->context;
    }

    /**
     * @param int<0, max> $count
     * @return string|false
     */
    public function stream_read(int $count): string|false { 
        return fread($this->context, $count);
    }

    public function stream_write(string $data): int|false { 
        return fwrite($this->context, $data);
    }

    public function stream_tell(): int|false { 
        return ftell($this->context);
    }

    /**
     * Truncates the file to a given length.
     *
     * @param int<0,max> $new_size
     * @return boolean
     */
    public function stream_truncate(int $new_size): bool { 
        return ftruncate($this->context, $new_size);
    }

    public function stream_eof(): bool { 
        return feof($this->context);
    }

    public function stream_seek(int $offset, int $whence): bool { 
        return fseek($this->context, $offset, $whence) !== -1;
    }

    public function stream_close(): void { 
        fclose($this->context);
    }

    public function stream_lock(int $operation): bool { 
        return flock($this->context, $operation);
    }

    // public function stream_metadata(string $path, int $option, mixed $value): bool { 
    //     $path = $this->realPath($path);
    //     switch ($option) {
    //         case STREAM_META_TOUCH:
    //             if (!touch($path, $value[0] ?? time(), $value[1] ?? time())) {
    //                 trigger_error('Failed to touch file: ' . $path, E_USER_WARNING);
    //                 return false;
    //             }
    //             return true;
    //         case STREAM_META_OWNER_NAME:
    //         case STREAM_META_OWNER:
    //             return chown($path, $value);
    //         case STREAM_META_GROUP_NAME:
    //         case STREAM_META_GROUP:
    //             return chgrp($path, $value);
    //         case STREAM_META_ACCESS:
    //             return chmod($path, $value);
    //         default:
    //             trigger_error('Failed to set metadata: ' . $path, E_USER_WARNING);
    //             return false;
    //     }
    // }

    // public function stream_set_option(int $option, int $arg1, int $arg2): bool { 
    //     return stream_set_option($this->context, $option, $arg1, $arg2);
    // }

    public function stream_cast(int $cast_as): mixed { 
        // if ($cast_as === STREAM_CAST_FOR_SELECT) {
        //     return $this->context;
        // } elseif ($cast_as === STREAM_CAST_AS_STREAM) {
        //     return $this->context;
        // } else {
        //     return false;
        // }
        return $this->context;
    }

    public function stream_flush(): bool { 
        return fflush($this->context);
    }

    /**
     * @return array{0: int, 1: int, 2: int, ...}|false
     */
    public function stream_stat(): array|false { 
        return fstat($this->context);
    }

    /**
     * @param string $url
     * @param integer $flags
     * @return array{0: int, 1: int, 2: int, ...}|false|null
     */
    public function url_stat(string $url, int $flags): array|false|null { 
        $path = $this->realPath($url);
        if (!file_exists($path)) return null;
        return stat($path);
    }

    public function unlink(string $url): bool { 
        $path = $this->realPath($url);
        if ($path && !unlink($path)) {
            trigger_error('Failed to unlink file: ' . $url, E_USER_WARNING);
            return false;
        }
        return true;
    }

    public function rename(string $from, string $to): bool { 
        $fromReal = $this->realPath($from);
        $toReal = $this->realPath($to);
        if (!rename($fromReal, $toReal)) {
            trigger_error('Failed to rename file: ' . $from . ' -> ' . $to, E_USER_WARNING);
            return false;
        }
        return true;
    }

    public function mkdir(string $dir, int $mode, int $options): bool { 
        $realDir = $this->realPath($dir);
        if (is_dir($realDir)) {
            return true;
        } elseif (is_readable($realDir)) {
            trigger_error('The file of the same name already exists: ' . $dir, E_USER_WARNING);
            return false;
        } elseif (!mkdir($realDir, $mode, $options & STREAM_MKDIR_RECURSIVE ? true : false)) {
            trigger_error('Failed to create the directory: ' . $dir, E_USER_WARNING);
            return false;
        }
        return true;
    }

    public function rmdir(string $dir, int $options): bool { 
        if (!rmdir($realDir = $this->realPath($dir))) {
            trigger_error('Failed to remove the directory: ' . $dir, E_USER_WARNING);
            return false;
        }
        return true;
    }

    public function dir_opendir(string $dir, int $options): bool { 
        $realDir = $this->realPath($dir);
        $this->context = opendir($realDir);
        if (!$this->context) {
            trigger_error('Failed to open the directory: ' . $dir, E_USER_WARNING);
            return false;
        }
        return true;
    }

    public function dir_readdir(): string|false { 
        return readdir($this->context);
    }

    public function dir_closedir(): bool { 
        closedir($this->context); // returns void
        return true;
    }

    public function dir_rewinddir(): bool {
        rewinddir($this->context); // returns void
        return true;
    }

    public function __destruct() {
        // It appears that PHP closes the stream automatically.
        // fclose() always fails for me.
         
        // if ($this->context) {
            // fclose($this->context);
        // }
    }
}