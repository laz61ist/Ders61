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
        private readonly int $port = 7474,
        private readonly string $scheme = 'http'
    ) {
    }

    /**
     * Execute a Cypher query using the Neo4j HTTP API.
     *
     * @param string $query The Cypher query to execute.
     * @param array $params The parameters for the query.
     * @return array The decoded JSON response.
     * @throws RuntimeException If there is a cURL error, non-200 HTTP response, or query error.
     */
    public function executeCypher(string $query, array $params = []): array
    {
        $url = sprintf('%s://%s:%d/db/neo4j/tx/commit', $this->scheme, $this->host, $this->port);

        // Ensure empty parameter arrays are cast to objects for proper JSON encoding `{}`
        if (empty($params)) {
            $params = (object) $params;
        }

        $payloadData = [
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => $params
                ]
            ]
        ];

        $payload = json_encode($payloadData);
        if ($payload === false) {
            throw new RuntimeException('Failed to JSON encode Cypher payload.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password)
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL error: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j HTTP error: %d. Response: %s', $httpCode, $response));
        }

        $decodedResponse = json_decode((string) $response, true);
        if (!is_array($decodedResponse)) {
            throw new RuntimeException('Failed to decode Neo4j JSON response.');
        }

        if (isset($decodedResponse['errors']) && count($decodedResponse['errors']) > 0) {
            $errorMessage = json_encode($decodedResponse['errors']);
            throw new RuntimeException(sprintf('Neo4j Query error: %s', $errorMessage));
        }

        return $decodedResponse;
    }
}
