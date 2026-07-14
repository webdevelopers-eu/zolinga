<?php

/**
 * MCP (Model Context Protocol) non-streaming HTTP gateway.
 *
 * Thin entry point: if the client sent an `Mcp-Session-Id` header, stuff it
 * into the PHP session cookie so the bootstrap's `session_start()` picks it
 * up naturally. Then load the system, hand the raw request body to
 * {@see \Zolinga\System\Mcp\McpServer} and let it dispatch the JSON-RPC 2.0
 * request. The server sends back `Mcp-Session-Id: <session_id()>` on every
 * reply so clients can resume the session.
 *
 * HTTP method handling lives in {@see \Zolinga\System\Mcp\McpServer::run()}
 * so the system logger is available for the access log on every path
 * (POST, GET, DELETE, etc.).
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @date 2026-06-03
 */

declare(strict_types=1);

namespace Zolinga\System\Mcp;

use Zolinga\System\Mcp\Exceptions\McpException;

// If the client sent an `Mcp-Session-Id` header, treat it as the PHP session
// id for this request. Per the MCP spec, the id must be visible ASCII in the
// 0x21-0x7E range. Anything else is dropped (PHP mints a fresh id). Bounded
// to 64 chars to match the MCP spec.
$hadSessionCookie = isset($_COOKIE[session_name()]);
// $headerSession = $_SERVER['HTTP_MCP_SESSION_ID'] ?? null;
// if (is_string($headerSession)) {
//     if (strlen($headerSession) > 64 || preg_match('/\A[\x21-\x7E]+\z/', $headerSession) !== 1) {
//         // Don't set $_COOKIE[session_name()] — drop the bad value.
//     } else {
//         $_COOKIE[session_name()] = $headerSession;
//     }
// }

ini_set('session.use_cookies', 0); // disable session cookies

// CORS: emit headers early (before session or output). For preflight (OPTIONS)
// this sends 204 and exits. For actual requests it sets the CORS headers and
// continues. Must run before session_start() to avoid headers already sent.
require($_SERVER['DOCUMENT_ROOT'] . '/../modules/zolinga-oauth/src/CorsHelper.php');
\Zolinga\OAuth\CorsHelper::emitHeaders('/mcp/');

require($_SERVER['DOCUMENT_ROOT'] . '/../system/loader.php');

// if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
//     header('Mcp-Session-Id: ' . session_id());
// }

try {
    (new McpServer())->run();
} catch (McpException $e) {
    (new McpServer())->sendError($e);
} finally {
    if (!$hadSessionCookie) {
        // If we created a session for this request, destroy it so we don't
        // leave a session file on the server. The client will get the
        // `Mcp-Session-Id` header in the response and can resume the session
        // on the next request.
        session_destroy();
    }
    header('MCP-Session-Reset: ' . (int)(session_status() === PHP_SESSION_NONE));
}