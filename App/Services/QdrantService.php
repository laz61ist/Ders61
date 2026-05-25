<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class QdrantService
{
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 6333,
        private readonly ?string $apiKey = null
    ) {
    }

    /**
     * @param string $collection
     * @param array $points
     * @return array
     * @throws RuntimeException
     */
    public function upsertPoints(string $collection, array $points): array
    {
        $url = sprintf('http://%s:%d/collections/%s/points', $this->host, $this->port, $collection);

        $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey !== null) {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(sprintf('cURL error during Qdrant request: %s', $error));
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant API returned non-success HTTP status code: %d. Response: %s', $httpCode, $response));
        }

        return json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
    }
}
