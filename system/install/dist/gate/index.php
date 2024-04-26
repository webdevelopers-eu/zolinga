<?php

/**
 * @author Daniel Sevcik <danny@zolinga.net>
 * @package Zolinga
 */

declare(strict_types=1);

namespace Zolinga\System\Gates;

use Zolinga\System\Events\RequestResponseEvent;
use const Zolinga\System\ROOT_DIR;


require($_SERVER['DOCUMENT_ROOT'] . '/../system/loader.php');

// Process JSON requests.
$requests = json_decode(file_get_contents('php://input') ?: 'false', true);
$responses = [];

if (!$requests) {
    echo json_encode([
        "error" => "Invalid request data."
    ]);
    exit;
}

foreach ($requests as $data) {
    $time = microtime(true);
    if (!isset($data['type']) || !isset($data['request'])) {
        echo json_encode([
            "error" => "Invalid request data.",
            "data" => $data
        ]);
        exit;
    }


    $event = new RequestResponseEvent($data['type'], RequestResponseEvent::ORIGIN_REMOTE, $data['request']);
    $event->uuid = $data['uuid'];
    $event->dispatch();

    $responses[] = array(
        "uuid" => $event->uuid,
        "type" => $event->type,
        "origin" => $event->origin->value,
        "status" => $event->status->value,
        "statusName" => $event->status->name,
        "statusNiceName" => $event->statusNiceName,
        "message" => $event->message,
        "response" => $event->response,
        "time" => microtime(true) - $time,
        "ok" => $event->isOk()
    );
}

echo json_encode($responses);
