<?php

declare(strict_types=1);

namespace Zolinga\System\Wiki;

use Zolinga\System\Events\{RequestResponseEvent, AuthorizeEvent};
use Zolinga\System\Events\ListenerInterface;

/**
 * The WIKI authentication and authorization service.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2024-02-23
 */
class WikiAuth implements ListenerInterface
{
    private const LOG_FILE = "private://system/wiki-login.log.json";
    private int $maxAttempts = 5;
    private int $maxAttemptsTimeframe = 300;

    public function __construct()
    {
        global $api;
        $this->maxAttempts = intval($api->config['wiki']['maxAttempts']) ?? 5;
        $this->maxAttemptsTimeframe = intval($api->config['wiki']['maxAttemptsTimeframe']) ?? 300;
    }

    /**
     * Is WIKI enabled on this site?
     * 
     * If the wiki.urlPrefix property is not defined or is false or the wiki.password is not defined
     * then the wiki is disabled.
     *
     * @return boolean true if WIKI is enabled
     */
    public function isEnabled(): bool
    {
        global $api;

        if (!$api->config['wiki']['enabled']) {
            return false;
        }

        // allowedIps
        $ips = $api->config['wiki']['allowedIps'] ?? ["127.0.0.1"];

        $match = array_reduce($ips, function (bool $match, string $ip): bool {
            if ($match) return true; // matches
            if (php_sapi_name() === $ip) return true; // cli
            $regExp = '@^' . str_replace(["\\*", "\\?"], [".+", "."], preg_quote($ip, '@')) . '$@';
            return (bool) preg_match($regExp, $_SERVER['REMOTE_ADDR']);
        }, false);

        return $match && !empty($api->config['wiki']['urlPrefix']);
    }

    /**
     * Checks if the user has the system:wiki:read right.
     *
     * @param AuthorizeEvent $event
     * @return void
     */
    public function onAuthorize(AuthorizeEvent $event)
    {
        foreach ($event->unauthorized as $right) {
            if ($right == 'system:wiki:read' && $this->isAuthorized()) {
                $event->authorize($right);
            }
        }
    }

    /**
     * Inquires if the user has entered the correct password or if it is currently authorized.
     *
     * @param RequestResponseEvent $event
     * @return void
     */
    public function onLogin(RequestResponseEvent $event): void
    {
        global $api;

        if (!$this->isEnabled()) {
            $_SESSION['systemWiki']['authorized'] = false;
            $event->setStatus($event::STATUS_NOT_FOUND, "WIKI is not enabled");
        } elseif (!empty($event->request['password'])) {
            if ($this->throttle($_SERVER['REMOTE_ADDR'])) { 
                $event->setStatus($event::STATUS_FORBIDDEN, "Too many attempts. Wait " . ($this->maxAttemptsTimeframe / 60) . " minutes.");
            } elseif ($this->loginWithPassword($event->request['password'])) {
                $event->setStatus($event::STATUS_OK, "Authorized");
            } else {
                $event->setStatus($event::STATUS_UNAUTHORIZED, "Invalid password");
                $this->recordLoginAttempt($_SERVER['REMOTE_ADDR']);
            }
        } elseif ($this->isAuthorized()) {
            $event->setStatus($event::STATUS_OK, "Authorized");
        } else {
            $event->setStatus($event::STATUS_UNAUTHORIZED, "Unauthorized");
            $this->recordLoginAttempt($_SERVER['REMOTE_ADDR']);
        }
    }

    private function readAttempts(): array {
        return json_decode(file_get_contents(self::LOG_FILE) ?: '[]', true) ?? [];
    }

    private function recordLoginAttempt(string $ip) {
        $log = $this->readAttempts();
        $log[] = ['time' => time(), 'ip' => $ip];
        file_put_contents(self::LOG_FILE, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function throttle(string $ip) {
        // Simple brute force prevention
        $log = $this->readAttempts();
        $attempts = 0;
        $save = false;

        // Throttling and brute force prevention
        foreach ($log as $k => $record) {
            // Remove old records
            if ($record['time'] < time() - $this->maxAttemptsTimeframe) {
                unset($log[$k]);
                $save = true;
                continue;
            }
            // Count attempts for this IP
            if ($record['ip'] == $_SERVER['REMOTE_ADDR']) {
                $attempts++;
            }
        }

        if ($save) {
            file_put_contents(self::LOG_FILE, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $attempts > $this->maxAttempts;
    }

    private function loginWithPassword(string $password): bool
    {
        global $api;

        $_SESSION['systemWiki']['authorized'] = $api->config['wiki']['password'] === '' || $api->config['wiki']['password'] === $password;
        return $_SESSION['systemWiki']['authorized'];
    }

    /**
     * Did the user enter the correct password? Relies on the session.
     *
     * @return boolean true if the user is authorized
     */
    public function isAuthorized(): bool
    {
        global $api;
        return $this->isEnabled() && (($_SESSION['systemWiki']['authorized'] ?? false) || $api->config['wiki']['password'] === '');
    }
}
