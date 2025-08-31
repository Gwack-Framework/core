<?php

namespace Gwack\Http;

use Symfony\Component\HttpFoundation\JsonResponse as BaseResponse;


class JsonResponse extends BaseResponse
{
    /**
     * Create a new JSON response instance.
     *
     * @param mixed $data The data to be encoded as JSON.
     * @param int $status The HTTP status code.
     * @param array $headers An array of headers to send with the response.
     */
    public function __construct(mixed $data, int $status = 200, array $headers = [])
    {
        parent::__construct($data, $status, $headers);
        $this->headers->set('Content-Type', 'application/json');
    }
}