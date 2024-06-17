# Dynamic Configuration

You can fire [config](:ref:event:config) event to change configuration values from CLI or you can call `$api->config->merge($cfg)` to merge a configuration array into the current configuration for the current request.

```bash
bin/zolinga config '{"db": {"host": "localhost", "port": 3377}} my-other-event'
bin/zolinga config --db.host=localhost --db.port=3377  my-other-event
```

Because CLI events are processed in sequence, you can use the `config` event to change the configuration values before the next event is processed.

```bash
bin/zolinga config --tor.port=123 my-other-event config --tor.port=456 my-other-event
```
