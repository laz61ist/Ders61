<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class QdrantService
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $apiKey = ''
    ) {
    }

    public function upsertPoints(string $collection, array $points): void
    {
        $url = sprintf('http://%s:%d/collections/%s/points?wait=true', $this->host, $this->port, $collection);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL Error: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant API returned non-200 HTTP status code: %d. Response: %s', $httpCode, (string)$response));
        }
    }
}
