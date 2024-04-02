<?php
declare(strict_types=1);

namespace Zolinga\System\Config;
use ArrayObject;

/**
 * This class represents a configuration array object.
 * It provides methods to manipulate and access the configuration data.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @extends ArrayObject<string, mixed>
 */
class ConfigArrayObject extends ArrayObject
{

    /**
     * Path to the manifest file
     * @var string
     */
    protected string|null $filePath;

    const FLAGS_NONE = 0;
    const FLAGS_REMOVE_COMMENTS = 1;

    public function __construct(string $filePath = null, int $flags = self::FLAGS_NONE)
    {
        parent::__construct([]);

        if (is_string($filePath)) {
            $this->loadFile($filePath, $flags);
        }
    }

    public function loadFile(string $filePath, int $flags = self::FLAGS_NONE): void
    {
        $this->filePath = $filePath;

        $rawData = file_get_contents($this->filePath) ?: 'false';
        $parsedData = json_decode($rawData, true);

        if (!is_array($parsedData)) {
            throw new \Exception('Manifest file ' . $this->filePath . ' is not valid JSON manifest file.');
        }

        $this->loadData($parsedData, $flags);
    }

    /**
     * Replace the data in the object with the data in the array.
     *
     * @param array<mixed> $data
     * @return void
     */
    public function loadData(array $data, int $flags = self::FLAGS_NONE): void
    {
        $this->exchangeArray($this->preprocessData($data, $flags));
    }

    /**
     * Preprocesses the data array.
     *
     * @param iterable<string, mixed>|ArrayObject<string, mixed> $data The data array to be preprocessed.
     * @param int $flags (optional) Flags to control the preprocessing behavior. Defaults to self::FLAGS_NONE.
     * @return iterable<string, mixed>|ArrayObject<string, mixed> The preprocessed data array.
     */
    protected function preprocessData(iterable $data, int $flags = self::FLAGS_NONE): iterable
    {
        if ($flags & self::FLAGS_REMOVE_COMMENTS) {
            $data = $this->removeComments($data);
        }

        return $data;
    }

    /**
     * Filter out all keys that start with "#"
     *
     * @param array<mixed> $arr
     * @return array<mixed>
     */
    private function removeComments(iterable $arr): iterable
    {
        // @phpstan-ignore-next-line
        $arr = array_filter($arr, function (string $key) {
            return substr($key, 0, 1) !== "#";
        }, ARRAY_FILTER_USE_KEY);

        foreach ($arr as $key => &$value) {
            if (is_array($value)) {
                $value = $this->removeComments($value);
            }
        }
        return $arr;
    }
}
