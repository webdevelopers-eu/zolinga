## Description

Dispatched by the installation controller for each new installation or patch file. The `*` suffix is the file extension (e.g., `system:install:script:sql`, `system:install:script:php`).

- **Event:** `system:install:script:*`
- **Emitted by:** `Zolinga\System\Installer\InstallController`
- **Event Type:** `\Zolinga\System\Events\InstallScriptEvent`
- **Origin:** `internal`

## Behavior

When a module is installed or updated, the install controller discovers new script files in `install/install/` and `install/install/updates/` directories. For each file, it dispatches `system:install:script:{extension}` so the appropriate handler can execute it.

## Known Handlers

| Extension | Handler | Module |
|---|---|---|
| `php` | `Zolinga\System\Installer\InstallPhpScript` | system |
| `sql` | `Zolinga\Database\InstallSqlScript` | zolinga-db |

## InstallScriptEvent Properties

The event carries the full path to the script file to be executed.

## See Also

- [system:install](../install.md) — the parent install event
