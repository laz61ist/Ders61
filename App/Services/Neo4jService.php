<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use JsonException;

class Neo4jService
{
    public function __construct(
        public readonly string $url,
        public readonly string $username,
        public readonly string $password,
        public readonly string $database = 'neo4j'
    ) {
    }

    /**
     * @param string $query
     * @param array $params
     * @return array
     * @throws RuntimeException
     */
    public function executeCypher(string $query, array $params = []): array
    {
        // For Neo4j version >= 4.0 using the transactional endpoint
        $endpoint = sprintf('%s/db/%s/tx/commit', rtrim($this->url, '/'), $this->database);

        $payloadArray = [
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => (object)$params,
                ],
            ],
        ];

        try {
            $payload = json_encode($payloadArray, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to JSON encode Neo4j statement: ' . $e->getMessage(), 0, $e);
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL session.');
        }

        $auth = base64_encode($this->username . ':' . $this->password);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . $auth,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('cURL error: ' . $error);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j API error (Status: %d): %s', $statusCode, (string)$response));
        }

        try {
            $decodedResponse = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Failed to decode Neo4j JSON response: ' . $e->getMessage(), 0, $e);
        }

        // Neo4j transactional endpoint returns a 200 OK even if there are Cypher errors,
        // but populates the 'errors' array.
        if (isset($decodedResponse['errors']) && count($decodedResponse['errors']) > 0) {
            $errorMsg = json_encode($decodedResponse['errors']);
            throw new RuntimeException('Neo4j Cypher error: ' . $errorMsg);
        }

        return $decodedResponse;
    }
}
