<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

readonly class Neo4jService
{
    public function __construct(
        private string $host,
        private string $username,
        private string $password,
        private string $database = 'neo4j'
    ) {
    }

    /**
     * Execute a Cypher query using Neo4j's HTTP API.
     *
     * @param string $query The Cypher query to execute
     * @param array $params The parameters for the query
     * @return array The response body decoded as an array
     * @throws RuntimeException If a cURL error occurs, a non-200 HTTP response is received, or Neo4j returns errors
     */
    public function executeCypher(string $query, array $params = []): array
    {
        $url = sprintf('%s/db/%s/tx/commit', rtrim($this->host, '/'), urlencode($this->database));

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(sprintf('%s:%s', $this->username, $this->password)),
        ];

        // Ensure empty parameters array is cast to object so it serializes as {} instead of []
        if (empty($params)) {
            $params = (object) [];
        }

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => $params,
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL error during Neo4j executeCypher: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException(
                sprintf('Neo4j API returned non-200 HTTP status code %d: %s', $httpCode, $response)
            );
        }

        $responseData = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!empty($responseData['errors'])) {
            $errorMsg = json_encode($responseData['errors']);
            throw new RuntimeException(sprintf('Neo4j returned Cypher execution errors: %s', $errorMsg));
        }

        return $responseData;
    }
}
