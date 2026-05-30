<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class QdrantService
{
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 6333
    ) {
    }

    public function upsertPoints(string $collection, array $points): void
    {
        $url = sprintf('http://%s:%d/collections/%s/points', $this->host, $this->port, $collection);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL session.');
        }

        $payload = json_encode(['points' => $points]);
        if ($payload === false) {
            throw new RuntimeException('Failed to JSON encode points.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(sprintf('cURL error during Qdrant upsert: %s', $error));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('Qdrant upsert failed with HTTP status code %d: %s', $statusCode, (string)$response));
        }
    }
}
