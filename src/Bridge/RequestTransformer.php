<?php

namespace Pachico\SlimSwoole\Bridge;

use Slim\Http;
use Swoole\Channel;
use swoole_http_request;
use Dflydev\FigCookies\Cookie;
use Dflydev\FigCookies\FigRequestCookies;

class RequestTransformer implements RequestTransformerInterface
{
    const DEFAULT_SCHEMA = 'http';

    /**
     * @param swoole_http_request $request
     *
     * @todo Handle HTTPS requests
     */
    public function toSlim(swoole_http_request $request, Http\Request $slimRequest, Channel $channel): void
    {
        go(function () use ($request, $slimRequest, $channel) {
            $this->copyHeaders($request, $slimRequest);
            $channel->push(1);
        });

        go(function () use ($request, $slimRequest, $channel) {
            if ($this->isMultiPartFormData($request) || $this->isXWwwFormUrlEncoded($request)) {
                $this->handlePostData($request, $slimRequest);
            }
            $channel->push(1);
        });

        go(function() use($request, $slimRequest,$channel) {
            if ($this->isMultiPartFormData($request)) {
                $this->handleUploadedFiles($request, $slimRequest);
            }
            $channel->push(1);
        });

        go(function() use($request, $slimRequest, $channel) {
            $this->copyCookies($request, $slimRequest);
            $channel->push(1);
        });

        go(function() use ($request, $slimRequest, $channel) {
            $this->copyBody($request, $slimRequest);
            $channel->push(1);
        });
    }

    /**
     * @param swoole_http_request $request
     * @param Http\Request $slimRequest
     */
    private function copyCookies(swoole_http_request $request, Http\Request $slimRequest):void
    {
        if (!empty($request->cookie)) {
            foreach ($request->cookie as $name => $value) {
                $cookie = Cookie::create($name, $value);
                FigRequestCookies::set($slimRequest, $cookie);
            }
        }
    }

    /**
     * @param swoole_http_request $request
     * @param Http\Request $slimRequest
     *
     * @return Http\Request
     */
    private function copyBody(swoole_http_request $request, Http\Request $slimRequest): void
    {
        if (empty($request->rawContent())) {
            return;
        }

        $body = $slimRequest->getBody();
        $body->write($request->rawContent());
        $body->rewind();

        $slimRequest->withBody($body);
    }

    /**
     * @param swoole_http_request $request
     * @param Http\Request $slimRequest
     *
     * @return Http\Request
     */
    private function copyHeaders(swoole_http_request $request, Http\Request $slimRequest): void
    {

        foreach ($request->header as $key => $val) {
            $slimRequest = $slimRequest->withHeader($key, $val);
        }
    }

    /**
     * @param swoole_http_request $request
     *
     * @return boolean
     */
    private function isMultiPartFormData(swoole_http_request $request): bool
    {

        if (!isset($request->header['content-type'])
            || false === stripos($request->header['content-type'], 'multipart/form-data')) {
            return false;
        }

        return true;
    }

    /**
     * @param swoole_http_request $request
     *
     * @return boolean
     */
    private function isXWwwFormUrlEncoded(swoole_http_request $request): bool
    {

        if (!isset($request->header['content-type'])
            || false === stripos($request->header['content-type'], 'application/x-www-form-urlencoded')) {
            return false;
        }

        return true;
    }


    /**
     * @param swoole_http_request $request
     * @param Http\Request $slimRequest
     */
    private function handleUploadedFiles(swoole_http_request $request, Http\Request $slimRequest): void
    {
        if (empty($request->files) || !is_array($request->files)) {
            return;
        }

        $uploadedFiles = [];

        foreach ($request->files as $key => $file) {
            $uploadedFiles[$key] = new Http\UploadedFile(
                $file['tmp_name'],
                $file['name'],
                $file['type'],
                $file['size'],
                $file['error']
            );
        }

        $slimRequest->withUploadedFiles($uploadedFiles);
    }

    /**
     * @param swoole_http_request $swooleRequest
     * @param Http\Request $slimRequest
     *
     * @return Http\Request
     */
    private function handlePostData(swoole_http_request $swooleRequest, Http\Request $slimRequest): void
    {
        if (empty($swooleRequest->post) || !is_array($swooleRequest->post)) {
            return;
        }

        $slimRequest->withParsedBody($swooleRequest->post);
    }
}
