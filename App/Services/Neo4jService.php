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
    ) {}

    public function executeCypher(string $query, array $params = []): array
    {
        $url = rtrim($this->host, '/') . '/db/neo4j/tx/commit';

        $payloadArray = [
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => empty($params) ? (object)[] : $params,
                ]
            ]
        ];

        $payload = json_encode($payloadArray, JSON_THROW_ON_ERROR);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL for Neo4jService.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode("{$this->username}:{$this->password}")
        ];

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL error in Neo4jService: ' . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j HTTP error. HTTP code: %d. Response: %s', $httpCode, $response));
        }

        $responseData = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);

        if (!empty($responseData['errors'])) {
            $errorMsg = json_encode($responseData['errors'], JSON_THROW_ON_ERROR);
            throw new RuntimeException('Neo4j Cypher error(s): ' . $errorMsg);
        }

        return $responseData;
    }
}
