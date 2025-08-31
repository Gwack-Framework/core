<?php

class HttpException extends \Exception
{
    /**
     * The HTTP status code for the exception.
     *
     * @var int
     */
    protected $statusCode;

    /**
     * Create a new HttpException instance.
     *
     * @param string $message
     * @param int $statusCode
     */
    public function __construct(int $statusCode = 500, string $message)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}