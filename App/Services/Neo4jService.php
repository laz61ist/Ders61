<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

readonly class Neo4jService
{
    public function __construct(
        private string $host = 'http://localhost:7474',
        private string $username = 'neo4j',
        private string $password = 'password'
    ) {
    }

    /**
     * @param string $query The Cypher query to execute.
     * @param array $params The parameters for the query.
     * @return array The response from the Neo4j API.
     * @throws RuntimeException If a cURL error occurs or a non-200 HTTP response code is returned.
     */
    public function executeCypher(string $query, array $params = []): array
    {
        $url = sprintf('%s/db/neo4j/tx/commit', rtrim($this->host, '/'));

        // When sending JSON payloads to Neo4j's HTTP API, ensure empty parameter arrays are cast to objects
        // (e.g., (object)$params) so they serialize properly as empty JSON objects {} rather than JSON arrays [].
        if (empty($params)) {
            $paramsObject = new \stdClass();
        } else {
            $paramsObject = (object)$params;
        }

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => $paramsObject,
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL handle for Neo4j API.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode(sprintf('%s:%s', $this->username, $this->password)),
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL error during Neo4j executeCypher: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j API returned non-200 HTTP response code: %d. Response: %s', $httpCode, (string) $response));
        }

        $responseData = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);

        // Neo4j API returns 200 OK even if there are statement errors, so we should check for errors in the response
        if (!empty($responseData['errors'])) {
            throw new RuntimeException(sprintf('Neo4j Cypher execution errors: %s', json_encode($responseData['errors'], JSON_THROW_ON_ERROR)));
        }

        return $responseData;
    }
}
