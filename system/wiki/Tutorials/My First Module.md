# My First Module Tutorial

## Skeleton Module

To create a module from a boiler plate run this command:

```bash
bin/zolinga skeleton:module --name="{module-name}"
```

The `{module-name}` is a name of a directory in `modules/` where the module will be created. The name should uniquely identify the module and should not contain spaces or special characters. It will became a sort of an identifier for the module that will be used for various path and URL generation.

We will create a module named `example-acme`:

```bash
bin/zolinga skeleton:module --name="example-acme"
```

This will create a directory `modules/example-acme` with [the standard structure of a module](:Zolinga Core:Module Anatomy) and few basic demo files.

Now, if you visit the URL that you have set up for the Zolinga and you add `?acme[name]=John` to the URL you should see the response `Hello John` in the browser. This is because the module `example-acme` is already plugged into the system and it is responding to the event [system:request:acme](:ref:event:system:request:*) as declared in the `zolinga.json` file. This manifest file also declares that the skeleton module responds to `example:acme` event.


```bash
bin/zolinga example:acme --name=John
```

Voila! You have just created your first module! Congratulations!

## What Just Happened?

1. System has created a directory `modules/example-acme` with [a standard structure](:Zolinga Core:) of a module.
2. System detected new `modules/example-acme/zolinga.json` file and extracted all necessary information from it. 
3. Now, system knows from `zolinga.json` that
    - the module [serves the event](:ref:event) `example:acme` triggered from front-end (*remote* origin), command-line (*cli* origin), and if other PHP code dispatches it (*internal* origin).
    - if there is [a custom HTML tag](:ref:wc) `<example-acme>` on the frontend, the module's javascript should be loaded.
    - Any PHP namespace `\Example\Acme\...` maps to `modules/example-acme/src/...` directory.
4. There is a WIKI file that should be displayed in Zolinga's WIKI as the article `My New ACME Module`.

## What's Next?

- [Learn about the anatomy of a module](:Zolinga Core:Module Anatomy)
- [Learn how to create a custom HTML tag](:Zolinga Core:Web Components)
- [Learn how to create a custom event](:Zolinga Core:Events and Listeners)
- [Learn how to create a custom command-line command and more](:Zolinga Core:Running the System)
- ...or simply browse randomly the [Zolinga Core](:Zolinga Core:) documentation to learn more.
