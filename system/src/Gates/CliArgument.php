<?php

declare(strict_types=1);

namespace Zolinga\System\Gates;

class CliArgument
{
    /**
     * Type of the argument. It can be command line "parameter" or "event".
     *
     * @var string "parameter" or "event"
     */
    public readonly string $type;
    public readonly mixed $value;

    public function __construct(string $arg)
    {
        if (strpos($arg, '-') === 0) {
            [$this->type, $this->value] = $this->parseParameter($arg);
        } elseif (strpos($arg, '{') === 0) {
            $this->type = 'parameter';
            $this->value = json_decode($arg, true, 64, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_OBJECT_AS_ARRAY);
        } else { // Event
            $this->type = 'event';
            $this->value = $arg;
        }
    }

    /**
     * Parse single parameter
     *
     * @param string $arg
     * @return array<mixed>
     */
    private function parseParameter(string $arg): array
    {
        $name = ltrim($arg, '-');
        /** 
         * @var string $keyList 
         * @var string|bool $value
         */
        [$keyList, $value] = [...explode('=', $name, 2), true]; 
        
        $data = [];
        $pointer = &$data;

        foreach(explode('.', $keyList) as $key) {
            $pointer[$key] = [];
            $pointer = &$pointer[$key];
        }

        switch ($value) {
            case 'yes':                
            case 'true':
                $value = true;
                break;
            case 'no':
            case 'false':
                $value = false;
                break;
            case 'null':
                $value = null;
                break;
            default:
                if (is_numeric($value) && strpos($value, '.') !== false) {
                    $value = (float) $value;
                } elseif (is_numeric($value)) {
                    $value = (int) $value;
                } else {
                    $value = (string) $value;
                }
        }

        $pointer = $value;

        return [ 'parameter', $data ];
    }
}