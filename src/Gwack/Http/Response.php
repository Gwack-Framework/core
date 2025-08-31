<?php

namespace Gwack\Http;

use \Symfony\Component\HttpFoundation\Response as BaseResponse;

/*
 * Gwack HTTP Response Class
 *
 * This class represents an HTTP response in the Gwack framework.
 * It can be extended to add specific functionality for handling responses.
 *
 * @package Gwack\Http
 */
class Response extends BaseResponse
{
    /**
     * Create a new response instance.
     *
     * @param string $content The content of the response.
     * @param int $status The HTTP status code.
     * @param array $headers An array of headers to send with the response.
     */
    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        parent::__construct($content, $status, $headers);
    }

    public function serialize(): string
    {
        // Serialize the response content to a string format
        return $this->getContent();
    }
}