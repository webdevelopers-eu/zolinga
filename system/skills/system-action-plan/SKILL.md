---
name: system-action-plan
description: Use when creating actionable, trackable plan documents in the /TODO folder — reviews, audits, new features, refactors, migrations, any multi-step work. Covers the TOC-with-progress-counters format, atomic checkbox steps, mandatory validation step per block, heading hierarchy, and concise author-to-author tone.
argument-hint: "<plan-name> <topic>"
---

# System Action Plan

## Use When

- Creating any trackable plan document in `/TODO/` — review fixes, new features, refactors, migrations, audits.
- Converting a set of findings or feature requirements into a trackable, actionable plan.
- Any request to "create a TODO document with checkboxes".

## Document Format

Place files in `/TODO/<plan-name>.md`. Structure:

```markdown
# <Plan Title>

# TOC
- [ ] <Category 1> (0/N)
  - [ ] <Item 1> (0/M)
  - [ ] <Item 2> (0/K)
- [ ] <Category 2> (0/J)
  - [ ] <Item 3> (0/L)
...

# <Category 1>

## <Item 1>
- [ ] <atomic action step>
- [ ] <atomic action step>
- [ ] Validate: <how to verify this block is done>

## <Item 2>
- [ ] <atomic action step>
- [ ] Validate: <how to verify this block is done>

# <Category 2>

## <Item 3>
- [ ] <atomic action step>
- [ ] Validate: <how to verify this block is done>
```

## Rules

1. **TOC at top**: nested list — `#` category headings at top level, `##` items indented under their category. Each line has checkbox + heading + `(checked/total)` counter.
2. **Heading hierarchy**: `#` for top-level categories (e.g. Security, Maintainability, Performance, or feature areas like "Backend", "Frontend", "Database"). `##` for individual action items under each category.
3. **Atomic steps**: each checkbox is one concise, actionable sentence. No paragraphs.
4. **Validation step**: every `##` item block ends with a `Validate:` checkbox describing how to confirm the block is complete/correct.
5. **Concise tone**: write as if to an author who knows the system. No lengthy explanations.
6. **Update counters**: when checking a box, update the item's count, the category total, and the TOC counters.
7. **Check the TOC box** for an item when all its steps are checked; check the category box when all items under it are checked.
8. **File paths**: reference files by relative path from workspace root, e.g. `modules/foo/src/Bar.php`.
9. **One concern per item**: group related steps under a `##` item; split unrelated steps into separate items.
10. **CONFIRMED CONTENT ONLY**: for review/audit plans, every finding MUST be verified against the actual codebase before writing it. For feature plans, every step must be concrete and actionable. Do NOT include "verify" or "audit" steps as action items — verification happens during plan creation, not during execution.
11. **No "verify/audit/check if" action steps**: if something needs verification, verify it NOW (read the file, grep the code), then either include it as a confirmed step or drop it. The plan executor should not have to re-verify your findings.
12. **Optional prefixes**: category/item IDs may use prefixes (e.g. S1, M2, P3) for easy reference — these are optional and purely for identification.

## Workflow

1. Gather requirements (review findings, feature specs, refactor goals, etc.).
2. **VERIFY each item**: for review plans, read the actual file, grep the code, confirm the issue exists at the stated line. For feature plans, confirm the target files/structures exist. Drop anything that cannot be confirmed.
3. Group items into `#` category headings (by concern, dimension, or feature area — not by file).
4. Write `##` items under each category, each with atomic action steps — each step is a concrete action, not an investigation.
5. Add a `Validate:` step at the end of each `##` item block.
6. Build the nested TOC with `(0/N)` counters.
7. Save to `/TODO/<plan-name>.md`.

## References

- `/TODO/` — existing plan documents
- `system-create-skill` — skill authoring conventions