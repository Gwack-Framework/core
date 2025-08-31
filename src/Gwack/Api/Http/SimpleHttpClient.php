<?php

namespace Gwack\Api\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simple HTTP client for testing API endpoints
 *
 * @package Gwack\Api\Http
 */
class SimpleHttpClient
{
    /**
     * @var string Base URL
     */
    private string $baseUrl;

    /**
     * @var array Default headers
     */
    private array $defaultHeaders;

    /**
     * Constructor
     *
     * @param string $baseUrl Base URL for requests
     * @param array $defaultHeaders Default headers
     */
    public function __construct(string $baseUrl = '', array $defaultHeaders = [])
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $defaultHeaders);
    }

    /**
     * Make a GET request
     *
     * @param string $path
     * @param array $headers
     * @return array
     */
    public function get(string $path, array $headers = []): array
    {
        return $this->makeRequest('GET', $path, null, $headers);
    }

    /**
     * Make a POST request
     *
     * @param string $path
     * @param mixed $data
     * @param array $headers
     * @return array
     */
    public function post(string $path, mixed $data = null, array $headers = []): array
    {
        return $this->makeRequest('POST', $path, $data, $headers);
    }

    /**
     * Make a PUT request
     *
     * @param string $path
     * @param mixed $data
     * @param array $headers
     * @return array
     */
    public function put(string $path, mixed $data = null, array $headers = []): array
    {
        return $this->makeRequest('PUT', $path, $data, $headers);
    }

    /**
     * Make a PATCH request
     *
     * @param string $path
     * @param mixed $data
     * @param array $headers
     * @return array
     */
    public function patch(string $path, mixed $data = null, array $headers = []): array
    {
        return $this->makeRequest('PATCH', $path, $data, $headers);
    }

    /**
     * Make a DELETE request
     *
     * @param string $path
     * @param array $headers
     * @return array
     */
    public function delete(string $path, array $headers = []): array
    {
        return $this->makeRequest('DELETE', $path, null, $headers);
    }

    /**
     * Make HTTP request using cURL
     *
     * @param string $method
     * @param string $path
     * @param mixed $data
     * @param array $headers
     * @return array
     */
    private function makeRequest(string $method, string $path, mixed $data = null, array $headers = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $headers = array_merge($this->defaultHeaders, $headers);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('HTTP request failed');
        }

        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        return [
            'status' => $httpCode,
            'headers' => $this->parseHeaders($responseHeaders),
            'body' => $responseBody,
            'data' => json_decode($responseBody, true) ?: []
        ];
    }

    /**
     * Format headers for cURL
     *
     * @param array $headers
     * @return array
     */
    private function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = "$key: $value";
        }
        return $formatted;
    }

    /**
     * Parse response headers
     *
     * @param string $headerString
     * @return array
     */
    private function parseHeaders(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }
}
