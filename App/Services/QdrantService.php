<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

readonly class QdrantService
{
    public function __construct(
        private string $host,
        private string $apiKey = ''
    ) {
    }

    /**
     * Upsert points to a Qdrant collection.
     *
     * @param string $collection The name of the collection
     * @param array $points The points to upsert
     * @return array The response body decoded as an array
     * @throws RuntimeException If a cURL error occurs or a non-200 HTTP response is received
     */
    public function upsertPoints(string $collection, array $points): array
    {
        $url = sprintf('%s/collections/%s/points', rtrim($this->host, '/'), urlencode($collection));

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL error during Qdrant upsertPoints: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException(
                sprintf('Qdrant API returned non-200 HTTP status code %d: %s', $httpCode, $response)
            );
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }
}
