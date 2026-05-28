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
        $url = rtrim($this->host, '/') . "/db/{$this->database}/tx/commit";

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => (object)$params // Cast to object to ensure it is encoded as an empty object rather than an empty array if empty
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode("{$this->username}:{$this->password}")
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
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
            throw new RuntimeException("Neo4j API error. HTTP Code: $httpCode, Response: " . (string)$response);
        }

        $decodedResponse = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);

        // Neo4j API returns 200 OK even if there are transaction errors, so we need to check for those.
        if (isset($decodedResponse['errors']) && count($decodedResponse['errors']) > 0) {
            $errorMsg = json_encode($decodedResponse['errors']);
            throw new RuntimeException("Neo4j Cypher error: " . $errorMsg);
        }

        return $decodedResponse;
    }
}
