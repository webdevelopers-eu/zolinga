<?php

declare(strict_types=1);

namespace Zolinga\System\Events;

use ArrayObject, ArrayAccess;
use Zolinga\System\Types\OriginEnum;
use Zolinga\System\Types\StatusEnum;

/**
 * System event class that represents a request and a response.
 *
 * @author Daniel Sevcik <sevcik@webdevelopers.eu>
 * @date 2025-12-13
 */
class RemoteEvent extends RequestResponseEvent
{

    public readonly string $server;

    /**
     * Creates a new RemoteEvent instance that is intended to be dispatched to a remote Zolinga server.
     * 
     * Example:
     * 
     * ```php
     * use Zolinga\System\Events\RemoteEvent;
     * use Zolinga\System\Types\OriginEnum;
     * 
     * $event = new RemoteEvent(
     *    server: 'https://remote.zolinga.net',
     *    type: 'test:event',
     *    request: ['param1' => 'value1']
     * );
     * $event->dispatch();
     * echo "Response status: {$event->statusNiceName}: " . json_encode($event->response);
     * ```
     *
     * @param string $server The remote server URL where to dispatch the event. The URL must be valid and reachable.
     * @param string $type The event type in the form of URI
     * @param ArrayAccess<string, mixed>|array<string, mixed> $request
     * @param ArrayAccess<string, mixed>|array<string, mixed> $response
     * @throws \InvalidArgumentException
     */
    public function __construct(string $server, string $type, ArrayAccess|array $request = new ArrayObject, ArrayAccess|array $response = new ArrayObject)
    {
        if (!filter_var($server, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid server URL provided for RemoteEvent: " . $server);
        }

        parent::__construct($type, OriginEnum::REMOTE, $request, $response);
        $this->server = $server;
    }

    public function dispatch(): self
    {
        $startTime = microtime(true);

        $url = $this->buildGateURL();
        $payload = $this->buildGatePayload();

        [$httpStatus, $responseBody] = $this->postJson($url, $payload);
        $responseDecoded = json_decode($responseBody, true);

        if (!is_array($responseDecoded)) {
            throw new \RuntimeException(
                'RemoteEvent: Invalid JSON response from remote gate.',
                0,
                new \RuntimeException('HTTP ' . $httpStatus . ' Body: ' . substr((string)$responseBody, 0, 2000))
            );
        }

        if (($responseDecoded['type'] ?? null) === 'error') {
            throw new \RuntimeException(
                'RemoteEvent: Remote gate error: ' . ((string)($responseDecoded['message'] ?? '-'))
            );
        }

        if (!array_is_list($responseDecoded) || !isset($responseDecoded[0]) || !is_array($responseDecoded[0])) {
            throw new \RuntimeException(
                'RemoteEvent: Remote gate returned unexpected response format.',
                0,
                new \RuntimeException('HTTP ' . $httpStatus . ' Body: ' . substr((string)$responseBody, 0, 2000))
            );
        }

        $responseData = $responseDecoded[0];
        if (($responseData['uuid'] ?? null) !== $this->uuid) {
            throw new \RuntimeException('RemoteEvent: Response UUID does not match request UUID.');
        }

        $status = StatusEnum::tryFromString((int)($responseData['status'] ?? 0)) ?: StatusEnum::ERROR;
        $this->setStatus($status, (string)($responseData['message'] ?? ''));

        $this->response = new ArrayObject((array)($responseData['response'] ?? []));
        $this->totalTime = microtime(true) - $startTime;

        return $this;
    }

    /**
     * @return string
     */
    private function buildGateURL(): string
    {
        $base = rtrim($this->server, '/');
        $info = $this->buildInfoString();
        return $base . '/dist/system/gate/' . ($info !== '' ? ('?' . $info) : '');
    }

    /**
     * @return string
     */
    private function buildInfoString(): string
    {
        $op = $this->getRequestValue('op');
        $id = $this->getRequestValue('id');

        $info = $this->type;
        if (is_string($op) && $op !== '') {
            $info .= '/' . $op;
        }
        if ((is_string($id) && $id !== '') || is_int($id)) {
            $info .= ':' . $id;
        }
        return $info;
    }

    /**
     * @return array<int, array{uuid:string, type:string, origin:string, request: mixed}>
     */
    private function buildGatePayload(): array
    {
        return [[
            'uuid' => (string)$this->uuid,
            'type' => $this->type,
            'origin' => $this->origin->value,
            'request' => $this->normalizeForJson($this->request),
        ]];
    }

    /**
     * @param string $url
     * @param mixed $payload
     * @return array{0:int, 1:string} HTTP status code and response body
     */
    private function postJson(string $url, mixed $payload): array
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $json,
                'timeout' => 30,
                'ignore_errors' => true,
            ]
        ]);

        $body = file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('RemoteEvent: HTTP request failed (no response body).');
        }

        $httpStatus = 0;
        /** @var array<int, string>|null $http_response_header */
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
            $httpStatus = (int)$m[1];
        }

        return [$httpStatus, (string)$body];
    }

    /**
     * @param string $key
     * @return mixed
     */
    private function getRequestValue(string $key): mixed
    {
        if (is_array($this->request)) {
            return $this->request[$key] ?? null;
        }

        if ($this->request instanceof ArrayAccess && isset($this->request[$key])) {
            return $this->request[$key];
        }

        return null;
    }

    /**
     * Convert common Zolinga request containers to JSON-friendly values.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normalizeForJson(mixed $value): mixed
    {
        if ($value instanceof ArrayObject) {
            $value = $value->getArrayCopy();
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->normalizeForJson($v);
            }
            return $value;
        }

        if ($value instanceof \JsonSerializable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        throw new \InvalidArgumentException('RemoteEvent: Request contains non-serializable value of type ' . get_debug_type($value));
    }
}
