## Description

Dispatched by `index.php` for each `$_REQUEST` parameter in POST and GET requests. The `*` suffix is the request key name.

- **Event:** `system:request:*`
- **Emitted by:** `public/index.php`
- **Event Type:** `\Zolinga\System\Events\RequestEvent`
- **Origin:** `remote`

## Behavior

For each incoming HTTP request, `index.php` iterates over `$_REQUEST` parameters and dispatches `system:request:{key}` for each one. This allows modules to hook into specific request parameters.

## Known Handlers

| Request Key | Handler | Module |
|---|---|---|
| `ab` | `Zolinga\Autoblog\Listeners\AutoblogProcessor` | zolinga-autoblog |
| `sudo` | `Ipd\App\Api\SudoApi` | ipdefender |

## See Also

- Processing POST and GET wiki article
