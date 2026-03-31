## Description

Executes PHP installation and update scripts during module installation.

- **Event:** `system:install:script:php`
- **Class:** `Zolinga\System\Installer\InstallPhpScript`
- **Method:** `onInstall`
- **Origin:** `internal`
- **Event Type:** `\Zolinga\System\Events\InstallScriptEvent`

## Behavior

When the install controller finds a `.php` file in a module's `install/install/` or `install/install/updates/` directory, this handler includes and executes it.

## See Also

- [system:install:script:*](wildcard.md) — the wildcard install script event
- PHP Installation Scripts wiki article
