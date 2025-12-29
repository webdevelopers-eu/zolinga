<?php

declare(strict_types=1);

namespace Zolinga\System\Helpers;

use Zolinga\System\Exceptions\CliInvalidInputException;
use Zolinga\System\Exceptions\CliUnknownInputException;

/**
 * Minimal yargs-like CLI argument validator for Zolinga events.
 *
 * It validates the already-parsed request array (produced by the CLI gate) and can:
 * - define options with aliases, types, defaults, required flags, and choices
 * - reject unknown parameters
 * - apply per-key coercion callbacks
 * - print help when requested
 */
final class ZArgs
{
    /** @var array<string, mixed> */
    private array $values;

    /** @var array<string, array{key: string, path: list<string>, alias: ?string, describe: ?string, choices: ?array, demandOption: bool, hasDefault: bool, default: mixed, type: ?string}> */
    private array $options = [];

    /** @var array<string, string> alias => key */
    private array $aliasToKey = [];

    /** @var array<string, callable> */
    private array $coerce = [];

    private ?string $helpKey = null;

    private bool $strict = false;

    /**
     * @param array<string, mixed> $request
     */
    public function __construct(array $request)
    {
        $this->values = $request;
    }

    /**
     * Define a supported option.
     *
     * @param string $key Option key (supports dot-separated paths).
     * @param ?string $alias Alias key (e.g. 'h' for help). Alias does not support dot paths.
     * @param ?string $describe Help description.
     * @param ?array<int, mixed> $choices Allowed values. If provided as Enum::cases(), parsed value will be converted to the enum case.
     * @param bool $demandOption Require presence (default counts as present).
     * @param ?string $type Supported: 'string', 'number', 'int', 'float', 'boolean'.
     * @param mixed $default Default value. If omitted, no default is applied.
     */
    public function option(
        string $key,
        ?string $alias = null,
        ?string $describe = null,
        ?array $choices = null,
        bool $demandOption = false,
        ?string $type = null,
        mixed $default = null,
    ): self {
        $hasDefault = func_num_args() >= 7;
        $path = $this->splitPath($key);

        $this->options[$key] = [
            'key' => $key,
            'path' => $path,
            'alias' => $alias,
            'describe' => $describe,
            'choices' => $choices,
            'demandOption' => $demandOption,
            'hasDefault' => $hasDefault,
            'default' => $default,
            'type' => $type,
        ];

        if ($alias !== null && $alias !== '') {
            $this->aliasToKey[$alias] = $key;
        }

        return $this;
    }

    /**
     * When enabled, unknown parameters cause CliUnknownInputException.
     * When disabled (default), unknown parameters are allowed.
     */
    public function strict(bool $enabled = true): self
    {
        $this->strict = $enabled;
        return $this;
    }

    /**
     * Apply a custom transformation/validation function to an option.
     *
     * The callable can throw to signal invalid input.
     *
     * @param string $key
     * @param callable $fn function(mixed $value, string $key, array<string, mixed> $all): mixed
     */
    public function coerce(string $key, callable $fn): self
    {
        $this->coerce[$key] = $fn;
        return $this;
    }

    /**
     * Configure a help flag key.
     *
     * If present/truthy in input, parse() prints generated help and returns without validating.
     */
    public function help(string $key = 'help'): self
    {
        $this->helpKey = $key;
        return $this;
    }

    /**
     * Validate and normalize input.
     *
     * @return array<string, mixed>
     * @throws CliUnknownInputException
     * @throws CliInvalidInputException
     */
    public function parse(): array
    {
        $this->applyAliases();

        if ($this->helpKey !== null) {
            $helpValue = $this->getByPath($this->values, $this->splitPath($this->helpKey));
            if ($helpValue) {
                echo $this->renderHelp();
                return $this->values;
            }
        }

        if ($this->strict) {
            $this->assertNoUnknownOptions();
        }
        $this->applyDefaultsAndRequired();
        $this->applyTypesAndChoices();
        $this->applyCoerce();

        return $this->values;
    }

    public function renderHelp(): string
    {
        $lines = [];
        $lines[] = "Supported parameters:";

        foreach ($this->options as $opt) {
            $key = $opt['key'];
            $alias = $opt['alias'];

            $left = $this->formatKey($key);
            if (is_string($alias) && $alias !== '') {
                $left .= ", " . $this->formatKey($alias);
            }

            $rightParts = [];
            if (is_string($opt['describe']) && $opt['describe'] !== '') {
                $rightParts[] = $opt['describe'];
            }
            if ($opt['demandOption']) {
                $rightParts[] = 'required';
            }
            if ($opt['hasDefault']) {
                $rightParts[] = 'default=' . $this->stringify($opt['default']);
            }
            if (is_string($opt['type']) && $opt['type'] !== '') {
                $rightParts[] = 'type=' . $opt['type'];
            }
            if (is_array($opt['choices'])) {
                $rightParts[] = 'choices=' . $this->stringify($this->choicesToDisplayList($opt['choices']));
            }

            $lines[] = sprintf("  %-28s %s", $left, implode(' | ', $rightParts));
        }

        if ($this->helpKey !== null && !isset($this->options[$this->helpKey])) {
            $lines[] = sprintf("  %-28s %s", $this->formatKey($this->helpKey), 'show this help');
        }

        return implode("\n", $lines) . "\n";
    }

    private function applyAliases(): void
    {
        foreach ($this->aliasToKey as $alias => $key) {
            $aliasPath = $this->splitPath($alias);
            $keyPath = $this->splitPath($key);

            $hasKey = $this->hasByPath($this->values, $keyPath);
            $hasAlias = $this->hasByPath($this->values, $aliasPath);

            if (!$hasKey && $hasAlias) {
                $value = $this->getByPath($this->values, $aliasPath);
                $this->setByPath($this->values, $keyPath, $value);
            }

            if ($hasAlias) {
                $this->unsetByPath($this->values, $aliasPath);
            }
        }
    }

    private function assertNoUnknownOptions(): void
    {
        $allowed = $this->supportedParamList();
        $allowedLeafPaths = $this->supportedLeafPathSet();

        $unknown = [];
        foreach ($this->collectLeafPaths($this->values) as $leafPath) {
            if (!isset($allowedLeafPaths[$leafPath])) {
                $unknown[] = $this->formatKey($leafPath);
            }
        }

        if ($unknown) {
            sort($unknown);
            throw new CliUnknownInputException($unknown, $allowed);
        }
    }

    private function applyDefaultsAndRequired(): void
    {
        foreach ($this->options as $opt) {
            $path = $opt['path'];
            $key = $opt['key'];

            if (!$this->hasByPath($this->values, $path)) {
                if ($opt['hasDefault']) {
                    $this->setByPath($this->values, $path, $opt['default']);
                } elseif ($opt['demandOption']) {
                    throw new CliInvalidInputException(
                        "Missing required parameter: " . $this->formatKey($key),
                        $this->supportedParamList(),
                        $key,
                        null,
                    );
                }
            }
        }
    }

    private function applyTypesAndChoices(): void
    {
        foreach ($this->options as $opt) {
            $path = $opt['path'];
            $key = $opt['key'];

            if (!$this->hasByPath($this->values, $path)) {
                continue;
            }

            $value = $this->getByPath($this->values, $path);

            if (is_string($opt['type']) && $opt['type'] !== '') {
                $value = $this->enforceType($opt['type'], $value, $key);
            }

            if (is_array($opt['choices'])) {
                $value = $this->enforceChoices($opt['choices'], $value, $key);
            }

            $this->setByPath($this->values, $path, $value);
        }
    }

    private function applyCoerce(): void
    {
        foreach ($this->coerce as $key => $fn) {
            $path = $this->splitPath($key);
            if (!$this->hasByPath($this->values, $path)) {
                continue;
            }

            $current = $this->getByPath($this->values, $path);

            try {
                $next = $fn($current, $key, $this->values);
            } catch (CliInvalidInputException $e) {
                throw $e;
            } catch (\Throwable $e) {
                throw new CliInvalidInputException(
                    "Invalid value for " . $this->formatKey($key) . ": " . $e->getMessage(),
                    $this->supportedParamList(),
                    $key,
                    $current,
                    $e,
                );
            }

            $this->setByPath($this->values, $path, $next);
        }
    }

    /**
     * @return array<string>
     */
    private function supportedParamList(): array
    {
        $all = [];
        foreach ($this->options as $opt) {
            $all[] = $this->formatKey($opt['key']);
            if (is_string($opt['alias']) && $opt['alias'] !== '') {
                $all[] = $this->formatKey($opt['alias']);
            }
        }
        if ($this->helpKey !== null && !isset($this->options[$this->helpKey])) {
            $all[] = $this->formatKey($this->helpKey);
        }
        $all = array_values(array_unique($all));
        sort($all);
        return $all;
    }

    /**
     * @return array<string, true>
     */
    private function supportedLeafPathSet(): array
    {
        $set = [];
        foreach ($this->options as $opt) {
            $set[$opt['key']] = true;
        }
        if ($this->helpKey !== null) {
            $set[$this->helpKey] = true;
        }
        return $set;
    }

    /**
     * @param array<int, mixed> $choices
     * @return list<string|int|float|bool|null>
     */
    private function choicesToDisplayList(array $choices): array
    {
        $out = [];
        foreach ($choices as $c) {
            if ($c instanceof \UnitEnum) {
                $out[] = $c instanceof \BackedEnum ? $c->value : $c->name;
            } else {
                $out[] = is_scalar($c) || $c === null ? $c : $this->stringify($c);
            }
        }
        return $out;
    }

    private function enforceChoices(array $choices, mixed $value, string $key): mixed
    {
        if ($choices === []) {
            return $value;
        }

        $enumCases = array_values(array_filter($choices, fn ($c) => $c instanceof \UnitEnum));
        if ($enumCases) {
            return $this->enforceEnumChoices($enumCases, $value, $key);
        }

        foreach ($choices as $allowed) {
            if ($value === $allowed) {
                return $value;
            }
            if (is_scalar($value) && is_scalar($allowed) && (string) $value === (string) $allowed) {
                return $allowed;
            }
        }

        throw new CliInvalidInputException(
            "Invalid value for " . $this->formatKey($key) . ": " . $this->stringify($value) .
                ". Allowed values: " . $this->stringify($this->choicesToDisplayList($choices)),
            $this->supportedParamList(),
            $key,
            $value,
        );
    }

    /**
     * @param list<\UnitEnum> $cases
     */
    private function enforceEnumChoices(array $cases, mixed $value, string $key): \UnitEnum
    {
        foreach ($cases as $case) {
            if ($value === $case) {
                return $case;
            }

            if (is_scalar($value)) {
                if ((string) $value === $case->name) {
                    return $case;
                }
                if ($case instanceof \BackedEnum && (string) $value === (string) $case->value) {
                    return $case;
                }
            }
        }

        $allowed = array_map(
            fn (\UnitEnum $c) => $c instanceof \BackedEnum ? ($c->name . "=" . $this->stringify($c->value)) : $c->name,
            $cases
        );

        throw new CliInvalidInputException(
            "Invalid value for " . $this->formatKey($key) . ": " . $this->stringify($value) .
                ". Allowed values: " . $this->stringify($allowed),
            $this->supportedParamList(),
            $key,
            $value,
        );
    }

    private function enforceType(string $type, mixed $value, string $key): mixed
    {
        $type = strtolower($type);

        return match ($type) {
            'string' => $this->toString($value, $key),
            'boolean', 'bool' => $this->toBool($value, $key),
            'number' => $this->toNumber($value, $key),
            'int', 'integer' => $this->toInt($value, $key),
            'float', 'double' => $this->toFloat($value, $key),
            default => throw new CliInvalidInputException(
                "Unsupported type " . $this->stringify($type) . " for " . $this->formatKey($key),
                $this->supportedParamList(),
                $key,
                $value,
            ),
        };
    }

    private function toString(mixed $value, string $key): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }
        throw new CliInvalidInputException(
            "Invalid type for " . $this->formatKey($key) . ": expected string, got " . gettype($value),
            $this->supportedParamList(),
            $key,
            $value,
        );
    }

    private function toBool(mixed $value, string $key): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            return match ($v) {
                '1', 'true', 'yes', 'y', 'on' => true,
                '0', 'false', 'no', 'n', 'off', '' => false,
                default => throw new CliInvalidInputException(
                    "Invalid boolean for " . $this->formatKey($key) . ": " . $this->stringify($value),
                    $this->supportedParamList(),
                    $key,
                    $value,
                ),
            };
        }

        throw new CliInvalidInputException(
            "Invalid type for " . $this->formatKey($key) . ": expected boolean, got " . gettype($value),
            $this->supportedParamList(),
            $key,
            $value,
        );
    }

    private function toNumber(mixed $value, string $key): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        throw new CliInvalidInputException(
            "Invalid number for " . $this->formatKey($key) . ": " . $this->stringify($value),
            $this->supportedParamList(),
            $key,
            $value,
        );
    }

    private function toInt(mixed $value, string $key): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', trim($value))) {
            return (int) $value;
        }
        throw new CliInvalidInputException(
            "Invalid integer for " . $this->formatKey($key) . ": " . $this->stringify($value),
            $this->supportedParamList(),
            $key,
            $value,
        );
    }

    private function toFloat(mixed $value, string $key): float
    {
        if (is_float($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }
        throw new CliInvalidInputException(
            "Invalid float for " . $this->formatKey($key) . ": " . $this->stringify($value),
            $this->supportedParamList(),
            $key,
            $value,
        );
    }

    /**
     * @param array<string, mixed> $array
     * @param list<string> $path
     */
    private function hasByPath(array $array, array $path): bool
    {
        $cursor = $array;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return false;
            }
            $cursor = $cursor[$segment];
        }
        return true;
    }

    /**
     * @param array<string, mixed> $array
     * @param list<string> $path
     */
    private function getByPath(array $array, array $path): mixed
    {
        $cursor = $array;
        foreach ($path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }

    /**
     * @param array<string, mixed> $array
     * @param list<string> $path
     */
    private function setByPath(array &$array, array $path, mixed $value): void
    {
        $cursor = &$array;
        foreach ($path as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor = &$cursor[$segment];
        }
        $cursor = $value;
    }

    /**
     * @param array<string, mixed> $array
     * @param list<string> $path
     */
    private function unsetByPath(array &$array, array $path): void
    {
        if ($path === []) {
            return;
        }

        $cursor = &$array;
        $last = array_pop($path);
        foreach ($path as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                return;
            }
            $cursor = &$cursor[$segment];
        }
        unset($cursor[$last]);
    }

    /**
     * @param array<string, mixed> $array
     * @return list<string>
     */
    private function collectLeafPaths(array $array, string $prefix = ''): array
    {
        $out = [];
        foreach ($array as $k => $v) {
            $key = (string) $k;
            $path = $prefix === '' ? $key : ($prefix . '.' . $key);
            if (is_array($v)) {
                $out = [...$out, ...$this->collectLeafPaths($v, $path)];
            } else {
                $out[] = $path;
            }
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    private function splitPath(string $key): array
    {
        return $key === '' ? [] : explode('.', $key);
    }

    private function formatKey(string $key): string
    {
        if (strlen($key) === 1) {
            return '-' . $key;
        }
        return '--' . $key;
    }

    private function stringify(mixed $value): string
    {
        if ($value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum
                ? (get_class($value) . '::' . $value->name . '(' . json_encode($value->value) . ')')
                : (get_class($value) . '::' . $value->name);
        }
        if (is_string($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_scalar($value) || $value === null) {
            return json_encode($value);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
