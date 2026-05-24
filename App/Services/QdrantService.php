<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use JsonException;

class QdrantService
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $apiKey = null
    ) {
    }

    /**
     * @param string $collection
     * @param array $points
     * @return void
     * @throws RuntimeException
     */
    public function upsertPoints(string $collection, array $points): void
    {
        $endpoint = sprintf('%s/collections/%s/points', rtrim($this->url, '/'), $collection);

        try {
            $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to JSON encode points: ' . $e->getMessage(), 0, $e);
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL session.');
        }

        $headers = ['Content-Type: application/json'];
        if ($this->apiKey !== null) {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('cURL error: ' . $error);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant API error (Status: %d): %s', $statusCode, (string)$response));
        }
    }
}
