<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

readonly class QdrantService
{
    public function __construct(
        private string $host = 'http://localhost:6333',
        private ?string $apiKey = null
    ) {
    }

    /**
     * @param string $collection The name of the collection.
     * @param array $points The points to upsert.
     * @return array The response from the Qdrant API.
     * @throws RuntimeException If a cURL error occurs or a non-200 HTTP response code is returned.
     */
    public function upsertPoints(string $collection, array $points): array
    {
        $url = sprintf('%s/collections/%s/points?wait=true', rtrim($this->host, '/'), urlencode($collection));

        $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL handle for Qdrant API.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey !== null) {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL error during Qdrant upsert: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant API returned non-200 HTTP response code: %d. Response: %s', $httpCode, (string) $response));
        }

        return json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
    }
}
