<?php

declare(strict_types=1);

/**
 * This file is the entry point for page HTTP requests.
 * It is responsible for processing the request and generating the response.
 * It is also responsible for loading the system.
 * 
 * It fires the following events with "remote" origin:
 * 
 * - system:request:<key> - for each key in $_REQUEST
 * - system:content - event that expects content to be generated
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-02-02
 */

namespace Zolinga\System\Gate\PublicGate;

/** @var \Zolinga\System\Api $api */

use Zolinga\System\Events\{ContentEvent, RequestEvent};
use Exception, ArrayObject;

// We serve only page requests for files without extensions (without dots) or with extensions
// .html, .htm, .php, .asp, or directories due to performance concerns.
// Skilled users should configure web server so this is not needed here...
if (!preg_match('@(?:\.html?|\.php|\.asp|/[^.]*/?)$@', $_SERVER['PATH_INFO'] ?? '/')) {
    if ($_SERVER['PATH_INFO'] ?? '' === '/favicon.ico') {
        http_response_code(200);
        header('Content-Type: image/x-icon');
        echo file_get_contents(__DIR__ . '/favicon-zolinga.ico');
        exit;
    }
    http_response_code(404);
    exit;
}

// Check if the script is running over HTTP
if (php_sapi_name() === 'cli') {
    throw new Exception("This script must be run through a web server and not CLI or other means.");
}

// Load the system
require(dirname(__DIR__) . '/system/loader.php');

(function ($api) {
    // Process requests by iterating through keys and triggering "system:request:$key" events.
    // While we don't necessarily anticipate a response, the response object is included for compatibility
    // with AJAX requests that may require a response.
    foreach ($_REQUEST as $key => $value) {
        if (!is_array($value)) {
            $value = ["value" => $value];
        }
        $api->dispatchEvent(new RequestEvent(
            type: "system:request:$key",
            origin: RequestEvent::ORIGIN_REMOTE,
            request: new ArrayObject($value)
        ));
    }

    // Trigger an "system:content" event designed to awaken code within a CMS, 
    // allowing it to autonomously generate and output the HTTP server response 
    // to stdout as needed. 
    // $_SERVER['REQUEST_URI'] contains also query string so extract just the path
    $contentEvent = new ContentEvent($_SERVER['PATH_INFO'] ?? rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'));

    $api->dispatchEvent($contentEvent);
    
    if (!$contentEvent->isDefaultPrevented()) {
        if (headers_sent($file, $line)) {
            trigger_error("Headers already sent in $file on line $line . If you want to produce content on your own call \$event->preventDefault() on the $contentEvent", E_USER_WARNING);
        } else {
            header('Content-Type: text/html; charset=utf-8');
        }
        http_response_code($contentEvent->status->value);
        echo $contentEvent->getContentHTML();
    }

})($api);
