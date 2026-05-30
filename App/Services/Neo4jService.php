<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class Neo4jService
{
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 7474,
        private readonly string $username = 'neo4j',
        private readonly string $password = 'password'
    ) {
    }

    public function executeCypher(string $query, array $params = []): array
    {
        $url = sprintf('http://%s:%d/db/neo4j/tx/commit', $this->host, $this->port);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL session.');
        }

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => (object)$params,
                ]
            ]
        ]);

        if ($payload === false) {
            throw new RuntimeException('Failed to JSON encode Neo4j payload.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $auth = base64_encode(sprintf('%s:%s', $this->username, $this->password));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . $auth
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(sprintf('cURL error during Neo4j executeCypher: %s', $error));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j executeCypher failed with HTTP status code %d: %s', $statusCode, (string)$response));
        }

        $responseData = json_decode((string)$response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
             throw new RuntimeException('Failed to parse Neo4j response JSON.');
        }

        if (!empty($responseData['errors'])) {
            $errorMsg = json_encode($responseData['errors']);
            throw new RuntimeException(sprintf('Neo4j executeCypher returned errors: %s', $errorMsg));
        }

        return $responseData;
    }
}
