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
        private readonly string $database = 'neo4j',
    ) {
    }

    public function executeCypher(string $query, array $params = []): array
    {
        $url = rtrim($this->host, '/') . "/db/{$this->database}/tx/commit";

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL for Neo4jService.');
        }

        // Neo4j requires empty params to be an object `{}`, not an array `[]`
        $parameters = empty($params) ? (object)$params : $params;

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => $parameters,
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json;charset=UTF-8',
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error in Neo4jService: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j API HTTP error. HTTP Code: %d, Response: %s', $httpCode, $response));
        }

        $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        // Explicitly check for Cypher statement errors within a 200 OK response
        if (!empty($responseData['errors'])) {
            $errorDetails = json_encode($responseData['errors'], JSON_THROW_ON_ERROR);
            throw new RuntimeException('Neo4j Cypher error(s): ' . $errorDetails);
        }

        return $responseData;
    }
}
