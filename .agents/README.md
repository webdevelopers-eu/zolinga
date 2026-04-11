# Agent Skills for This Repository

This folder supersedes project guidance previously centralized in `.github/copilot-instructions.md`.

## Structure

- `skills/` contains task-specific skills in Agent Skills format.
- Each skill is self-contained in `skills/<skill-name>/SKILL.md`.

## Usage

- Auto-load: skills are discovered by `name` and `description` relevance.
- Manual: invoke as slash command in chat (for example `/zolinga-run-and-test`).

## Notes

- Keep each skill narrow and action-oriented.
- Keep shared coding conventions in dedicated skills instead of one large instruction file.
- If you add files beside `SKILL.md`, reference them from the skill body using relative links.
