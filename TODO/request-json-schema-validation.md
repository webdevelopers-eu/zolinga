# Validate Input

Condition: when [PHP support](https://wiki.php.net/rfc/json_schema_validation) JSON SCHEMA validation. Maybe PHP 8.6?

Description: Be able to statically declare event parameters and validate them against a JSON SCHEMA. 

Current Implementation: zolinga.json file can declare a `schema` property for each event for MCP.

# Plan

The `RequestEvent` class will be extended with `validate()` method that will find all subscribers and will join all their schemas if they exist into a single schema and validate the event parameters against it. If the joining of schemas fails because of conflicting definitions, the validation will fail and the event will be rejected.

The `RequestEvent` class will also have a `getSchema()` method that will return the joined schema for the event. This can be used by the MCP to provide a `schema` endpoint for each event instead of current cherry picking of schemas on per-subscriber basis. (In fact this must be fixed in MCP because the current implementation does not support multiple listeners to one MCP event with different schemas.)

The CLI will also be extended to support general `--print-schema` option to print the schema for any event and general `--help` option to print help generated from the schema for any event. 

