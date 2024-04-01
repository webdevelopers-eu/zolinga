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

    public function __construct()
    {
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

        // allowedIps
        $ips = $api->config['wiki']['allowedIps'] ?? ["127.0.0.1"];

        $match = array_reduce($ips, function (bool $match, string $ip): bool {
            if ($match) return true; // matches
            if (php_sapi_name() === $ip) return true; // cli
            $regExp = '@^' . str_replace(["\\*", "\\?"], [".+", "."], preg_quote($ip, '@')) . '$@';
            return (bool) preg_match($regExp, $_SERVER['REMOTE_ADDR']);
        }, false);

        return $match && !empty($api->config['wiki']['password']) && !empty($api->config['wiki']['urlPrefix']);
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
            return;
        }

        if (!empty($event->request['password'])) {
            // Simple brute force prevention
            $maxAttempts = $api->config['wiki']['maxAttempts'] ?? 5;
            $timeframe = $api->config['wiki']['maxAttemptsTimeframe'] ?? 300;
            $log = json_decode(file_get_contents(self::LOG_FILE) ?: '[]', true);
            $attempts = 0;
            $save = false;
            foreach ($log as $k => $record) {
                if ($record['time'] < time() - $timeframe) {
                    unset($log[$k]);
                    $save = true;
                    continue;
                }
                if ($record['ip'] == $_SERVER['REMOTE_ADDR']) {
                    $attempts++;
                }
            }
            if ($attempts > $maxAttempts) {
                $event->setStatus($event::STATUS_FORBIDDEN, "Too many attempts. Wait " . ($timeframe / 60) . " minutes.");
            } else { // Login attempt
                $_SESSION['systemWiki']['authorized'] = $api->config['wiki']['password'] === $event->request['password'];
            }

            if ($save) {
                file_put_contents(self::LOG_FILE, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        }

        if (!empty($_SESSION['systemWiki']['authorized'])) {
            $event->setStatus($event::STATUS_OK, "Authorized");
            $log = array_filter($log ?? [], fn ($record) => $record['ip'] != $_SERVER['REMOTE_ADDR']);
        } else {
            $event->setStatus($event::STATUS_UNAUTHORIZED, "Unauthorized");
            $log[] = ['time' => time(), 'ip' => $_SERVER['REMOTE_ADDR']];
        }
        file_put_contents(self::LOG_FILE, json_encode($log));
    }

    /**
     * Did the user enter the correct password? Relies on the session.
     *
     * @return boolean true if the user is authorized
     */
    public function isAuthorized(): bool
    {
        global $api;

        return $this->isEnabled() && ($_SESSION['systemWiki']['authorized'] ?? false);
    }
}
