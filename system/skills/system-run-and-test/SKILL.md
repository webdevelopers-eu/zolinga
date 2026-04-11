---
name: system-run-and-test
description: Use when running Zolinga locally, triggering CLI-origin events, evaluating scripts, and finding the correct run/test command workflow.
argument-hint: "[event-name] [params]"
---

# Zolinga Run and Test

## Use When

- Running local development server or CLI events.
- Executing scripts for diagnostics.
- Validating behavior after code changes.

## Workflow

1. Discover CLI capabilities with `bin/zolinga --help`.
2. Trigger CLI-origin events with `bin/zolinga <event-name> <params>`.
3. Execute ad hoc scripts with `bin/zolinga --execute=\"<path>\"`.
4. Evaluate short snippets with `bin/zolinga --eval=\"<php-code>\"`.
5. Start local web server with `bin/zolinga --server=<host>:<port>` and optional `--xdebug`.
6. Use `config/local.json` then `config/global.json` (`baseURL`) to determine front-end URL defaults.
7. Create a new module with `bin/zolinga skeleton:module --name=<module-name>`.
8. Generate Apache config with `bin/zolinga skeleton:apache ...` (see `system/wiki/ref/event/skeleton/apache.md`).

## Documentation Abstract

- Begin with `Running the System.md` for execution modes.
- Use command-line and custom script docs for CLI workflows.
- Use request and AJAX pages for runtime behavior checks after deployment.

## References

- `system/wiki/Zolinga Core/Running the System.md`
- `system/wiki/Zolinga Core/Running the System/Command Line.md`
- `system/wiki/Zolinga Core/Running the System/Custom Scripts.md`
- `system/wiki/Zolinga Core/Running the System/Page Request.md`
- `system/wiki/Zolinga Core/Running the System/AJAX.md`
- `system/wiki/ref/event/skeleton/apache.md`
- `system/wiki/templates/Running the System.md`
- `system/wiki/Zolinga Core/Installing Additional Modules.md`
