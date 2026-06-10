<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class Neo4jService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly string $database = 'neo4j'
    ) {
    }

    public function executeCypher(string $query, array $params = []): array
    {
        // Ensure empty parameter arrays are cast to objects to serialize as {}
        $parameters = empty($params) ? (object) [] : $params;

        $url = sprintf('%s/db/%s/tx/commit', rtrim($this->baseUrl, '/'), $this->database);

        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => $parameters
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password)
            ],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(sprintf('cURL Error: %s', $error));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf('HTTP Error %d: %s', $statusCode, (string) $response));
        }

        $responseData = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);

        // Explicitly check the response body for Cypher statement errors
        if (!empty($responseData['errors'])) {
            $errorMessages = array_map(function($err) {
                return sprintf('[%s] %s', $err['code'] ?? 'Unknown', $err['message'] ?? 'Unknown error');
            }, $responseData['errors']);

            throw new RuntimeException(sprintf('Neo4j Cypher Error(s): %s', implode('; ', $errorMessages)));
        }

        return $responseData;
    }
}
