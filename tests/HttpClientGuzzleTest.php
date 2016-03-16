<?php
namespace Http\Client\Curl\Tests;

use Mackey\Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Http\Message\MessageFactory\GuzzleMessageFactory;
use Http\Message\StreamFactory\GuzzleStreamFactory;

/**
 * Tests for Http\Client\Curl\Client
 */
class HttpClientGuzzleTest extends HttpClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAdapter()
    {
        return new Client(new GuzzleMessageFactory(), new GuzzleStreamFactory());
    }
}
