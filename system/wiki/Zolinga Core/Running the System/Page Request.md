# Page Request

When you type a URL in your browser, you are making a GET request to the server.

For missing files, the system first checks the virtual root overlay at `./public/data/system/root`. If a matching file exists there, it is served as if it was in the web root. Example: `./public/data/system/root/favicon.ico` is available as `/favicon.ico`.

Requests that are not mapped to an existing static file are then routed to `public/index.php`, which does two things:

- Processes [GET and POST requests](:Zolinga Core:Running the System:Page Request:Processing POST and GET)
- Serves the [page content](:Zolinga Core:Running the System:Page Request:Processing Page Content).

Both actions are event driven. How else, right?

# Related
{{Running the System}}