<?php

namespace Pachico\SlimSwoole\Bridge;

use Slim\App;
use Slim\Http;
use Swoole\Channel;
use swoole_http_response;

class ResponseMerger implements ResponseMergerInterface
{
    /**
     * @var App
     */
    private $app;

    /**
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * @param Http\Response $slimResponse
     * @param swoole_http_response $swooleResponse
     */
    public function mergeToSwoole(
        Http\Response $slimResponse,
        swoole_http_response $swooleResponse,
        Channel $channel
    ): void {
        go(function () use ($channel, $swooleResponse, $slimResponse) {
            $container = $this->app->getContainer();
            $settings = $container->get('settings');
            if (isset($settings['addContentLengthHeader']) && $settings['addContentLengthHeader'] == true) {
                $size = $slimResponse->getBody()->getSize();
                if ($size !== null) {
                    $swooleResponse->header('Content-Length', (string)$size);
                }
            }

            if (!empty($slimResponse->getHeaders())) {
                foreach ($slimResponse->getHeaders() as $key => $headerArray) {
                    $swooleResponse->header($key, implode('; ', $headerArray));
                }
            }
            $channel->push(1);
        });

        go(function() use($swooleResponse, $slimResponse, $channel) {
            $swooleResponse->status($slimResponse->getStatusCode());
            $channel->push(1);
        });

        go(function() use ($swooleResponse, $slimResponse, $channel){
            if ($slimResponse->getBody()->getSize() > 0) {
                if ($slimResponse->getBody()->isSeekable()) {
                    $slimResponse->getBody()->rewind();
                }

                $swooleResponse->write($slimResponse->getBody()->getContents());
            }

            $channel->push(1);
        });
    }
}
