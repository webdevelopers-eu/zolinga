Priority: 0.9

# PHP Installation Scripts

The Zolinga Core supports PHP installation scripts by defaults. The installation script will be required from inside the function.

Something like this:

```php
function executeScript(string $script): void {
    global $api;
    require $script;
}
```

So you can use the `$api` object to access all services and other global objects and variables that you create won't polute the global namespace.

# Related

- [Module Installation and Updates](:Zolinga Core:Module Installation and Updates)
- [All Supported Script Events](:ref:event:system:install:script:*)