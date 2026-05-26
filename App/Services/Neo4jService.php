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
        $url = sprintf('%s/db/%s/tx/commit', rtrim($this->host, '/'), $this->database);

        $statement = [
            'statement' => $query,
            'parameters' => (object) $params, // Use object casting for empty params to serialize as {} instead of []
        ];

        $payload = json_encode(['statements' => [$statement]]);
        if ($payload === false) {
            throw new RuntimeException('Failed to JSON encode Neo4j payload.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $credentials = base64_encode($this->username . ':' . $this->password);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . $credentials,
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL execution error: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j API error. HTTP Code: %d, Response: %s', $httpCode, $response));
        }

        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Failed to decode Neo4j API response: ' . json_last_error_msg());
        }

        if (!empty($responseData['errors'])) {
            $errorMessage = $responseData['errors'][0]['message'] ?? 'Unknown error';
            throw new RuntimeException('Neo4j Cypher execution error: ' . $errorMessage);
        }

        return $responseData;
    }
}
