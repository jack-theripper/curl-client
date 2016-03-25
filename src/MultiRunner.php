<?php
namespace Mackey\Http\Client\Curl;

use Http\Client\Exception\RequestException;
use Mackey\Http\Client\Curl\Response\Parser;

/**
 * Simultaneous requests runner
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 * @author  Dmitry Arhitector   <dmitry.arhitector@yandex.ru>
 */
class MultiRunner
{
    /**
     * cURL multi handle
     *
     * @var resource|null
     */
    protected $handle = null;

    /**
     * Awaiting cores
     *
     * @var PromiseCore[]
     */
    protected $cores = [];

    /**
     * @var Parser  response parser
     */
    protected $responseParser;

    /**
     * Construct new runner.
     *
     * @param Parser $response_parser
     */
    public function __construct(Parser $response_parser)
    {
        $this->handle = curl_multi_init();
        $this->responseParser = $response_parser;
    }

    /**
     * Add promise to runner
     *
     * @param PromiseCore $core
     */
    public function add(PromiseCore $core)
    {
        foreach ($this->cores as $existed) {
            if ($existed === $core) {
                return;
            }
        }

        $this->cores[] = $core;

        if (curl_multi_add_handle($this->handle, $core->getHandle()) !== 0) {
            throw new \RuntimeException('Handler was not added.');
        }
    }

    /**
     * Wait for request(s) to be completed.
     *
     * @param PromiseCore|null $targetCore
     */
    public function wait(PromiseCore $targetCore = null)
    {
        do {
            $status = curl_multi_exec($this->handle, $active);
            $info = curl_multi_info_read($this->handle);

            if (false !== $info) {
                $core = $this->findCoreByHandle($info['handle']);

                if (null === $core) {
                    // We have no promise for this handle. Drop it.
                    curl_multi_remove_handle($this->handle, $info['handle']);
                    continue;
                }

                if (CURLE_OK === $info['result']) {
                    try {
                        $response = $this->responseParser->parse(
                            curl_getinfo($core->getHandle()),
                            $core->getTemporaryStream()
                        );
                        $core->fulfill($response);
                    } catch (\Exception $e) {
                        $core->reject(new RequestException($e->getMessage(), $core->getRequest(), $e));
                    }
                } else {
                    $error = curl_error($core->getHandle());
                    $core->reject(new RequestException($error, $core->getRequest()));
                }

                $this->remove($core);
            }
        } while ($status === CURLM_CALL_MULTI_PERFORM || $active);
    }

    /**
     * Remove promise from runner
     *
     * @param PromiseCore $core
     */
    public function remove(PromiseCore $core)
    {
        foreach ($this->cores as $index => $existed) {
            if ($existed === $core) {
                curl_multi_remove_handle($this->handle, $core->getHandle());
                unset($this->cores[$index]);
                return;
            }
        }
    }

    /**
     * Release resources if still active
     */
    public function __destruct()
    {
        if (is_resource($this->handle)) {
            curl_multi_close($this->handle);
        }
    }

    /**
     * Find core by handle.
     *
     * @param resource $handle
     *
     * @return PromiseCore|null
     */
    protected function findCoreByHandle($handle)
    {
        foreach ($this->cores as $core) {
            if ($core->getHandle() === $handle) {
                return $core;
            }
        }
        return null;
    }
}