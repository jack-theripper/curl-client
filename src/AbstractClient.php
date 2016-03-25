<?php

/**
 * Created by Arhitector.
 * Date: 16.03.2016
 * Time: 17:29
 */

namespace Mackey\Http\Client\Curl;


use Psr\Http\Message\ResponseInterface;

class AbstractClient
{

    /**
     * Response chunk size
     */
    const CHUNK_SIZE = 8192;

    /**
     * cURL options
     *
     * @var array
     */
    protected $options;


    /**
     * Send the response the client
     *
     * @param ResponseInterface $response
     */
    public function sendResponse(ResponseInterface $response)
    {
        if (!headers_sent()) {
            header(sprintf('HTTP/%s %s %s', $response->getProtocolVersion(), $response->getStatusCode(),
                $response->getReasonPhrase()));

            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }
        }

        if (!in_array($response->getStatusCode(), [204, 205, 304])) {
            $body = $response->getBody();

            if ($body->isSeekable()) {
                $body->rewind();
            }

            $contentLength = $response->getHeaderLine('Content-Length');

            if (!$contentLength) {
                $contentLength = $body->getSize();
            }

            if (isset($contentLength)) {
                while (!$body->eof() && $contentLength > 0) {
                    echo $body->read(self::CHUNK_SIZE);

                    if (connection_status() != CONNECTION_NORMAL) {
                        break;
                    }

                    $contentLength -= self::CHUNK_SIZE;
                }
            } else {
                while (!$body->eof()) {
                    echo $body->read(self::CHUNK_SIZE);

                    if (connection_status() != CONNECTION_NORMAL) {
                        break;
                    }
                }
            }
        }
    }

}