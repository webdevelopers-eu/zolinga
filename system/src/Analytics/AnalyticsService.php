<?php

declare(strict_types=1);

namespace Zolinga\System\Analytics;

use Zolinga\System\Events\ServiceInterface;
use Zolinga\System\Types\SeverityEnum;
use Throwable;
use const Zolinga\System\IS_INTERACTIVE;

/**
 * Keeps track of the landing page and referrer page for analytics purposes.
 * 
 * Should be used only for HTTP requests.
 * 
 * Example usage:
 *    
 *   $api->analytics->currentURL; // Returns the current URL of the request.
 *   $api->analytics->referrerPage; // Returns the referrer page URL or null if not set.
 *   $api->analytics->landingPage; // Returns the landing page URL or null if
 */
class AnalyticsService implements ServiceInterface
{
    public string $currentURL {
        get {
            return $this->getCurrentURL();
        }
    }

    public bool $initialized {
        get {
            $this->checkSession();
            return isset($_SESSION['analytics']);
        }
    }

    public ?string $landingPage {
        get {
            $this->checkSession();
            return $_SESSION['analytics']['landingPage'] ?? null;
        }
    }

    public ?string $referrerPage {
        get {
            $this->checkSession();
            return $_SESSION['analytics']['referrerPage'] ?? null;
        }
    }

    public function __construct()
    {
        // Initialize the service, if needed.
    }

    public function initialize(): void 
    {
        if ($this->initialized) {
            throw new \RuntimeException("AnalyticsService is already initialized.");
        }

        $_SESSION['analytics'] = [
            'referrerPage' => $_SERVER['HTTP_REFERER'] ?? null,
            'landingPage' => $this->getCurrentURL(),
        ];
    }

    private function getCurrentURL(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $requestURI = $_SERVER['REQUEST_URI'];
        return "$protocol://$host$requestURI";
    }

    private function checkSession(): void
    {
        if (IS_INTERACTIVE || session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException("Zolinga Analytics: Session is not active. Please start a session before using AnalyticsService.");
        }
    }
}