<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class QdrantService
{
    public function __construct(
        private readonly string $host,
        private readonly string $apiKey = ''
    ) {
    }

    public function upsertPoints(string $collection, array $points): array
    {
        $url = rtrim($this->host, '/') . "/collections/{$collection}/points?wait=true";

        $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);

        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        $headers = [
            'Content-Type: application/json',
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("cURL Error: $error");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Qdrant API error. HTTP Code: $httpCode, Response: " . (string)$response);
        }

        return json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);
    }
}
