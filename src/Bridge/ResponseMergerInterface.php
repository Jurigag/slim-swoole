<?php

namespace Pachico\SlimSwoole\Bridge;

use Slim\Http;
use Swoole\Channel;
use swoole_http_response;

interface ResponseMergerInterface
{
    /**
     * @param Http\Response $slimResponse
     * @param swoole_http_response $swooleResponse
     */
    public function mergeToSwoole(
        Http\Response $slimResponse,
        swoole_http_response $swooleResponse,
        Channel $channel
    ): void;
}
