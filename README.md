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

## Credentials for AWS S3

Add the following credentials into the model of `Marketplace`,  `User` and `Post`, it's inside the function `_construct`:

```php
'key'    => 'putKeyHere',
'secret' => 'putSecretHere'
```

**Note:** The key and secret can be found in the GC in Messenger.
