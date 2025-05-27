<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

use Zolinga\System\Types\StatusEnum;
use Zolinga\System\Types\OriginEnum;
use Zolinga\System\Types\SeverityEnum;

/**
 * Event for healthcheck operations.
 * 
 * This event is used to perform health checks on the system and report any issues.
 * 
 * @author GitHub Copilot <copilot@github.com>
 * @since 2025-05-23
 * 
 * @property-read array $reports Collection of health check reports from various components
 * @property-read ?string $notifyEmail Email address to send notifications to if issues are found
 */
class HealthCheckEvent extends RequestResponseEvent implements StoppableInterface
{
    use StoppableTrait;

    /**
     * Private backing field for reports collection
     */
    public private(set) array $reports = [];
    
    /**
     * Private backing field for notification email
     */
    public readonly ?string $notifyEmail;

    /**
     * Constructor.
     * 
     * @param string $type Event type
     * @param OriginEnum $origin Event origin
     * @param string|null $notifyEmail Optional email to send notifications to
     */
    public function __construct(string $type, OriginEnum $origin, ?string $notifyEmail = null)
    {
        parent::__construct($type, $origin);
        $this->notifyEmail = $notifyEmail;
    }


    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'status':
                $status = parent::__get($name);
                return $status == StatusEnum::UNDETERMINED ? StatusEnum::OK : $status;
            case 'message':
                return parent::__get('status') == StatusEnum::UNDETERMINED ? 'OK' : parent::__get('message');
            default:
                return parent::__get($name);
        }
    }

    /**
     * Report a health check result for a component
     * 
     * @param string $component Name of the component being checked
     * @param SeverityEnum $severity Status of the component
     * @param string $description Description of the health status or issue
     * @return self Returns $this for method chaining
     */
    public function report(string $component, SeverityEnum $severity, string $description): self
    {
        global $api;

        $isError = !in_array($severity, [SeverityEnum::INFO, SeverityEnum::WARNING]);

        $this->reports[] = [
            'component' => $component,
            'severity' => $severity,
            'description' => $severity->getEmoji() . ' ' . $description,
            'timestamp' => time()
        ];
        
        // If any component reports an error, update the event status
        if ($isError) {
            $this->setStatus(StatusEnum::ERROR, "Health check detected issues");
        }

        $api->log->log($severity, "system", "Health check for $component: $description");
        
        return $this;
    }

    public function setStatus(StatusEnum $status, string $message): StatusEnum
    {
        global $api;

        // If the status is already set to an error, do not override it
        if ($status->isOk()) { // OK is default we set only errors
            return $this->status;
        }
        
        return parent::setStatus($status, $message);
    }

    /**
     * Get a formatted summary of all health reports
     * 
     * @return string Formatted health report summary
     */
    public function getSummary(): string
    {
        if ($this->status->isOk()) {
            return "All components are healthy.";
        }
        
        // Generate a summary of the health check reports
        $errors = [];        
        foreach ($this->reports as $report) {
            if (!in_array($report['severity'], [SeverityEnum::INFO, SeverityEnum::WARNING])) {
                $errors[$report['component']] = $report['component'];
            }
        }
        
        return "Failed components: " . implode(', ', $errors) . ".";
    }
}
