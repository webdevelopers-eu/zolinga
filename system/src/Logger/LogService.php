<?php

declare(strict_types=1);

namespace Zolinga\System\Logger;

use Zolinga\System\Events\ServiceInterface;
use Zolinga\System\Types\SeverityEnum;
use Throwable;
use const Zolinga\System\IS_INTERACTIVE;

/**
 * Logging service.
 *
 * Example:
 *   $api->log->info("example.category", "Hello, world!");
 *   $api->log->error("example.test.install", "Something went wrong.", ["extraInfo" => "Really bad!"]);
 * 
 *   // You can call the logger as a function (see __invoke() method)
 *   $api->log(SeverityEnum::INFO, "example.category", "Hello, world!", ["user" => "danny"]);
 * 
 * Messages are logged to private://system/logs/messages.log ({ZOLINGA}/data/system/logs/messages.log).
 * 
 * Rotated files are stored as messages.log.1, messages.log.2, etc.
 * 
 * Rotating can be set to daily, weekly, monthly, yearly or by size in the configuration file.
 * 
 * The log format is with and without context as follows:
 * 
 *   [{date}] [{severity}] {ip|sapi} {runtimeId}/pid{pid} [{category}] "{JSON_STRING}"   
 *   [{date}] [{severity}] {ip|sapi} {runtimeId}/pid{pid} [{category}] "{JSON_STRING}" """" {JSON_CONTEXT_ARRAY}
 * 
 * Examples: 
 * 
 *   [2024-03-09T12:54:38+00:00] [error] ::1 d9776b8f/pid423399 [test.log] "Hello, error world!"
 *   [2024-03-09T12:55:48+00:00] [info] cli 1c2b7723/pid458562 [test.log] "Hello, world!" """" {"user":"danny"}
 *
 * @module Logger
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since  2024-03-09
 */
class LogService implements ServiceInterface
{
    public const SEVERITY_INFO = SeverityEnum::INFO;
    public const SEVERITY_WARNING = SeverityEnum::WARNING;
    public const SEVERITY_ERROR = SeverityEnum::ERROR;

    private ?string $path = null;
    private string $buffer = '';
    private readonly string $runtimeId;

    /**
     * Count of messages logged with severity ERROR.
     *
     * @var integer
     */
    public int $errorCount = 0;

    /**
     * Count of messages logged with severity WARNING.
     *
     * @var integer
     */
    public int $warningCount = 0;

    /**
     * Count of messages logged with severity INFO.
     *
     * @var integer
     */
    public int $infoCount = 0;

    public function __construct() {
        // Short string to uniquely identify the runtime logs from this run.
        $this->runtimeId = substr(base_convert(strval(rand(1000000000, 9999999999)), 10, 36), 0, 4);
    }

    /**
     * Run the logger as a function. 
     * 
     * Example: 
     * 
     *    $api->log(SeverityEnum::INFO, "example.category", "Hello, world!", ["user" => "danny"]);
     * 
     * You can use $api->log::SEVERITY_INFO, $api->log::SEVERITY_WARNING, $api->log::SEVERITY_ERROR or \Zolinga\System\Types\SeverityEnum enumerations.
     *
     * @param SeverityEnum $severity. You can use constants $api->log::SEVERITY_INFO, $api->log::SEVERITY_WARNING, $api->log::SEVERITY_ERROR or \Zolinga\System\Types\SeverityEnum enumerations.
     * @param string $category The category of the message, starts with module name - dot-separated, e.g. "ecs.installation"
     * @param string|Throwable $message Any message to log.
     * @param ?array<mixed> $context Any JSON-serializable structure to log with the message.
     * @return void
     */
    public function __invoke(SeverityEnum $severity, string $category, string|Throwable $message, array $context = null): void {
        $this->log($severity, $category, $message, $context);
    }

    /**
     * This method is an alias for $api->log(SeverityEnum::INFO, $category, $message, $context) because intelephense for VS Code does not support magic method __invoke();
     * and also stubornly refuses to recognize the magic method __invoke() as a valid method. As an alternative you can use $api->log->log(...) instead of $api->log(...)
     * 
     * @param bool|SeverityEnum $severity. You can use constants $api->log::SEVERITY_INFO, $api->log::SEVERITY_WARNING, $api->log::SEVERITY_ERROR or \Zolinga\System\Types\SeverityEnum enumerations or bool where false is ERROR and true is INFO.
     * @param string $category The category of the message, starts with module name - dot-separated, e.g. "ecs.installation"
     * @param string|Throwable $message Any message to log.
     * @param ?array<mixed> $context Any JSON-serializable structure to log with the message.
     * @return void
     */
    public function log(SeverityEnum|bool $severity, string $category, string|Throwable $message, array $context = null): void {
        if ($severity === true) {
            $severity = SeverityEnum::INFO;
        } elseif ($severity === false) {
            $severity = SeverityEnum::ERROR;
        }
        $this->write($severity, $category, $message, $context);
    }

    /**
     * We want to use the logger early before $api->config is loaded.
     * $api->config depends on $api->manifest which depends on $api->log...
     * 
     * So we start in offline mode and enable the logger later and it will flush
     * buffered messages to the log file.
     *
     * @return void
     */
    public function online() {
        global $api;

        if (!$this->isOffline()) {
            trigger_error("Logger is already online.", E_USER_WARNING);
            return;
        }

        $this->path = $api->fs->toPath("private://system/logs/messages.log");
        if (!is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0777, true);
        }

        $this->rotate();

        // Flush the buffer
        file_put_contents($this->path, $this->buffer, FILE_APPEND | LOCK_EX);
        $this->buffer = '';
    }

    /**
     * Is the logger offline? If it is offline it logs into memory buffer.
     *
     * @return boolean
     */
    public function isOffline(): bool {
        return $this->path === null;
    }

    /**
     * Log a message with the severity INFO.
     * 
     * Example: $api->log->info("ecs.installation", "Hello, world!", ["user" => "danny"]);
     *
     * @param string $category The category of the message, starts with module name - dot-separated, e.g. "ecs.installation"
     * @param string|Throwable $message Any message to log.
     * @param ?array<mixed> $context Any JSON-serializable structure to log with the message.
     * @return void
     */
    public function info(string $category, string|Throwable $message, array $context = null): void {
        $this->write(SeverityEnum::INFO, $category, $message, $context);
    }

    /**
     * Log a message with the severity WARNING.
     * 
     * Example: $api->log->warning("ecs.installation", "Folder $target is writeable by everybody", ["dir" => $target]);
     *
     * @param string $category The category of the message, starts with module name - dot-separated, e.g. "ecs.installation"
     * @param string|Throwable $message Any message to log.
     * @param ?array<mixed> $context Any JSON-serializable structure to log with the message.
     * @return void
     */
    public function warning(string $category, string|Throwable $message, ?array $context = null): void {
        $this->write(SeverityEnum::WARNING, $category, $message, $context);
    }

    /**
     * Log a message with the severity ERROR.
     * 
     * Example: $api->log->error("system.installer", "Failed to install the script: $script");
     *
     * @param string $category The category of the message, starts with module name - dot-separated, e.g. "ecs.installation"
     * @param string|Throwable $message Any message to log.
     * @param ?array<mixed> $context Any JSON-serializable structure to log with the message.
     * @return void
     */
    public function error(string $category, string|Throwable $message, ?array $context = null): void {
        $this->write(SeverityEnum::ERROR, $category, $message, $context);
    }

    private function rotate(): void {
        global $api;

        $rotate = match ($api->config['logger']['rotate']) {
            'daily' => $this->getLastRotation() < strtotime('today'),
            'weekly' => $this->getLastRotation() < strtotime('last monday'),
            'monthly' => $this->getLastRotation() < strtotime('first day of this month'),
            'yearly' => $this->getLastRotation() < strtotime('first day of january this year'),
            'size' => file_exists($this->path) && filesize($this->path) > (intval($api->config['logger']['rotateSize']) ?: 1048576),
            default => throw new \Exception("Invalid logger.rotate value: " . $api->config['logger']['rotate']),
        };

        if ($rotate) {
            $maxFiles = intval($api->config['logger']['maxFiles']) ?: 7;
            if (file_exists("{$this->path}.{$maxFiles}")) {
                unlink("{$this->path}.{$maxFiles}");
            }
            for ($i = $maxFiles - 1; $i > 0; $i--) {
                $oldFile = $this->path . ($i === 1 ? '' : '.' . ($i - 1));
                if (file_exists($oldFile)) {
                    rename($oldFile, $this->path . '.' . $i);
                }
            }
            $this->setLastRotation();
        }
    }

    private function getLastRotation(): int {
        if (!file_exists($this->path . '.json')) {
            return 0;
        }
        $lastRotation = json_decode(file_get_contents($this->path . '.json') ?: '[]', true);
        return $lastRotation['lastRotation'] ?? 0;
    }

    private function setLastRotation(): void {
        file_put_contents($this->path . '.json', json_encode(['lastRotation' => time()]));
    }

    /**
     * Write a message to the log.
     * 
     * 	Line format:
     * 
     *    [{date}] [{severity}] {ip|sapi} {pid} [{category}] "{JSON_STRING}" """" {JSON_CONTEXT}
     *
     * @access public
     * @param SeverityEnum $severity
     * @param string $category
     * @param string|Throwable $message
     * @param ?array<mixed> $context JSON-serializable structure
     * @return void
     */
    private function write(SeverityEnum $severity, string $category, string|Throwable $message, ?array $context): void
    {
        global $api;

        $this->{$severity->value . 'Count'}++;

        $jsonFlags = 
            JSON_UNESCAPED_SLASHES | 
            JSON_UNESCAPED_UNICODE | 
            JSON_INVALID_UTF8_IGNORE | 
            JSON_INVALID_UTF8_SUBSTITUTE |
            JSON_PARTIAL_OUTPUT_ON_ERROR
            ;

        if ($message instanceof Throwable) {
            $context = $context ?? [];
            $context['exception'] = [
                'class' => get_class($message),
                'code' => $message->getCode(),
                'message' => $message->getMessage(),
                'file' => $message->getFile(),
                'line' => $message->getLine(),
                'trace' => $message->getTrace(),
            ];
            $message = get_class($message) . ': ' . $message->getCode(). ' ' . $message->getMessage();
        }

        $line = [
            '['.date('c').']',
            $_SERVER['REMOTE_ADDR'] ?? php_sapi_name(),
            '['.$category.':'.$severity->value.']',
            $this->runtimeId . '/' . getmypid(),
            sprintf("%.1F", memory_get_usage() / 1024 / 1024) . 'M',
            $severity->getEmoji(),
            json_encode($message, $jsonFlags),
        ];

        if ($context !== null) {
            $line[] = '""""';
            $line[] = json_encode($context, $jsonFlags);
        }

        if ($this->path) {
            file_put_contents($this->path, implode(' ', $line) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } else {
            $this->buffer .= implode(' ', $line) . PHP_EOL;
        }

        if (IS_INTERACTIVE) {
            file_put_contents('php://stderr', $severity->getEmoji() . ' ' . $category . ': ' . $message . PHP_EOL);
        }
    }
}
