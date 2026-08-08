---
name: system-action-plan
description: Use when creating actionable, trackable review/implementation plan documents in the /TODO folder. Covers the TOC-with-progress-counters format, atomic checkbox steps, mandatory validation step per block, and concise author-to-author tone.
argument-hint: "<plan-name> <topic>"
---

# System Action Plan

## Use When

- Creating a review/audit/implementation plan document in `/TODO/`.
- Converting a set of findings (security, performance, maintainability, etc.) into a trackable, actionable plan.
- Any request to "create a TODO document with checkboxes".

## Document Format

Place files in `/TODO/<plan-name>.md`. Structure:

```markdown
# <Plan Title>

# TOC
- [ ] <Heading 1> (0/N)
- [ ] <Heading 2> (0/M)
...

# <Heading 1>
- [ ] <atomic action step>
- [ ] <atomic action step>
- [ ] Validate: <how to verify this block is done>

# <Heading 2>
- [ ] <atomic action step>
- [ ] Validate: <how to verify this block is done>
```

## Rules

1. **TOC at top**: one line per heading, checkbox + heading + `(checked/total)` counter.
2. **Atomic steps**: each checkbox is one concise, actionable sentence. No paragraphs.
3. **Validation step**: every heading block ends with a `Validate:` checkbox describing how to confirm the block is complete/correct.
4. **Concise tone**: write as if to an author who knows the system. No lengthy explanations.
5. **Update counters**: when checking a box, update both the section's count and the TOC counter.
6. **Check the TOC box** when all steps under a heading are checked.
7. **File paths**: reference files by relative path from workspace root, e.g. `modules/foo/src/Bar.php`.
8. **One concern per heading**: group related steps; split unrelated steps into separate headings.
9. **CONFIRMED FINDINGS ONLY**: every finding in the plan MUST be verified against the actual codebase before writing it. Do NOT include "verify" or "audit" steps as action items — verification happens during plan creation, not during execution. The plan contains only confirmed, actionable fixes.
10. **No "verify/audit/check if" action steps**: if a finding needs verification, verify it NOW (read the file, grep the code), then either include it as a confirmed fix or drop it. The plan executor should not have to re-verify your findings.

## Workflow

1. Gather findings (review, audit, exploration).
2. **VERIFY each finding**: read the actual file, grep the code, confirm the issue exists at the stated line with the stated code. Drop any finding that cannot be confirmed.
3. Group confirmed findings into headings by concern (not by file).
4. Write atomic action steps under each heading — each step is a concrete fix, not an investigation.
5. Add a `Validate:` step at the end of each heading block.
6. Build the TOC with `(0/N)` counters.
7. Save to `/TODO/<plan-name>.md`.

## References

- `/TODO/` — existing plan documents
- `system-create-skill` — skill authoring conventions