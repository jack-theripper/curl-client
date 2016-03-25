# Curl client for PHP HTTP

[![Latest Version](https://img.shields.io/github/release/php-http/curl-client.svg?style=flat-square)](https://github.com/php-http/curl-client/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/travis/php-http/curl-client.svg?style=flat-square)](https://travis-ci.org/php-http/curl-client)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/php-http/curl-client.svg?style=flat-square)](https://scrutinizer-ci.com/g/php-http/curl-client)
[![Quality Score](https://img.shields.io/scrutinizer/g/php-http/curl-client.svg?style=flat-square)](https://scrutinizer-ci.com/g/php-http/curl-client)
[![Total Downloads](https://img.shields.io/packagist/dt/php-http/curl-client.svg?style=flat-square)](https://packagist.org/packages/arhitector/http-curl-client)

The client for sending requests of PSR-7. The cURL client use the cURL PHP extension which must be activated in your `php.ini`.


## Differences from https://github.com/php-http/curl-client

- Support custom method, such as "MOVE", "COPY", "PROPFIND", "MKCOL" etc. (it is actual by operation with WebDav)
- Fixed supports transmission of big bodies (upload and download).
- Full support of native streaming filters, such as 'zlib.deflate' etc.
- Fixed bug with queues in async request.
- Doesn't lead to hangup of the server, in case of the inexact size of a flow.
- More friendly api.
- Inheritance support.
- You can use options "CURLOPT_READFUNCTION" and "CURLOPT_WRITEFUNCTION".
- Support of an native option "CURLOPT_FOLLOWLOCATION".
(if you need support cookie with redirect - use "CURLOPT_COOKIEJAR" and "CURLOPT_COOKIEFILE")
- Support send "PSR-7 Response".
- More correct support of header "Expect"


## Install

Via Composer

```
$ composer require arhitector/http-curl-client:~1.3
```

## Quickstart

For example with Zend/Diactoros:

```php
use Http\Message\MessageFactory\DiactorosMessageFactory;
use Http\Message\StreamFactory\DiactorosStreamFactory;

$client = new Mackey\Http\Client\Curl\Client(new DiactorosMessageFactory(), new DiactorosStreamFactory(), [
  // array, set default curl options, if you need
  CURLOPT_SSL_VERIFYPEER => false
]);
```

Send request

```php
$request = new Zend\Diactoros\Request('<url>', 'GET');

$response = $client->sendRequest($request, [
  // curl options for 1 request, if you need
  CURLOPT_FOLLOWLOCATION => true
]);
```

Send async request

```php
$request = new Zend\Diactoros\Request('<url>', 'GET');

$promise = $client->sendAsyncRequest($request);
$promise->then(
  function (ResponseInterface $response) {
    // The success callback

    return $response;
  },
  function (\Exception $exception) {
    // The failure callback

    throw $exception;
  }
);

// other request
// $promise = $client->sendAsyncRequest($request);

try {
    $response = $promise->wait();
} catch (\Exception $exception) {
    echo $exception->getMessage();
}
```

Send response

```php
// example just send file in output
$stream = new \Zend\Diactoros\Stream('file/to/send.txt');
$response = new \Zend\Diactoros\Response($stream, 200);

$client->sendResponse($response);

// or send for download
$response = $response->withHeader('Content-Description', 'File Transfer')
    ->withHeader('Content-Type', 'application/octet-stream')
    ->withHeader('Content-Disposition', 'attachment; filename=filename.txt')
    ->withHeader('Content-Transfer-Encoding', 'binary');

$client->sendResponse($response);
```

## Documentation

Please see the [official documentation](http://docs.php-http.org/en/latest/clients/curl-client.html).

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.


## Security

If you discover any security related issues, please contact us at
[security@php-http.org](mailto:security@php-http.org).


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
