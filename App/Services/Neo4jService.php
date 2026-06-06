<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class Neo4jService
{
    public function __construct(
        private readonly string $url,
        private readonly string $username,
        private readonly string $password
    ) {
    }

    public function executeCypher(string $query, array $params = []): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        // According to memory, empty params must be cast to object so they serialize as {} rather than []
        if (empty($params)) {
            $params = (object)$params;
        }

        $endpoint = sprintf('%s/db/neo4j/tx/commit', rtrim($this->url, '/'));

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => $params,
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password)
        ];

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL error: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('HTTP error: Received status code %d. Response: %s', $httpCode, $response));
        }

        $responseData = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);

        // Explicitly check for Cypher statement errors, as neo4j may return 200 HTTP code even on error
        if (isset($responseData['errors']) && count($responseData['errors']) > 0) {
            $errorMessages = array_map(function ($error) {
                return sprintf('[%s] %s', $error['code'] ?? 'Unknown', $error['message'] ?? 'Unknown error');
            }, $responseData['errors']);

            throw new RuntimeException(sprintf('Neo4j Cypher error(s): %s', implode('; ', $errorMessages)));
        }

        return $responseData;
    }
}
