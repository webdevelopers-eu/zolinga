---
name: system-web-components
description: Use when creating or modifying Zolinga web components in install/dist, registering them through module zolinga.json, and documenting them correctly.
argument-hint: "<module-name> <component-name>"
---

# Zolinga Web Components

## Use When

- Building new web components in a module.
- Updating existing web component behavior or docs.

## Workflow

1. Create component files in `modules/<module-name>/install/dist/web-components/`.
2. Register the component in module `zolinga.json` (do not rely on manual `customElements.define()`).
3. Keep component documentation in a sibling `.md` file to the component `.js` file.
4. Use the shared base web component implementation from `system/install/dist/js/web-component.js`.
5. Do not use `this.setContextVariable()`.

## Documentation Abstract

- Start with `Web Components.md` for conventions and lifecycle.
- Use `WebComponent Class.md` for base class capabilities and limits.
- Use template docs for cross-cutting event and integration references.

## References

- `system/wiki/Zolinga Core/Web Components.md`
- `system/wiki/Zolinga Core/Web Components/WebComponent Class.md`
- `system/wiki/templates/Web Components Related.md`
- `system/wiki/Zolinga Core/Manifest File.md`
