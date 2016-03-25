# Change Log

## 1.3.0 - 2016-03-25

- Full support of native streaming filters.
- Fixed bug with queues in async request.
- Support of an native option "CURLOPT_FOLLOWLOCATION".
- Support send "PSR-7 Response".
- More correct support of header "Expect".

## 1.2.0 - 2016-03-16

- Fix: transfer of very large body.
- More user-friendly design.
- Support custom method.
- You can use options "CURLOPT_READFUNCTION" and "CURLOPT_WRITEFUNCTION".
- Inheritance support.

## 1.1.0 - 2016-01-29

### Changed

- Switch to php-http/message 1.0.


## 1.0.0 - 2016-01-28

First stable release.


## 0.7.0 - 2016-01-26

### Changed

- Migrate from `php-http/discovery` and `php-http/utils` to `php-http/message`.

## 0.6.0 - 2016-01-12

### Changed

- Root namespace changed from `Http\Curl` to `Http\Client\Curl`.
- Main client class name renamed from `CurlHttpClient` to `Client`. 
- Minimum required [php-http/discovery](https://packagist.org/packages/php-http/discovery)
  version changed to 0.5.


## 0.5.0 - 2015-12-18

### Changed

- Compatibility with php-http/httplug 1.0 beta
- Switch to php-http/discovery 0.4


## 0.4.0 - 2015-12-16

### Changed

- Switch to php-http/message-factory 1.0


## 0.3.1 - 2015-12-14

### Changed

- Requirements fixed.


## 0.3.0 - 2015-11-24

### Changed

- Use cURL constants as options keys.


## 0.2.0 - 2015-11-17

### Added

- HttpAsyncClient support.


## 0.1.0 - 2015-11-11

### Added

- Initial release
