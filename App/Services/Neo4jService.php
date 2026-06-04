<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class Neo4jService
{
    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly string $database = 'neo4j'
    ) {
    }

    public function executeCypher(string $query, array $params = []): array
    {
        $url = rtrim($this->host, '/') . '/db/' . urlencode($this->database) . '/tx/commit';

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => (object)$params,
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL for Neo4jService.');
        }

        $auth = base64_encode($this->username . ':' . $this->password);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . $auth,
            ],
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error in Neo4jService: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf(
                'Neo4j API HTTP error: code %d. Response: %s',
                $httpCode,
                (string)$response
            ));
        }

        $responseData = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);

        if (isset($responseData['errors']) && count($responseData['errors']) > 0) {
            throw new RuntimeException(sprintf(
                'Neo4j Cypher error: %s',
                json_encode($responseData['errors'], JSON_THROW_ON_ERROR)
            ));
        }

        return $responseData;
    }
}
