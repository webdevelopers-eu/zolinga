<?php

declare(strict_types=1);

namespace Zolinga\System\Health;

use Zolinga\System\Events\Event;
use Zolinga\System\Events\HealthCheckEvent;
use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Types\OriginEnum;
use Zolinga\System\Types\StatusEnum;

/**
 * CLI listener for healthcheck events.
 * 
 * This listener receives healthcheck events from CLI mode and
 * rewraps them as internal events to be processed by health monitors.
 * 
 * @author GitHub Copilot <copilot@github.com>
 * @since 2025-05-23
 */
class HealthCheckCli implements ListenerInterface
{
    /**
     * Handle the healthcheck event from CLI mode
     * 
     * @param Event $event The CLI event
     */
    public function onHealthcheck(Event $event): void
    {
        global $api;
        
        // Extract notification email if provided
        $notifyEmail = null;
        if (isset($event->request['notify'])) {
            $notifyEmail = $event->request['notify'];

            if (!filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
                $event->setStatus(StatusEnum::BAD_REQUEST, "Invalid email address provided for notification");
                return;
            }
        }
        
        // Create and dispatch an internal healthcheck event
        $healthcheckEvent = new HealthCheckEvent('healthcheck', OriginEnum::INTERNAL, $notifyEmail);
        $healthcheckEvent->dispatch();
        
        // Copy the status from the internal event
        $event->setStatus($healthcheckEvent->status, $healthcheckEvent->message);
        
        // Add the health reports to the event response
        $event->response = [
            'data' => $healthcheckEvent->response,
            'reports' => $healthcheckEvent->reports,
            'summary' => $healthcheckEvent->getSummary(),
            'notification' => [
                'email' => $notifyEmail,
                'sent' => !empty($notifyEmail) && $healthcheckEvent->status->isError(),
            ],
        ];
        
        // If notification email is provided and there are errors, send email
        if ($notifyEmail && $healthcheckEvent->status->isError()) {
            $this->sendNotificationEmail($notifyEmail, $healthcheckEvent);
        }
        
        // Log the completion of health check
        $status = $healthcheckEvent->status->getFriendlyName();
        if ($healthcheckEvent->status->isError()) {
            $api->log->error("system", "Health check failed with status: $status");
        } else {
            $api->log->info("system", "Health check completed successfully with status: $status");
            $api->pingjoe->ping("ipd:healthcheck", "+8 hours");
        }
    }
    
    /**
     * Send notification email about health check issues
     * 
     * @param string $email Recipient email
     * @param HealthCheckEvent $event The health check event
     */
    private function sendNotificationEmail(string $email, HealthCheckEvent $event): void
    {
        global $api;
        
        $subject = "Health Check Alert - " . $event->status->getFriendlyName();
        $body = $event->getSummary();
        $body .= "\n\nThis is an automated message from Zolinga Health Monitor.";
        
        // Using PHP's mail function as a fallback
        if (mail($email, $subject, $body)) {
            $api->log->info("system", "Health check notification sent to $email");
        } else {
            $api->log->error("system", "Failed to send health check notification to $email");
        }
    }
}
