<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
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

    /**
     * Executes a Cypher query against Neo4j via its HTTP API.
     *
     * @param string $query The Cypher query to execute.
     * @param array $params The parameters for the query.
     * @return array The JSON-decoded response from Neo4j.
     * @throws RuntimeException If there is a cURL error or non-200 HTTP response.
     */
    public function executeCypher(string $query, array $params = []): array
    {
        // Default Neo4j HTTP transaction endpoint
        $url = sprintf('%s/db/%s/tx/commit', rtrim($this->host, '/'), $this->database);

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => empty($params) ? new \stdClass() : $params
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json;charset=UTF-8',
            'Authorization: Basic ' . base64_encode(sprintf('%s:%s', $this->username, $this->password)),
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL session.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL Error: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j API error (HTTP %d): %s', $httpCode, $response));
        }

        $decodedResponse = json_decode((string)$response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode Neo4j JSON response: ' . json_last_error_msg());
        }

        // Neo4j returns a 200 OK even if the statement resulted in a database error.
        // We need to check the 'errors' array in the response.
        if (isset($decodedResponse['errors']) && is_array($decodedResponse['errors']) && count($decodedResponse['errors']) > 0) {
            $errorMsg = json_encode($decodedResponse['errors']);
            throw new RuntimeException(sprintf('Neo4j Cypher Execution Error: %s', $errorMsg));
        }

        return $decodedResponse;
    }
}
