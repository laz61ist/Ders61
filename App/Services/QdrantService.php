<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class QdrantService
{
    public function __construct(
        private readonly string $host,
        private readonly int $port = 6333,
        private readonly string $scheme = 'http',
        private readonly ?string $apiKey = null
    ) {
    }

    /**
     * Upsert points to a Qdrant collection.
     *
     * @param string $collection The name of the collection.
     * @param array $points The array of points to upsert.
     * @return array The decoded JSON response.
     * @throws RuntimeException If there is a cURL error or non-200 HTTP response.
     */
    public function upsertPoints(string $collection, array $points): array
    {
        $url = sprintf('%s://%s:%d/collections/%s/points?wait=true', $this->scheme, $this->host, $this->port, $collection);

        $payload = json_encode(['points' => $points]);
        if ($payload === false) {
            throw new RuntimeException('Failed to JSON encode points payload.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($this->apiKey !== null) {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL error: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant HTTP error: %d. Response: %s', $httpCode, $response));
        }

        $decodedResponse = json_decode((string) $response, true);
        if (!is_array($decodedResponse)) {
            throw new RuntimeException('Failed to decode Qdrant JSON response.');
        }

        return $decodedResponse;
    }
}
