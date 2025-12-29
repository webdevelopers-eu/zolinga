<?php

declare(strict_types=1);

namespace Zolinga\System\Exceptions;

/**
 * Thrown when CLI input fails validation (missing required option, invalid type, invalid choice, etc.).
 */
class CliInvalidInputException extends \InvalidArgumentException
{
    /**
     * @param string $message
     * @param array<string> $supportedParams
     * @param ?string $key
     * @param mixed $value
     * @param ?\Throwable $previous
     */
    public function __construct(
        string $message,
        public readonly array $supportedParams = [],
        public readonly ?string $key = null,
        public readonly mixed $value = null,
        ?\Throwable $previous = null,
    ) {
        $supported = $supportedParams ? ("\n\nSupported parameters:\n  " . implode("\n  ", $supportedParams)) : '';
        parent::__construct($message . $supported, 0, $previous);
    }
}
