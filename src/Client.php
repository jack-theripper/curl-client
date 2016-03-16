<?php

namespace Mackey\Http\Client\Curl;

use Http\Client\Exception;
use Http\Client\Exception\RequestException;
use Http\Client\HttpAsyncClient as HttpAsyncClientInterface;
use Http\Client\HttpClient as HttpClientInterface;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;
use Http\Promise\Promise;
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
     * @var ResponseParser
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
     * @param StreamFactory  $streamFactory  HTTP Stream factory
     * @param array          $options        cURL options (see http://php.net/curl_setopt)
     */
    public function __construct(MessageFactory $messageFactory, StreamFactory $streamFactory, array $options = [])
    {
        $this->options = $options;
        $this->handle = curl_init();
        $this->setResponseParser(new ResponseParser($messageFactory, $streamFactory));
    }

    /**
     * Sends a PSR-7 request.
     *
     * @param RequestInterface $request
     * @param array            $options custom curl options
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

        if ( ! curl_exec($this->handle)) {
            throw new RequestException(curl_error($this->handle), $request);
        }

        try {
            $response = $this->responseParser->parse($options[CURLOPT_FILE], curl_getinfo($this->handle));
        } catch (\Exception $e) {
            throw new RequestException($e->getMessage(), $request, $e);
        }

        return $response;
    }

    /**
     * Sends a PSR-7 request in an asynchronous way.
     *
     * @param RequestInterface $request
     * @param array            $options custom curl options
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
        if ( ! $this->multiRunner instanceof MultiRunner) {
            $this->multiRunner = new MultiRunner($this->responseParser);
        }

        $handle = curl_init();
        $options = $this->createCurlOptions($request, $options);

        curl_setopt_array($handle, $options);

        $core = new PromiseCore($request, $handle);
        $promise = new CurlPromise($core, $this->multiRunner);

        $this->multiRunner->add($core);

        return $promise;
    }

    /**
     * Set response parser
     *
     * @param ResponseParser $responseParser
     *
     * @return $this
     */
    public function setResponseParser(ResponseParser $responseParser)
    {
        $this->responseParser = $responseParser;

        return $this;
    }

    /**
     * Get parser
     *
     * @return ResponseParser
     */
    public function getResponseParser()
    {
        return $this->responseParser;
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

    /**
     * Generates cURL options
     *
     * @param RequestInterface $request
     * @param array            $options custom curl options
     *
     * @throws \UnexpectedValueException if unsupported HTTP version requested
     *
     * @return array
     */
    protected function createCurlOptions(RequestInterface $request, array $options = [])
	{
        // Invalid overwrite Curl options.
        $options = array_diff_key($options, array_flip([CURLOPT_INFILE, CURLOPT_INFILESIZE]));
        $options[CURLOPT_HTTP_VERSION] = $this->getCurlHttpVersion($request->getProtocolVersion());
        $options[CURLOPT_HEADERFUNCTION] = [$this->getResponseParser(), 'headerHandler'];
        $options[CURLOPT_CUSTOMREQUEST] = $request->getMethod();
        $options[CURLOPT_URL] = (string) $request->getUri();
        $options[CURLOPT_RETURNTRANSFER] = false;
        $options[CURLOPT_FILE] = $this->getResponseParser()->getTemporaryStream();
        $options[CURLOPT_HEADER] = false;

        // These methods do not transfer body.
        // You can specify any method you'd like, including a custom method that might not be part of RFC 7231 (like "MOVE").
		if (in_array($request->getMethod(), ['GET', 'HEAD', 'TRACE', 'CONNECT'])) {
			if ($request->getMethod() == 'HEAD') {
				$options[CURLOPT_NOBODY] = true;

                unset($options[CURLOPT_READFUNCTION], $options[CURLOPT_WRITEFUNCTION]);
			}
		} else {
			$body = clone $request->getBody();
			$size = $body->getSize();

			if ($size === null || $size > 1048576) {
                $body->rewind();
                $options[CURLOPT_UPLOAD] = true;

                // Avoid full loading large or unknown size body into memory. Not replace CURLOPT_READFUNCTION.
                if (isset($options[CURLOPT_READFUNCTION]) && is_callable($options[CURLOPT_READFUNCTION])) {
                    $body = $body->detach();
                    $options[CURLOPT_READFUNCTION] = function ($curlHandler, $handler, $length) use ($body, $options) {
                        return call_user_func($options[CURLOPT_READFUNCTION], $curlHandler, $body, $length);
                    };
                } else {
                    $options[CURLOPT_READFUNCTION] = function ($curl, $handler, $length) use ($body) {
                        return $body->read($length);
                    };
                }
			} else {
                // Send the body as a string if the size is less than 1MB.
				$options[CURLOPT_POSTFIELDS] = (string) $request->getBody();
			}
		}

		$options[CURLOPT_HTTPHEADER] = $this->createHeaders($request, $options);

		if ($request->getUri()->getUserInfo()) {
			$options[CURLOPT_USERPWD] = $request->getUri()->getUserInfo();
		}

		return $options;
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
        } else if ($version == '2.0') {
            if (!defined('CURL_HTTP_VERSION_2_0')) {
                throw new \UnexpectedValueException('libcurl 7.33 needed for HTTP 2.0 support');
            }

            return CURL_HTTP_VERSION_2_0;
        } else {
            return CURL_HTTP_VERSION_1_0;
        }
    }

    /**
     * Create headers array for CURLOPT_HTTPHEADER
     *
     * @param RequestInterface $request
     * @param array            $options cURL options
     *
     * @return string[]
     */
    protected function createHeaders(RequestInterface $request, array $options)
    {
        $headers = [];

        foreach ($request->getHeaders() as $header => $values)
        {
            foreach ((array) $values as $value)
            {
                $headers[] = sprintf('%s: %s', $header, $value);
            }
        }

        if ( ! $request->hasHeader('Content-Length')) {
        //    $headers[] = 'Content-Length: 0';
        }

        if ( ! $request->hasHeader('Expect')) {
            $headers[] = 'Expect:';
        }

        return $headers;
    }

}