---
name: system-create-service
description: Use when creating or wiring a Zolinga service exposed on global $api via system:service events, including manifest sugar, naming, initialization, and special service/listener combinations.
argument-hint: "<module-name> <service-name> [goal]"
---

# Zolinga Create Service

## Use When

- Adding a new `$api->...` service.
- Registering service listeners in `zolinga.json`.
- Designing service class shape, naming, and initialization behavior.
- Combining service role with normal event-handling methods.

## Service Contract

- Service class must implement `\Zolinga\System\Events\ServiceInterface`.
- `ServiceInterface` extends `ListenerInterface`, so services can also be normal listeners.
- Service objects are lazily instantiated and cached in `Api`.
- Service lookup resolves listener for `system:service:<name>` and uses highest-priority match.

## Services vs Events

- Services are a comfortable and fast way to call APIs directly across module boundaries (`$api->serviceName->method(...)`).
- Services create direct integration points between modules (and within the same module).
- Events are different: dispatching an event does not require any listener to exist.
- Because listeners may be absent, events provide weaker coupling and optional integration paths.
- If a service provider module may be absent, guard usage with `$api->serviceExists(string $name)`.

## Manifest Registration

Preferred syntax:

```json
{
  "service": "myService",
  "description": "My reusable service.",
  "class": "\\My\\Module\\MyService",
  "origin": ["internal"]
}
```

Equivalent explicit form:

```json
{
  "event": "system:service:myService",
  "description": "My reusable service.",
  "class": "\\My\\Module\\MyService",
  "origin": ["internal"]
}
```

Notes:

- `service` sugar auto-normalizes to `event: system:service:<name>` and `origin: ["internal"]`.
- `method` is optional for services.
- If omitted, service is instantiated and registered only.
- If provided, method is invoked once during initial service load with base `Event`.

## Naming and Placement (Observed)

- Service key (`service`) is usually lower camel case.
- Examples: `log`, `config`, `manifest`, `cms`, `db`, `registry`, `rms`, `user`.
- Class names usually end in `Service`.
- Typical placement: `src/<Domain>/<Thing>Service.php` or `src/<Thing>Service.php`.
- Ensure autoload prefix exists in module `zolinga.json` `autoload` section.

## Origins and Event Objects Relevant to Services

- Service bootstrap event type is always `system:service:<name>`.
- Event object used by loader is `\Zolinga\System\Events\Event` with origin `internal`.
- If service class also listens to other events, use appropriately typed methods (`ContentEvent`, `RequestResponseEvent`, etc.) for those specific listeners.

## Priority Guidance for Services

- Default priority is `0.5` and is sufficient in most cases.
- Set priority only when competing providers for same service name must be ordered.
- Highest-priority subscription wins service resolution.

## Special Cases and Customs

- A service class can handle additional events by adding more `listen` entries pointing to the same class with explicit `method`.
- Example pattern in repository: a service-like class also handles `system:content` and `cms:content:*` events.
- Service properties are available via `$api->serviceName` after first access.
- Service discovery contributes to generated `data/system/api.stub.php` for IDE hints.

## Authorization and Rights with Services

- `right` protection is a listener invocation feature for event dispatch.
- Standard service loading path (`$api->serviceName`) does not perform right-guard dispatch for `system:service:*`; it resolves and instantiates directly.
- If a service also has regular event listeners, those listeners may use `right` as usual.
- Rights are enforced via internal `system:authorize` providers (for example `zolinga-rms` user service provider).

## Service Creation Checklist

- Add `listen` entry using `service` sugar.
- Implement class with `ServiceInterface`.
- Add autoload mapping for namespace.
- Use `global $api;` to consume other services inside methods.
- If your module consumes services from another module, add that module to `dependencies` in `zolinga.json`.
- If your module expects handlers/listeners from another module to be present, declare that module in `dependencies` as well.
- Add explicit priority only if ordering override is needed.
- If new events are emitted by service logic, document in `emit`.
- Add/update module wiki docs as required.

## References

- `system/src/Api.php`
- `system/src/Config/Atom/ListenAtom.php`
- `system/src/Config/ManifestService.php`
- `system/src/Events/ServiceInterface.php`
- `system/src/Events/ListenerInterface.php`
- `system/wiki/Zolinga Core/Manifest File.md`
- `system/wiki/Zolinga Core/Events and Listeners.md`
- `modules/zolinga-rms/zolinga.json`
- `modules/zolinga-rms/src/Service.php`
- `modules/ipdefender/src/Content/VyhledavaniListener.php`
