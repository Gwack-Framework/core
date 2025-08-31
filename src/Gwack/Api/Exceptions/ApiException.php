<?php

namespace Gwack\Api\Exceptions;

use Exception;

/**
 * Base API exception class
 *
 * @package Gwack\Api\Exceptions
 */
class ApiException extends Exception
{
    /**
     * @var int HTTP status code
     */
    protected int $statusCode;

    /**
     * @var array Additional data for the exception
     */
    protected array $data;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $data Additional data
     * @param int $code Error code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        int $statusCode = 500,
        array $data = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $statusCode;
        $this->data = $data;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get additional data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
