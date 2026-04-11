# Agents Guidance

This repository uses the Agent Skills system in `.agents/skills/` as the primary source of agent guidance.

## Primary Rules

- Prefer loading and applying relevant skills from `.agents/skills/`.
- Treat `.github/copilot-instructions.md` as legacy compatibility guidance.
- Keep new reusable guidance in skills, not in monolithic instruction files.

This is a modular system with multiple independent git repositories.
The main git repository is in the project root and other repositories are in `modules/*`.

## Skills Available

Repository core skills:
- `system-coding-standards`
- `system-module-development`
- `system-manifest-events`
- `system-php-coding-style`
- `system-database-schema-updates`
- `system-content-tags`
- `system-web-components`
- `system-translations`
- `system-custom-api-endpoints`
- `system-documentation`
- `system-run-and-test`

Module-provided skills appear as `{module-name}-{skill-name}` in `.agents/skills/` after module installation.
