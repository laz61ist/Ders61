<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class Neo4jService
{
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 7474,
        private readonly string $user = 'neo4j',
        private readonly string $password = 'password',
        private readonly string $database = 'neo4j'
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
        $url = sprintf('http://%s:%d/db/%s/tx/commit', $this->host, $this->port, $this->database);

        $payload = json_encode([
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => (object) $params // Cast to object to ensure JSON object even if empty
                ]
            ]
        ], JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($this->user . ':' . $this->password)
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(sprintf('cURL error during Neo4j request: %s', $error));
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j API returned non-success HTTP status code: %d. Response: %s', $httpCode, $response));
        }

        $decodedResponse = json_decode((string) $response, true, 512, JSON_THROW_ON_ERROR);

        if (isset($decodedResponse['errors']) && count($decodedResponse['errors']) > 0) {
            throw new RuntimeException(sprintf('Neo4j API returned errors: %s', json_encode($decodedResponse['errors'])));
        }

        return $decodedResponse;
    }
}
