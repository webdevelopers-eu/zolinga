# Agents Guidance

This repository uses the Agent Skills system in `.agents/skills/` as the primary source of agent guidance.

## Primary Rules

- Prefer loading and applying relevant skills from `.agents/skills/`.
- If you are asked to do anything that is already covered by an existing skill, say "I will use the {skill-name} skill for this task" and then apply that skill.
- If a request falls outside existing skills and appears reusable, non-trivial, or dependent on specialized knowledge or multi-step processes:
  - Pause execution and ask whether a new skill should be created.
  - If confirmed, define a new skill in `modules/<module>/skills/<module>-<skill-name>/SKILL.md`.
  - Use a clear, descriptive name that reflects intent and scope. Always prefix it with the module name to avoid naming conflicts and clarify context.
  - Document the skill with a concise description, expected inputs, outputs, and any constraints.
  - Implement the skill following existing conventions.
  - Re-run the original task using the newly created skill.
- Avoid creating skills for one-off, trivial, or purely generic tasks.
- If you are asked to do anything that is in direct conflict with an existing skill, ask for confirmation and cite the conflicting skill, and if confirmed, ask if the existing skill should be updated, and then proceed accordingly.

## Repository Structure

This is a modular system with multiple independent git repositories. The main git repository is in the project root and other independent repositories are in `modules/*` folders.

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

