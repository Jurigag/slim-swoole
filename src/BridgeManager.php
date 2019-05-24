<?php

namespace Pachico\SlimSwoole;

use Pachico\SlimSwoole\Bridge;
use Slim\App;
use Slim\Http;
use Swoole\Channel;
use swoole_http_request;
use swoole_http_response;

class BridgeManager implements BridgeManagerInterface
{
    const DEFAULT_SCHEMA = 'http';

    /**
     * @var App
     */
    private $app;

    /**
     * @var Bridge\RequestTransformerInterface
     */
    private $requestTransformer;

    /**
     * @var Bridge\ResponseMergerInterface
     */
    private $responseMerger;

    /**
     * @param App $app
     * @param Bridge\RequestTransformerInterface $requestTransformer
     * @param Bridge\ResponseMergerInterface $responseMerger
     */
    public function __construct(
        App $app,
        Bridge\RequestTransformerInterface $requestTransformer = null,
        Bridge\ResponseMergerInterface $responseMerger = null
    ) {
        $this->app = $app;
        $this->requestTransformer = $requestTransformer ?: new Bridge\RequestTransformer();
        $this->responseMerger = $responseMerger ?: new Bridge\ResponseMerger($this->app);
    }

    /**
     * @param swoole_http_request $swooleRequest
     * @param swoole_http_response $swooleResponse
     *
     * @return swoole_http_response
     */
    public function process(
        swoole_http_request $swooleRequest,
        swoole_http_response $swooleResponse
    ) {
        $slimRequest = Http\Request::createFromEnvironment(
            new Http\Environment([
                'SERVER_PROTOCOL' => $swooleRequest->server['server_protocol'],
                'REQUEST_METHOD' => $swooleRequest->server['request_method'],
                'REQUEST_SCHEME' => static::DEFAULT_SCHEMA,
                'REQUEST_URI' => $swooleRequest->server['request_uri'],
                'QUERY_STRING' => isset($swooleRequest->server['query_string']) ? $swooleRequest->server['query_string'] : '',
                'SERVER_PORT' => $swooleRequest->server['server_port'],
                'REMOTE_ADDR' => $swooleRequest->server['remote_addr'],
                'REQUEST_TIME' => $swooleRequest->server['request_time'],
                'REQUEST_TIME_FLOAT' => $swooleRequest->server['request_time_float']
            ])
        );
        $channel = new Channel(5);
        $this->requestTransformer->toSlim($swooleRequest, $slimRequest, $channel);
        go(function () use ($channel, $slimRequest, $swooleResponse) {
            $slimResponse = $this->app->process($slimRequest, new Http\Response());
            $channel2 = new Channel(3);
            $this->responseMerger->mergeToSwoole($slimResponse, $swooleResponse, $channel2);
            go(function () use ($channel2, $swooleResponse) {
                $swooleResponse->end();
            });
        });
    }
}
