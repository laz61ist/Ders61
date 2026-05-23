<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

readonly class QdrantService
{
    public function __construct(
        private string $host,
        private string $apiKey = '',
    ) {}

    public function upsertPoints(string $collection, array $points): array
    {
        $url = sprintf('%s/collections/%s/points', rtrim($this->host, '/'), urlencode($collection));

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant API error: HTTP %d - %s', $statusCode, (string)$response));
        }

        return json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);
    }

    public function searchPoints(string $collection, array $vector, ?string $vectorName = null, int $limit = 5): array
    {
        $url = sprintf('%s/collections/%s/points/search', rtrim($this->host, '/'), urlencode($collection));

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $requestBody = [
            'vector' => $vectorName !== null ? ['name' => $vectorName, 'vector' => $vector] : $vector,
            'limit' => $limit,
            'with_payload' => true,
        ];

        $payload = json_encode($requestBody, JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant API error: HTTP %d - %s', $statusCode, (string)$response));
        }

        return json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);
    }
}
