# Creating a new module

To create a new module, run the following command:

```shell
$ bin/zolinga skeleton:module --name=<module-name>
```

This will create a new module in the `modules/<module-name>/` directory with a basic files and directories structure.

The generated skeleton includes an example Agent Skill at:

- `modules/<module-name>/skills/<module-name>-example-skill/SKILL.md`

During install/update, skill folders that contain `SKILL.md` are auto-linked into `.agents/skills/<skill-name>`.