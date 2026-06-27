---
name: system-serving-non-html-content
description: Use when serving JSON, plain text, or other non-HTML content from a URL path that goes through the Zolinga content pipeline (public/index.php). Covers the PreflightEvent + ContentEvent two-listener pattern for switching MIME type and generating the response body.
argument-hint: "<path-pattern> <mime-type>"
---

# Serving Non-HTML Content via the Content Pipeline

## Use When

- You need to serve JSON, plain text, or other non-HTML content from a URL path handled by `public/index.php` (not `install/dist/` endpoints).
- A path like `/.well-known/something` or `/api/some-doc` should return `application/json` instead of the default `text/html`.
- You are NOT creating a custom API endpoint in `install/dist/` (see `system-custom-api-endpoints` skill for that).

## How It Works

`public/index.php` dispatches two events in sequence:

1. **`system:content:preflight`** (PreflightEvent) — determines the MIME type. Default is `text/html`. A listener can change `$event->mimeType` to switch the content event class.
2. **`system:content:json`** (JsonContentEvent) — or `system:content:text` (TextContentEvent), depending on the MIME type set in step 1. A listener populates `$event->response` and sets status to OK.

You need **two listeners**: one for preflight (set MIME type), one for content (generate the body).

## Workflow

### 1. Preflight Listener — Set MIME Type

Create a listener class that checks `$event->path` and sets `$event->mimeType`:

```php
<?php

declare(strict_types=1);

namespace Example\Module\Content;

use Zolinga\System\Events\Content\PreflightEvent;
use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Types\ContentMimeTypesEnum;

class MyPreflightListener implements ListenerInterface
{
    private const JSON_PATHS = [
        '/.well-known/my-document',
    ];

    public function onPreflight(PreflightEvent $event): void
    {
        if (in_array($event->path, self::JSON_PATHS, true)) {
            $event->mimeType = ContentMimeTypesEnum::APPLICATION_JSON;
        }
    }
}
```

### 2. Content Listener — Generate the Body

Create a listener that checks `$event->path`, populates `$event->response`, and sets status to OK:

```php
<?php

declare(strict_types=1);

namespace Example\Module\Content;

use Zolinga\System\Events\Content\JsonContentEvent;
use Zolinga\System\Events\ListenerInterface;
use Zolinga\System\Types\StatusEnum;

class MyContentListener implements ListenerInterface
{
    public function onContentJson(JsonContentEvent $event): void
    {
        if ($event->status !== StatusEnum::UNDETERMINED) {
            return;
        }

        if ($event->path === '/.well-known/my-document') {
            global $api;
            $base = rtrim($api->config['baseURL'] ?? 'https://www.example.com', '/');

            $event->response = [
                'issuer' => $base,
                'some_endpoint' => $base . '/api/something',
            ];
            $event->setStatus(StatusEnum::OK, 'My Document');
        }
    }
}
```

### 3. Register Both Listeners in zolinga.json

```json
{
    "listen": [
        {
            "event": "system:content:preflight",
            "class": "\\Example\\Module\\Content\\MyPreflightListener",
            "method": "onPreflight",
            "origin": ["remote"],
            "priority": 0.5
        },
        {
            "event": "system:content:json",
            "class": "\\Example\\Module\\Content\\MyContentListener",
            "method": "onContentJson",
            "origin": ["remote"],
            "priority": 0.5
        }
    ]
}
```

### 4. Apply

Bump module version in `zolinga.json` and run `bin/zolinga` to rescan manifests.

## Key Points

- **Always check `$event->status === StatusEnum::UNDETERMINED`** in the content listener before setting a response. If another listener already claimed the path (status is OK), yours should not overwrite it.
- **Use `$event->path`**, not `$_SERVER['REQUEST_URI']`. The path may have been rewritten by a higher-priority preflight listener.
- **Available MIME types**: `APPLICATION_JSON` (dispatches `system:content:json`), `TEXT_PLAIN` (dispatches `system:content:text`), `TEXT_HTML` (default, dispatches `system:content:html`).
- **For JSON**: set `$event->response` to any JSON-encodable value. `getOutput()` calls `json_encode()` automatically.
- **For plain text**: set `$event->response` to a string. `TextContentEvent::getOutput()` returns it directly.
- **No web server config needed** — the content pipeline handles routing natively. No `.htaccess` or nginx rewrite rules required.
- **Build URLs from config**: use `$api->config['baseURL']` to construct absolute URLs so they work across environments.

## When NOT to Use This Pattern

- **Custom API endpoints** (POST handlers, webhooks, file uploads) — use `install/dist/` endpoints instead. See the `system-custom-api-endpoints` skill.
- **Static files** (favicon, robots.txt) — use the web-root overlay. See the `system-web-root-overlay` skill.
- **CMS pages** (HTML content) — use the default `text/html` pipeline or `zolinga-cms-page-authoring` skill.

## Troubleshooting

- **Web server overrides**: Apache, NGINX, or other web servers may intercept certain paths before they reach `public/index.php`. If your endpoint returns a 404 or unexpected response, check `.htaccess`, nginx config, or Apache directives for rewrite rules or alias blocks that may be catching the path (e.g. `/.well-known/*` is sometimes handled by certbot or other tools).
- **Existing files served directly**: If a physical file exists at the requested path (e.g. in the web-root overlay or `public/`), the web server may serve it directly without routing through the Zolinga framework. The content pipeline only runs when the request reaches `public/index.php`. Verify no static file shadows your path.
- **Path not reaching index.php**: Some web server configs only route requests to `index.php` when no matching file is found (fallback routing). Ensure your web server is configured to pass the target path through to PHP.

## References

- `system/src/Events/Content/PreflightEvent.php` — preflight event class
- `system/src/Events/Content/JsonContentEvent.php` — JSON content event class
- `system/src/Events/Content/TextContentEvent.php` — plain text content event class
- `system/src/Types/ContentMimeTypesEnum.php` — available MIME types
- `public/index.php` — the content pipeline entry point
- `system-custom-api-endpoints` skill — for `install/dist/` endpoints
- `system-web-root-overlay` skill — for static files at root URLs
- `system-create-handler` skill — for general listener creation conventions