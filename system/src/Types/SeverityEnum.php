<?php

declare(strict_types=1);

namespace Zolinga\System\Types;

/**
 * The message can have one of these severities
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-02-02
 */
enum SeverityEnum: string
{
    // Those strictly map to method names on $api->log service, so if
    // you add more, update the log service to support them.
    case TIP = 'tip';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';

    public function getEmoji(): string
    {
        return match (true) {
            $this === self::TIP => '💡',
            $this === self::INFO => '🔵',
            $this === self::WARNING => '🟠',
            $this === self::ERROR => '🔴',
            // default => '⚫'
        };
    }
}
