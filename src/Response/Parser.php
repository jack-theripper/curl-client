<?php

namespace Mackey\Http\Client\Curl\Response;

use Mackey\Http\Client\Curl\Tools\HeadersParser;
use Psr\Http\Message\ResponseInterface;
use Http\Message\MessageFactory;
use Http\Message\StreamFactory;

/**
 * cURL raw response parser
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @author  Dmitry Arhitector   <dmitry.arhitector@yandex.ru>
 */
class Parser
{
    use TemporaryStreamTrait;

    /**
     * Raw response headers
     *
     * @var array
     */
    protected $headers = [];

    /**
     * PSR-7 message factory
     *
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * PSR-7 stream factory
     *
     * @var StreamFactory
     */
    protected $streamFactory;

    /**
     * Receive redirect
     *
     * @var bool
     */
    protected $followLocation = false;


    /**
     * Create new parser.
     *
     * @param MessageFactory $messageFactory HTTP Message factory
     * @param StreamFactory  $streamFactory  HTTP Stream factory
     */
    public function __construct(MessageFactory $messageFactory, StreamFactory $streamFactory)
    {
        $this->messageFactory = $messageFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * Parse cURL response
     *
     * @param array $info cURL response info
     * @param resource $stream $raw raw response
     *
     * @return ResponseInterface
     */
    public function parse(array $info, $stream = null)
    {
        if ( ! is_resource($stream)) {
            $stream = $this->getTemporaryStream();
        }

        $parser = new HeadersParser();
        $response = $parser->parseArray($this->headers, $this->messageFactory->createResponse());
        $response = $response->withBody($this->streamFactory->createStream($stream));

        $this->setTemporaryStream(null);

        return $response;
    }

    /**
     * Set factory.
     *
     * @param MessageFactory $messageFactory
     *
     * @return $this
     */
    public function setMessageFactory(MessageFactory $messageFactory)
    {
        $this->messageFactory = $messageFactory;

        return $this;
    }

    /**
     * Get factory.
     *
     * @return MessageFactory
     */
    public function getMessageFactory()
    {
        return $this->messageFactory;
    }

    /**
     * Set factory.
     *
     * @param StreamFactory $streamFactory
     *
     * @return $this
     */
    public function setStreamFactory(StreamFactory $streamFactory)
    {
        $this->streamFactory = $streamFactory;

        return $this;
    }

    /**
     * Get factory.
     *
     * @return StreamFactory
     */
    public function getStreamFactory()
    {
        return $this->streamFactory;
    }

    /**
     * Save the response headers
     *
     * @param   resource    $handler    curl handler
     * @param   string      $rawHeader     raw header
     *
     * @return integer
     */
    public function headerHandler($handler, $rawHeader)
    {
        $header = trim($rawHeader);
        $this->headers[] = $header;

        if ($this->followLocation) {
            $this->followLocation = false;
            $this->headers = [$header];
        } else if ( ! $header) {
            $this->followLocation = true;
        }

        return strlen($rawHeader);
    }

}