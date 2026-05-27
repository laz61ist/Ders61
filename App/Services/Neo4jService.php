<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class Neo4jService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly string $database = 'neo4j'
    ) {
    }

    public function executeCypher(string $query, array $params = []): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $url = rtrim($this->baseUrl, '/') . '/db/' . rawurlencode($this->database) . '/tx/commit';

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => (object)$params,
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password)
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
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
            throw new RuntimeException(sprintf('Neo4j API error: HTTP %d, Response: %s', $httpCode, $response));
        }

        $responseData = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);

        if (isset($responseData['errors']) && count($responseData['errors']) > 0) {
            throw new RuntimeException('Neo4j Cypher error: ' . json_encode($responseData['errors']));
        }

        return $responseData;
    }
}
