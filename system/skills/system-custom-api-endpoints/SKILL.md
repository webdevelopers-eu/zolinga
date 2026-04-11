---
name: system-custom-api-endpoints
description: Use when creating module-specific API endpoints in install/dist and wiring them to load the Zolinga system correctly.
argument-hint: "<module-name> <endpoint-path>"
---

# Zolinga Custom API Endpoints

## Use When

- Creating a custom HTTP endpoint for a module.
- Refactoring endpoint bootstrap/loading behavior.

## Workflow

1. Place endpoint files in `modules/<module-name>/install/dist/...`.
2. Access endpoint via `/dist/<module-name>/...` URL path.
3. Follow bootstrap/loading approach from `system/install/dist/gate/index.php`.
4. Keep endpoint logic aligned with module and service conventions.

## Documentation Abstract

- Start with runtime request handling docs to understand endpoint entry flow.
- Use module anatomy and manifest docs to keep endpoint code consistent with module conventions.
- Use path docs when resolving dist/config/system file access.

## References

- `system/wiki/Zolinga Core/Running the System/Page Request.md`
- `system/wiki/Zolinga Core/Running the System/AJAX.md`
- `system/wiki/Zolinga Core/Module Anatomy.md`
- `system/wiki/Zolinga Core/Manifest File.md`
- `system/wiki/Zolinga Core/Paths and Zolinga URI.md`
- `system/wiki/Zolinga Core/Services.md`
