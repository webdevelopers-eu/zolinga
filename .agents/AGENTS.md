# Agents Guidance

This repository uses the Agent Skills system in `.agents/skills/` as the primary source of agent guidance.

## Primary Rules

- Prefer loading and applying relevant skills from `.agents/skills/`.
- If you are asked to do anything that is already covered by an existing skill, say "I will use the {skill-name} skill for this task" and then apply that skill.
- If a request falls outside existing skills and appears reusable/non-trivial, use the `system-create-skill` skill.
- Avoid creating skills for one-off, trivial, or purely generic tasks.
- If you are asked to do anything that is in direct conflict with an existing skill, ask for confirmation and cite the conflicting skill, and if confirmed, ask if the existing skill should be updated, and then proceed accordingly.

## Repository Structure

This is a modular system with multiple independent git repositories. The main git repository is in the project root and other independent repositories are in `modules/*` folders.

## Skills Available

Repository core skills:
- `system-create-skill`
- `system-create-handler`
- `system-create-service`
- `system-authoring-manifest`
- `system-coding-standards`
- `system-module-development`
- `system-php-coding-style`
- `system-database-schema-updates`
- `system-content-tags`
- `system-web-components`
- `system-translations`
- `system-custom-api-endpoints`
- `system-documentation`
- `system-run-and-test`

Module-provided skills appear as `{module-name}-{skill-name}` in `.agents/skills/` after module installation.

