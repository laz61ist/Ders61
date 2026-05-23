<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use RuntimeException;

class QdrantService
{
    public function __construct(
        private readonly string $host,
        private readonly string $apiKey = ''
    ) {
    }

    /**
     * Upserts points into a Qdrant collection.
     *
     * @param string $collection The name of the collection.
     * @param array $points The points to upsert.
     * @return array The JSON-decoded response from Qdrant.
     * @throws RuntimeException If there is a cURL error or non-200 HTTP response.
     */
    public function upsertPoints(string $collection, array $points): array
    {
        $url = sprintf('%s/collections/%s/points?wait=true', rtrim($this->host, '/'), $collection);

        $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL session.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL Error: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant API error (HTTP %d): %s', $httpCode, $response));
        }

        $decodedResponse = json_decode((string)$response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode Qdrant JSON response: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }
}
