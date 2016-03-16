<?php
namespace Http\Client\Curl\Tests;

use Mackey\Http\Client\Curl\Client;
use Http\Client\HttpClient;
use Http\Message\MessageFactory\DiactorosMessageFactory;
use Http\Message\StreamFactory\DiactorosStreamFactory;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;

/**
 * Tests for Http\Client\Curl\Client
 */
class HttpClientDiactorosTest extends HttpClientTestCase
{
    /**
     * @return HttpClient
     */
    protected function createHttpAdapter()
    {
        return new Client(new DiactorosMessageFactory(), new DiactorosStreamFactory());
    }
}
