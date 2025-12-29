<?php

declare(strict_types=1);

namespace Zolinga\System\Exceptions;

/**
 * Thrown when CLI input contains parameters that were not defined via ZArgs::option().
 */
class CliUnknownInputException extends \InvalidArgumentException
{
    /**
     * @param array<string> $unknownParams
     * @param array<string> $supportedParams
     */
    public function __construct(
        public readonly array $unknownParams,
        public readonly array $supportedParams,
    ) {
        $unknown = $unknownParams ? ("Unknown parameter(s): " . implode(', ', $unknownParams)) : 'Unknown parameters.';
        $supported = $supportedParams ? ("\n\nSupported parameters:\n  " . implode("\n  ", $supportedParams)) : '';
        parent::__construct($unknown . $supported);
    }
}
