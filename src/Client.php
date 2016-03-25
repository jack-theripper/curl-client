<?php

namespace Mackey\Http\Client\Curl;

use Http\Client\Exception;
use Http\Client\Exception\RequestException;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Client\HttpClient as HttpClientInterface;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Http\Promise\Promise;
use Mackey\Http\Client\Curl\Response\Parser;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-7 compatible cURL based HTTP client
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @author  Blake Williams <github@shabbyrobe.org>
 * @author  Dmitry Arhitector <dmitry.arhitector@yandex.ru>
 */
class Client extends AbstractClient implements HttpClientInterface, HttpAsyncClientInterface
{
    /**
     * cURL response parser
     *
     * @var Parser
     */
    protected $responseParser;

    /**
     * cURL synchronous requests handle
     *
     * @var resource|null
     */
    protected $handle = null;

    /**
     * Simultaneous requests runner
     *
     * @var MultiRunner|null
     */
    protected $multiRunner = null;

    /**
     * Create new client
     *
     * @param MessageFactory $messageFactory HTTP Message factory
     * @param StreamFactory $streamFactory HTTP Stream factory
     * @param array $options cURL options (see http://php.net/curl_setopt)
     */
    public function __construct(MessageFactory $messageFactory, StreamFactory $streamFactory, array $options = [])
    {
        $this->options = $options;
        $this->handle = curl_init();
        $this->setResponseParser(new Parser($messageFactory, $streamFactory));
    }

    /**
     * Sends a PSR-7 request.
     *
     * @param RequestInterface $request
     * @param array $options custom curl options
     *
     * @return ResponseInterface
     *
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     * @throws RequestException
     */
    public function sendRequest(RequestInterface $request, array $options = [])
    {
        $options = $this->createCurlOptions($request, $options + $this->options);

        curl_reset($this->handle);
        curl_setopt_array($this->handle, $options);

        if (!curl_exec($this->handle)) {
            throw new RequestException(curl_error($this->handle), $request);
        }

        try {
            $response = $this->getResponseParser()->parse(curl_getinfo($this->handle), $options[CURLOPT_FILE]);
        } catch (\Exception $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        }

        return $response;
    }

    /**
     * Generates cURL options
     *
     * @param RequestInterface $request
     * @param array $options custom curl options
     *
     * @return array if unsupported HTTP version requested
     */
    protected function createCurlOptions(RequestInterface $request, array $options)
    {
        $options = array_diff_key($options, array_flip([CURLOPT_INFILE, CURLOPT_INFILESIZE]));
        $options[CURLOPT_HTTP_VERSION] = $this->getCurlHttpVersion($request->getProtocolVersion());
        $options[CURLOPT_HEADERFUNCTION] = [$this->getResponseParser(), 'headerHandler'];
        $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        $options[CURLOPT_URL] = (string)$request->getUri();
        $options[CURLOPT_HEADER] = false;

        if (in_array($request->getMethod(), ['GET', 'HEAD', 'TRACE', 'CONNECT'])) {
            if ($request->getMethod() == 'HEAD') {
                $options[CURLOPT_NOBODY] = true;

                unset($options[CURLOPT_READFUNCTION], $options[CURLOPT_WRITEFUNCTION]);
            }
        } else {
            $options = $this->createCurlBody($request, $options);
        }

        $options[CURLOPT_RETURNTRANSFER] = false;
        $options[CURLOPT_FILE] = $this->getResponseParser()->setTemporaryStream(null)->getTemporaryStream();
        $options[CURLOPT_HTTPHEADER] = $this->createHeaders($request, $options);

        if ($request->getUri()->getUserInfo()) {
            $options[CURLOPT_USERPWD] = $request->getUri()->getUserInfo();
        }

        return $options;
    }

    /**
     * Get response parser
     *
     * @return Parser
     */
    public function getResponseParser()
    {
        return $this->responseParser;
    }

    /**
     * Set response parser
     *
     * @param Parser $responseParser
     *
     * @return $this
     */
    public function setResponseParser(Parser $responseParser)
    {
        $this->responseParser = $responseParser;

        return $this;
    }

    /**
     * Return cURL constant for specified HTTP version
     *
     * @param string $version
     *
     * @throws \UnexpectedValueException if unsupported version requested
     *
     * @return int
     */
    protected function getCurlHttpVersion($version)
    {
        if ($version == '1.1') {
            return CURL_HTTP_VERSION_1_1;
        } else {
            if ($version == '2.0') {
                if (!defined('CURL_HTTP_VERSION_2_0')) {
                    throw new \UnexpectedValueException('libcurl 7.33 needed for HTTP 2.0 support');
                }

                return CURL_HTTP_VERSION_2_0;
            } else {
                return CURL_HTTP_VERSION_1_0;
            }
        }
    }

    /**
     * Create body
     *
     * @param RequestInterface $request
     * @param array $options
     *
     * @return array
     */
    protected function createCurlBody(RequestInterface $request, array $options)
    {
        $body = clone $request->getBody();
        $size = $body->getSize();

        // Avoid full loading large or unknown size body into memory. It doesn't replace "CURLOPT_READFUNCTION".
        if ($size === null || $size > 1048576) {
            if ($body->isSeekable()) {
                $body->rewind();
            }

            $options[CURLOPT_UPLOAD] = true;

            if (isset($options[CURLOPT_READFUNCTION]) && is_callable($options[CURLOPT_READFUNCTION])) {
                $body = $body->detach();

                $options[CURLOPT_READFUNCTION] = function ($curl_handler, $handler, $length) use ($body, $options) {
                    return call_user_func($options[CURLOPT_READFUNCTION], $curl_handler, $body, $length);
                };
            } else {
                $options[CURLOPT_READFUNCTION] = function ($curl, $handler, $length) use ($body) {
                    return $body->read($length);
                };
            }
        } else {
            $options[CURLOPT_POSTFIELDS] = (string)$request->getBody();
        }

        return $options;
    }

    /**
     * Create headers array for CURLOPT_HTTPHEADER
     *
     * @param RequestInterface $request
     * @param array $options cURL options
     *
     * @return string[]
     */
    protected function createHeaders(RequestInterface $request, array $options)
    {
        $headers = [];
        $body = $request->getBody();
        $size = $body->getSize();

        foreach ($request->getHeaders() as $header => $values) {
            foreach ((array)$values as $value) {
                $headers[] = sprintf('%s: %s', $header, $value);
            }
        }

        if (!$request->hasHeader('Transfer-Encoding') && $size === null) {
            $headers[] = 'Transfer-Encoding: chunked';
        }

        if (!$request->hasHeader('Expect') && in_array($request->getMethod(), ['POST', 'PUT'])) {
            if ($request->getProtocolVersion() < 2.0 && !$body->isSeekable() || $size === null || $size > 1048576) {
                $headers[] = 'Expect: 100-Continue';
            } else {
                $headers[] = 'Expect: ';
            }
        }

        return $headers;
    }

    /**
     * Sends a PSR-7 request in an asynchronous way.
     *
     * @param RequestInterface $request
     * @param array $options custom curl options
     *
     * @return Promise
     *
     * @throws Exception
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     *
     * @since 1.0
     */
    public function sendAsyncRequest(RequestInterface $request, array $options = [])
    {
        if (!$this->multiRunner instanceof MultiRunner) {
            $this->multiRunner = new MultiRunner($this->getResponseParser());
        }

        $handle = curl_init();
        $options = $this->createCurlOptions($request, $options);

        curl_setopt_array($handle, $options);

        $core = (new PromiseCore($request, $handle))
            ->setTemporaryStream($options[CURLOPT_FILE]);
        $promise = new CurlPromise($core, $this->multiRunner);
        $this->multiRunner->add($core);

        return $promise;
    }

    /**
     * Release resources if still active
     */
    public function __destruct()
    {
        if (is_resource($this->handle)) {
            curl_close($this->handle);
        }
    }

}