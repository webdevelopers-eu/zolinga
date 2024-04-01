<?php

declare(strict_types=1);

namespace Zolinga\System\Types;


/**
 * The event can have one of these statuses.
 * 
 * The codes are same as HTTP status codes: https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
 * 
 * In general the status codes >= 300 are considered errors.
 * 
 * You can use the StatusEnum::isError() and StatusEnum::isOk() methods to check the status.
 * StatusEnum::UNDETERMINED is not an error and not OK. It is used as a default value. 
 * You can use StatusEnum::isUndetermined() to check for it.
 * 
 * Example: 
 *  
 *   $status = StatusEnum::tryFromString("OK");
 *   if ($status->isOk()) {
 *      echo "Everything is OK!";
 *   }
 * 
 *   $status = StatusEnum::tryFromString(404);
 *   if ($status->isError()) {
 *      echo "Problem!";
 *    }
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-02-02
 */
enum StatusEnum: int
{
    case UNDETERMINED = 0; // initial

        // OK
    case CONTINUE = 100;
    case PROCESSING = 102;
    case OK = 200;

        // Redirects
    case MULTIPLE_CHOICES = 300;
    case MOVED_PERMANENTLY = 301;
    case FOUND = 302;
    case SEE_OTHER = 303;
    case NOT_MODIFIED = 304;
    case TEMPORARY_REDIRECT = 307;
    case PERMANENT_REDIRECT = 308;

        // Errors
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case TIMEOUT = 408;
    case CONFLICT = 409;
    case PRECONDITION_FAILED = 412;
    case I_AM_A_TEAPOT = 418;
    case LOCKED = 423;
    case ERROR = 500;

    public function getEmoji(): string
    {
        return match (true) {
            $this === self::UNDETERMINED => '⭕',
            $this->value < 200 => '🔵',
            $this->value < 300 => '🟢',
            $this->value < 400 => '🟠',
            default => '🔴'
        };
    }

    public function getFriendlyName(): string
    {
        return match ($this) {
            self::OK => 'OK',
            default => ucwords(str_replace('_', ' ', $this->name)),
        };
    }

    public function isError(): bool
    {
        return $this->value >= 300;
    }

    public function isOk(): bool
    {
        return $this->value && $this->value < 300;
    }

    public function isUndetermined(): bool
    {
        return $this === StatusEnum::UNDETERMINED;
    }

    /**
     * Tries to guess the type from a string. Will match both the name and the value.
     *
     * @param string $status
     * @return StatusEnum|false
     */
    static function tryFromString(int|string $status): StatusEnum|false
    {
        $status = is_string($status) ? str_replace(' ', '_', strtoupper($status)) : $status;

        foreach (self::cases() as $case) {
            /** @var StatusEnum $case */
            if ($case->value == $status || $case->name == $status) {
                return $case;
            }
        }

        return false;
    }
}
