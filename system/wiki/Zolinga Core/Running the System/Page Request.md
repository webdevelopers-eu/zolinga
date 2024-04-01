# Page Request

When you type the URL in your browser, you are making a GET request to the server. The request for non-existent resources is routed to `public/index.php` script that does two things:

- Processes [GET and POST requests](:Zolinga Core:Running the System:Page Request:Processing POST and GET)
- Serves the [page content](:Zolinga Core:Running the System:Page Request:Processing Page Content).

Both actions are event driven. How else, right?

# Related
{{Running the System}}