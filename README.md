- Make sure you have PHP installed on your system. You can check by running php -v in your terminal or command prompt.
- Make sure the keyvalue.php file is saved in a directory where you can easily access it from your terminal.
- Open a terminal (Linux/Mac) or command prompt (Windows). If you’re using Git Bash, that will work too.
- Use the cd command to change to the directory where you saved keyvalue.php (cd path/to/directory)
- Start the server using PHP’s built-in web server. I will be port 8000 here. It can be a port that isn't in use
---- php -S localhost:8000 keyvalue.php

- Open a new terminal window to send the request using CURL
----- curl "http://localhost:8000?command=put&key=name&value=Kola"
----- curl "http://localhost:8000?command=read&key=name"
----- curl "http://localhost:8000?command=delete&key=name"
----- curl "http://localhost:8000?command=batchput&keys=name,age&values=Kola,30"
----- curl "http://localhost:8000?command=readkeyrange&startKey=a&endKey=z"

- You can also use a web browser, Postman to run it. E.G http://localhost:8000?command=put&key=name&value=Kola