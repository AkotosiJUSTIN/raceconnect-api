# RaceConnect API

An API for RaceConnect, our project for 2nd year, 2nd semester.

## Running the Server

To run the server, type the following command in the terminal:

```sh
php -S localhost:8000 -t public
```

You can access the API at:

```
http://localhost:8000/{endpoint}
```

## Running the WebSocket Server

To run the WebSocket server, follow these steps:

1. Open a terminal and navigate to the project directory:

    ```sh
    cd C:\xampp\htdocs\raceconnect-api\chat
    ```

2. Run the WebSocket server script:

    ```sh
    php WebsocketServer.php
    ```

You can access the WebSocket server at:

```
ws://localhost:8000
```

Make sure you have the WebSocket server script (`WebsocketServer.php`) in your project directory.

## Credentials for AWS S3

Add the following credentials into the model of `Marketplace`, `User`, and `Post`, inside the function `_construct`:

```php
'key'    => 'putKeyHere',
'secret' => 'putSecretHere'
```

**Note:** The key and secret can be found in the GC in Messenger.
