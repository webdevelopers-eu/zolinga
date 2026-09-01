---
name: system-coding-standards
description: Use when creating or modifying code in Zolinga modules, especially for following established coding standards and best practices.
argument-hint: "<module-name> [code-change-description]"
---

# Zolinga Coding Standards

## Use When

- Writing new code in a Zolinga module.
- Modifying existing code in a Zolinga module.
- Refactoring code for readability, maintainability, or performance.

## Workflow

1. Follow established coding conventions for PHP, SQL, JavaScript, and other languages used in Zolinga.
2. Use consistent naming conventions for variables, functions, classes, and files.
3. Keep functions and methods focused and concise.
4. Add comments and documentation where necessary to explain complex logic or decisions.
5. Ensure code changes are well-tested and do not introduce regressions.
6. Use version control best practices, including clear commit messages and logical commit structure.
7. Keep methods at 30 lines of code or less (excluding comments and whitespace).
8. Split long methods into smaller methods.
9. Keep classes focused on one responsibility.
10. Prefer simple algorithms over complex ones.
11. **Temporary scripts** (ad-hoc test/debug scripts) go in `./tmp/ai-*.*` with the `ai-` prefix. For other folders: `./data/system/tmp/`, `./public/data/system/tmp/`, `./public/dist/system/tmp/`, or `./public/tmp/` (for public URL access). Create `tmp/` dirs if needed. Clean up after use.
12. **Be decisive. Do not over-analyze.** If a task involves two 100-line files, do not spend excessive time debating approaches. Pick the simplest solution that works, implement it, and move on. Thinking is not progress — code is. If you catch yourself re-reading the same files or re-deriving the same conclusion, stop and act.
13. **Complete the task.** Never leave a task half-finished. If you started a refactoring, finish it. If you started a new class, write it fully. Do not loop indefinitely on analysis — set a mental deadline, decide, and execute.

## Refactoring and Abstraction

When asked to extract shared code into a parent class, trait, or shared module:

1. **Normalize differences before giving up.** Two pieces of code that are 90% identical but differ in small ways (different constant, different method name, different format string) are NOT "too different to merge." The differences are the abstraction boundary. Parameterize them.
2. **Techniques for normalizing differences:**
   - Replace a hardcoded value with a constructor argument or property.
   - Replace a method call with a template method (parent calls `$this->doX()`, child overrides `doX()`).
   - Replace a format string with a configurable format property.
   - Replace a conditional with a strategy/policy object passed in.
   - Replace a type-specific branch with a polymorphic method on the type itself.
3. **Do not refuse refactoring because code is not 100% identical.** That is the entire point of refactoring. If the code were identical, it would be a copy-paste, not a refactoring challenge.
4. **After refactoring, verify the result.** Ensure the parent class holds all shared logic and each child only contains what is genuinely different. If a child class is empty or near-empty, the abstraction was correct. If a child class is still large, the abstraction boundary is wrong — reconsider.

## Architecture-First Thinking

1. **Design the structure before writing code.** Before writing any function, know: what classes exist, what calls what, where data flows. If you cannot draw the structure in 5 boxes, you are not ready to code.
2. **Global coherence over local perfection.** A system made of perfectly written functions that don't fit together is worse than a system of average functions with a clear architecture. Optimize for the whole, not the part.
3. **No "slop architecture."** Do not produce a pile of individually-correct code snippets that lack a coherent design. Every class must have a clear role. Every module boundary must be intentional. If you cannot explain in one sentence why a class exists, it should not exist.
4. **Prefer fewer, well-structured classes over many tiny ones.** Do not create a class per function or a file per method. Group related functionality. A module with 3 well-designed classes is better than one with 30 micro-classes that nobody can navigate.
5. **When extending an existing system, fit the existing architecture.** Do not invent a parallel structure. Find the established pattern and follow it. Consistency with the codebase beats theoretical purity.

## PHP 8.4 Modern Patterns

### Property Hooks and Asymmetric Visibility

- **Prefer property hooks over getter/setter methods.** Use `public private(set)` for read-only public properties instead of `private` + `getFoo()`.
- **No getter clutter.** Never write `public function getFoo(): array { return $this->foo; }` — just make the property `public` or `public private(set)` and access it directly.
- **Computed properties use `get` hooks.** Instead of `getContext(): ?string`, use `public ?string $context { get { ... } }`.
- **Inside a hook, `$this->prop` is the backing value.** Reading or writing `$this->prop` from that property's `get`/`set` hook does not re-enter the hook. Store the cached value there. Do not add a parallel `$this->storedProp` field.

```php
public string $markdown {
    get {
        if (!isset($this->markdown)) {
            $this->markdown = self::htmlToMarkdown($this->html);
        }
        return $this->markdown;
    }
    set(string $value) {
        $this->html = self::markdownToHtml($value);
        $this->markdown = $value;
    }
}
```

### Type Safety

- **Enforce argument types for polymorphic methods.** If a method accepts `string|array`, validate which one is correct for the current object state and throw `\InvalidArgumentException` on mismatch rather than silently coercing.

## Anti-Patterns to Avoid

### Infinite Analysis Loop
Do not re-read files, re-derive conclusions, or re-debate approaches you already settled. If you have read the code and understand it, act. Re-reading the same 100-line file 5 times is not thoroughness — it is paralysis. One read, one decision, one implementation.

### Perfect Code, Broken Architecture
Writing flawless individual methods while ignoring how they compose into a system is the worst failure mode. A system where every function is beautiful but the overall structure is incoherent is "slop made of perfect parts." Always evaluate the architecture first, then write code that fits it.

### Refusal to Abstract Near-Duplicate Code
When two code blocks are similar but not identical, the correct response is to parameterize the differences — not to declare them "too different to merge." This is the core skill of abstraction. If you cannot see how to normalize a difference, try these in order:
1. Extract the differing value as a parameter.
2. Extract the differing behavior as a method override (template method pattern).
3. Extract the differing logic as a strategy/policy injected at construction.
4. Only if none of these work are the blocks genuinely too different to merge.