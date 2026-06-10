<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class QdrantService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey
    ) {
    }

    public function upsertPoints(string $collection, array $points): array
    {
        $url = sprintf('%s/collections/%s/points?wait=true', rtrim($this->baseUrl, '/'), $collection);

        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'api-key: ' . $this->apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(sprintf('cURL Error: %s', $error));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('HTTP Error %d: %s', $statusCode, (string) $response));
        }

        return json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);
    }
}
