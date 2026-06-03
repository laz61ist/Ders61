<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

readonly class QdrantService
{
    public function __construct(
        private string $host = 'http://localhost:6333',
        private string $apiKey = ''
    ) {}

    public function upsertPoints(string $collection, array $points): array
    {
        $url = rtrim($this->host, '/') . "/collections/{$collection}/points?wait=true";
        $payload = json_encode(['points' => $points], JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL for QdrantService.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'api-key: ' . $this->apiKey;
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error in QdrantService: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant API error. HTTP code: %d. Response: %s', $httpCode, $response));
        }

        return json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);
    }
}
