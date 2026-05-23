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
        private string $database = 'neo4j',
    ) {}

    public function executeCypher(string $query, array $params = []): array
    {
        $url = sprintf('%s/db/%s/tx/commit', rtrim($this->host, '/'), urlencode($this->database));

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => (object) $params, // Neo4j expects an object for params, even if empty
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password),
        ];

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j API error: HTTP %d - %s', $statusCode, (string)$response));
        }

        $decodedResponse = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);

        // Neo4j transaction endpoint returns 200 OK even if there are Cypher errors
        if (isset($decodedResponse['errors']) && count($decodedResponse['errors']) > 0) {
            $errorMsg = $decodedResponse['errors'][0]['message'] ?? 'Unknown Neo4j error';
            throw new RuntimeException('Neo4j Cypher error: ' . $errorMsg);
        }

        return $decodedResponse;
    }
}
