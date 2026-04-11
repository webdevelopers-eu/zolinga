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
- `kebab-case` for filenames (except wiki article titles).
8. Put DB model classes under `src/Model`.
9. Put enum-like keyword sets in `src/Types` where useful.
10. Listeners implement `Zolinga\System\Events\ListenerInterface`.
11. Services implement `Zolinga\System\Events\ServiceInterface`.
12. Provide a complete class example and brief comments explaining each public or protected property and/or accessor.

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
