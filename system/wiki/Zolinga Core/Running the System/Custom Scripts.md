# Custom Scripts

You don't need to run Zolinga on its own to use its power. You can include it in your existing scripts and use its event system.

Assume that you need to create a custom script `https://example.org/dist/myModule/export.php` that exports data from your system. You can use the Zolinga PHP framework to do that.

Example:

Contents of `./modules/myModule/install/dist/export.php` (see [Module Paths](:Zolinga Core:Paths and Zolinga URI) for explanation why this path):

```php
// This will load Zolinga PHP framework's autoloading mechanism and will create global `$api` object.
require($_SERVER['DOCUMENT_ROOT']. "/../system/loader.php");

$event = new \Zolinga\System\Events\RequestEvent("myModule:export", \Zolinga\System\Events\RequestEvent::ORIGIN_REMOTE, ["file" => "export.csv"]);
$event->dispatch();
```

It is really that simple. When dispatching events always use the *remote* origin of the event unless you have a serious reason not to.


# Related
{{Running the System}}
- [Module Paths](:Zolinga Core:Paths and Zolinga URI) 