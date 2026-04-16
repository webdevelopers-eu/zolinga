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

## Use When

- A request needs a reusable workflow not covered by existing skills.
- A skill needs rename/split/merge/migration.
- `AGENTS.md` and skill catalog have drifted.

## Canonical Skill Locations

- Core system skills: `system/skills/<skill-name>/SKILL.md`
- Module-specific skills: `modules/<module>/skills/<module>-<skill-name>/SKILL.md`
- `.agents/skills/` is derived/symlinked during install/update; do not treat it as canonical source.

## Naming Rules

- Use clear intent-driven names.
- Core skills use `system-...` prefix.
- Module skills must be prefixed with module name: `<module>-<skill-name>`.
- Keep names stable once referenced by docs or other instructions.

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
3. Create the skill folder and `SKILL.md`.
4. Write concise, actionable instructions and constraints.
5. If this replaces an older skill, merge useful content and remove deprecated duplicate skill paths.
6. Update `.agents/AGENTS.md` only with high-level policy and current skill inventory pointers, not full duplicated procedures.

## AGENTS.md Alignment Rules

- Keep `.agents/AGENTS.md` as policy/index, not a long how-to.
- If guidance is detailed and procedural, move it into a dedicated skill and reference that skill by name.
- Keep the “Skills Available” list aligned with current canonical system skills.

## Quality Checklist

- Skill path is canonical (not `.agents/skills` source edits).
- Naming follows scope/prefix rules.
- No contradictory duplicate skill remains.
- `AGENTS.md` does not duplicate full skill internals.
- References point to real files.

## References

- `.agents/AGENTS.md`
- `system/skills/system-module-development/SKILL.md`
- `system/skills/system-authoring-manifest/SKILL.md`
