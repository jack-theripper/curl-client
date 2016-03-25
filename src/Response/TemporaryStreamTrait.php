<?php

namespace Mackey\Http\Client\Curl\Response;

/**
 * Part of response parser.
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @author  Dmitry Arhitector   <dmitry.arhitector@yandex.ru>
 */
trait TemporaryStreamTrait
{

    /**
     * Temporary resource
     *
     * @var resource
     */
    protected $temporaryStream;

    /**
     * Set temporary stream
     *
     * @param resource|null $resource
     *
     * @return $this
     */
    public function setTemporaryStream($resource)
    {
        if ( ! is_resource($resource) && $resource !== null) {
            throw new \InvalidArgumentException('Temporary stream must be a resource type.');
        }

        $this->temporaryStream = $resource;

        return $this;
    }

    /**
     * Temporary body (fix out of memory)
     *
     * @return resource
     */
    public function getTemporaryStream()
    {
        if ( ! is_resource($this->temporaryStream))
        {
            $this->temporaryStream = fopen('php://temp', 'w+');
        }

        return $this->temporaryStream;
    }

}