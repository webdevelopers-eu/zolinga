---
name: system-php-coding-style
description: Use when writing or reviewing PHP code in this repository to enforce strict typing, naming, file layout, and listener/service interface conventions.
argument-hint: "<module-name> [class-or-file]"
---

# Zolinga PHP Coding Style

## Use When

- Implementing new PHP classes, listeners, services, or models.
- Refactoring PHP for repository style compliance.

## Rules

1. Add `declare(strict_types=1);` at file top.
2. Target PHP 8.4 compatibility.
3. Place PHP code under `modules/<module-name>/src/`.
4. Keep function parameters typed and declare return types.
5. Add concise PHPDoc; include usage examples when useful.
6. Split long functions into smaller units.
7. Use naming conventions:
- `camelCase` for variables/methods.
- `PascalCase` for classes.
- Up to 4 consecutive uppercase initials stay uppercased, e.g. `XMLHttpRequest`, `HTTPClient`, `getURL()`.
- `kebab-case` for filenames (except wiki article titles and PHP class files — see rule 8).
8. **PHP class files must mirror the namespace path after the autoload prefix, using the exact class name as filename.** Given `"Zolinga\\Seo\\": "src/"` in `zolinga.json` autoload, the class `Zolinga\Seo\Listeners\IndexNowListener` must be at `<module>/src/Listeners/IndexNowListener.php`. This is not kebab-case — class files use PascalCase filenames matching the class name. Without this the autoloader won't find the class.
9. Put DB model classes under `src/Model`.
10. Put enum-like keyword sets in `src/Types` where useful.
11. Listeners implement `Zolinga\System\Events\ListenerInterface`.
12. Services implement `Zolinga\System\Events\ServiceInterface`.
13. Provide a complete class example and brief comments explaining each public or protected property and/or accessor.
14. Use `$event::STATUS_*` constants (e.g. `$event::STATUS_OK`, `$event::STATUS_ERROR`, `$event::STATUS_BAD_REQUEST`) when calling `$event->setStatus()`. Never pass raw integers — `setStatus()` accepts `StatusEnum`. For dynamic HTTP codes use `StatusEnum::tryFrom($code) ?? $event::STATUS_ERROR`.

## Documentation Abstract

- Use `Module Anatomy.md` for source placement conventions.
- Use `Services.md` and `Events and Listeners.md` to align service/listener implementation style.
- Use API class references when matching method/event typing patterns.

## References

- `system/wiki/Zolinga Core/Module Anatomy.md`
- `system/wiki/Zolinga Core/Services.md`
- `system/wiki/Zolinga Core/Events and Listeners.md`
- `system/wiki/Zolinga Core/Manifest File.md`
- `system/wiki/ref/class/Zolinga/System/Events/Event.md`
- `system/wiki/ref/class/Zolinga/System/Events/AuthorizeEvent.md`
- `system/wiki/ref/class/Zolinga/System/Events/ContentEvent.md`
