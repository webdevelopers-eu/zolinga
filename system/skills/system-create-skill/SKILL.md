---
name: system-create-skill
description: Use when creating, updating, or consolidating Agent Skills in this repository, including canonical placement, naming, and AGENTS.md alignment.
argument-hint: "<scope:system|module> <skill-name> [goal]"
---

# Zolinga Create Skill

## Summary

- Prefer reusing existing skills.
- Create new skills only for reusable, non-trivial workflows.
- Keep core/general skills in `system/skills/`.
- Keep module-specific skills in `modules/<module>/skills/<module>-<skill-name>/SKILL.md`.
- Keep `.agents/AGENTS.md` concise; avoid duplicating full procedures documented in skills.
 
 - Placement: prefer `system/skills/` for cross-cutting flows and `modules/<module>/skills/` for module-scoped flows.
 - When to create: create new skills only for reusable workflows that require more than 3 steps or involve multiple modules.

## Use When

- A request needs a reusable workflow not covered by existing skills.
- A skill needs rename/split/merge/migration.
- `AGENTS.md` and skill catalog have drifted.

## Canonical Skill Locations

- Core system skills: `system/skills/<skill-name>/SKILL.md`
- Module-specific skills: `modules/<module>/skills/<module>-<skill-name>/SKILL.md`
- **NEVER edit, create, or modify anything inside `.agents/skills/`**. That directory is auto-populated with symlinks by the framework. Any real files or directories placed there (other than the single permitted `zolinga-setup/` directory) are wrong and must be moved to the canonical module location. Edit skills only in `system/skills/` or `modules/<module>/skills/`.
- If a new/changed skill is not visible yet, bump module version in `zolinga.json` and run `bin/zolinga` (no parameters). The system autodetects the change, rescans manifests, and regenerates symlinks.
- **Never add module-specific skills to the global `.agents/AGENTS.md`**. Modules are optional and vary between installations. Module skills are auto-discovered at runtime; listing them in AGENTS.md creates a false dependency.

## Naming Rules

- Use clear intent-driven names.
- Core skills use `system-...` prefix.
- Module skills must be prefixed with module name: `<module>-<skill-name>`.
- Keep names stable once referenced by docs or other instructions.

 - Verify the `SKILL.md` file conforms to this `modules/<module-name>/skills/<module-name>-<skill-name>/SKILL.md` before publishing and for `system` itself `system/skills/system-<skill-name>/SKILL.md`.

## Minimal Skill File Contract

Frontmatter:

- `name`
- `description`
- `argument-hint`

Body sections (recommended):

- `Use When`
- `Workflow` or equivalent actionable steps
- `References`

## Workflow

1. Confirm there is no existing skill that already covers the workflow.
2. Choose scope:
- cross-module/general -> `system/skills/...`
- module-specific -> `modules/<module>/skills/...`
- If the module name is invalid or unsupported (no matching directory under `modules/`), fix the module name or create/rename it to be the required directory.
3. Create the skill folder and `SKILL.md`.
4. Write concise, actionable instructions and constraints.
5. If this replaces an older skill, merge useful content and remove deprecated duplicate skill paths.
6. Update `.agents/AGENTS.md` only with high-level policy and current skill inventory pointers, not full duplicated procedures.

## AGENTS.md Alignment Rules

- Keep `.agents/AGENTS.md` as policy/index, not a long how-to.
- If guidance is detailed and procedural, move it into a dedicated skill and reference that skill by name.
- Keep the “Skills Available” list aligned with current canonical system skills.

 - Concision guideline: AGENTS.md entries should be short — limit to a maximum of 3 sentences or 50 words per skill entry; link to the canonical `SKILL.md` for details.

## Quality Checklist

- **Skill path is canonical — never inside `.agents/skills/`.**
- Naming follows scope/prefix rules.
- No contradictory duplicate skill remains.
- `AGENTS.md` does not duplicate full skill internals.
- References point to real files.

## References

- `.agents/AGENTS.md`
- `system/skills/system-module-development/SKILL.md`
- `system/skills/system-authoring-manifest/SKILL.md`
