---
name: system-security-practices
description: Use when implementing sensitive actions or reviewing Zolinga code for authorization gaps, unsafe request handling, and common security mistakes.
argument-hint: "<implement|review> [area]"
---

# Zolinga Security Practices

## Use When

- Implementing any sensitive action exposed through remote requests.
- Reviewing handlers for missing authorization or unsafe request processing.
- Editing `zolinga.json` listeners that use `"request"` or `"event": "system:request:*"`.

## Core Rule

- Treat every remote request handler as untrusted input.
- If a `zolinga.json` listener processes a sensitive action through `"request": "..."` or `"event": "system:request:..."`, add a `"right": "..."` key or enforce authorization explicitly in the handler code before doing the sensitive action.
- Treat manifest `"right"` as coarse-grained access to the request type or feature, not as complete authorization for every concrete resource the request may touch.
- Do not rely on hidden UI controls, HTTP method choice, or obscurity as authorization.

## Short Authorization Flow

1. `"request": "name"` is manifest sugar for `"event": "system:request:name"` with remote origin.
2. When a listener has `"right": "..."`, core dispatches internal `system:authorize` before the listener runs.
3. An authorization provider confirms the right and calls `$event->authorize(...)`.
4. With `zolinga-rms` installed, `\Zolinga\Rms\UserService::onAuthorize()` is the usual provider.
5. If the right is not granted, the protected listener is skipped and the original request ends unauthorized.

## How To Check And Add Rights

1. Inspect the module manifest for remote handlers using `"request"` or `"event": "system:request:..."`.
2. Decide whether the action changes state, exposes private data, performs admin work, impersonates users, touches billing, or triggers external side effects.
3. If yes, add a manifest right such as `"right": "member of users"` or `"right": "member of administrators"` when that matches the module policy.
4. Confirm an authorization provider exists. In projects with `modules/zolinga-rms/`, the RMS provider is already wired on `system:authorize`.
5. If no suitable provider exists, enforce the check inside the handler and return `UNAUTHORIZED` or `FORBIDDEN` before continuing.

## Resource-Level Authorization

- Use manifest `"right"` to block broad access first. This is the high-level gate for whether the caller may use the endpoint or feature at all.
- If the handler operates on a particular resource or state, add a second check in code for that exact target.
- Typical cases are editing a specific CMS page, changing another user's data, manipulating a particular invoice, or accessing a tenant-specific record.
- Example: a CMS edit endpoint may require a general manifest right such as `"member of editors"`, then the handler should still verify that the current user may edit that specific page.
- In RMS-based projects this often means checking a resource-scoped right via `$api->user->hasRight(...)`. One existing rights pattern in repository docs is `access page#134`, so project-specific checks may look like `$api->user->hasRight('access cms:page#' . $pageId)` or a similar convention defined by the module.
- Do not assume the exact resource-right string format unless the module or docs define it. Reuse existing naming patterns in that project.

Example manifest pattern:

```json
{
  "description": "Sensitive request handler.",
  "request": "admin-action",
  "class": "\\Vendor\\Module\\Api\\AdminActionApi",
  "method": "onAdminAction",
  "right": "member of administrators"
}
```

Example code-level fallback:

```php
global $api;

if (!isset($api->user) || !$api->user->isAdministrator()) {
    $event->setStatus($event::STATUS_FORBIDDEN, 'You do not have permission to perform this action.');
    return;
}
```

Example layered authorization:

```php
global $api;

if (!isset($api->user) || !$api->user->hasRight('member of editors')) {
  $event->setStatus($event::STATUS_FORBIDDEN, 'You do not have permission to edit pages.');
  return;
}

if (!$api->user->hasRight('access page#' . $pageId)) {
  $event->setStatus($event::STATUS_FORBIDDEN, 'You do not have permission to edit this page.');
  return;
}
```

## Review Checklist

- Does every sensitive remote request have authorization in manifest or code?
- If a handler touches a specific resource, does it perform a resource-level permission check in code and not only a general manifest `right` check?
- Is authorization checked before any state change, external API call, file write, or data disclosure?
- Are request parameters validated instead of trusted as-is?
- Are dangerous actions limited to POST-like flows in practice, even if routing allows both GET and POST?
- Are error messages free of secrets, tokens, stack traces, or internal paths?
- Are admin-only handlers protected against ordinary authenticated users?
- Is `system:authorize` itself left without a `right` property to avoid loops?

## References

- `system/wiki/Zolinga Core/Events and Listeners.md`
- `system/wiki/Zolinga Core/Events and Listeners/Event Authorization.md`
- `system/wiki/ref/event/system/request/wildcard.md`
- `system/src/Api.php`
- `modules/zolinga-rms/zolinga.json`
- `modules/zolinga-rms/src/UserService.php`