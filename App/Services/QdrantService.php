<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class QdrantService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey = null
    ) {
    }

    public function upsertPoints(string $collection, array $points): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $url = rtrim($this->baseUrl, '/') . '/collections/' . rawurlencode($collection) . '/points';

        $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey !== null) {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant API error: HTTP %d, Response: %s', $httpCode, $response));
        }

        return json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);
    }
}
