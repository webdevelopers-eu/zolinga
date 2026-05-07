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
10. Prefer simple algorithms over complex ones.11. **Temporary scripts** (ad-hoc test/debug scripts) go in `./tmp/ai-*.*` with the `ai-` prefix. For other folders: `./data/system/tmp/`, `./public/data/system/tmp/`, `./public/dist/system/tmp/`, or `./public/tmp/` (for public URL access). Create `tmp/` dirs if needed. Clean up after use.