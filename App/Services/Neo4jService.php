<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class Neo4jService
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $username,
        public readonly string $password,
        public readonly string $database = 'neo4j'
    ) {
    }

    public function executeCypher(string $query, array $params = []): array
    {
        $url = sprintf('http://%s:%d/db/%s/tx/commit', $this->host, $this->port, urlencode($this->database));

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $payloadData = [
            'statements' => [
                [
                    'statement' => $query,
                    'parameters' => empty($params) ? (object)$params : $params
                ]
            ]
        ];

        $payload = json_encode($payloadData, JSON_THROW_ON_ERROR);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json;charset=UTF-8',
            'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password)
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException(sprintf('cURL Error: %s', $error));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Neo4j API returned non-200 HTTP status code: %d. Response: %s', $httpCode, (string)$response));
        }

        $responseData = json_decode((string)$response, true, 512, JSON_THROW_ON_ERROR);

        if (isset($responseData['errors']) && count($responseData['errors']) > 0) {
            $errorMessages = array_map(function($err) {
                return $err['message'] ?? 'Unknown error';
            }, $responseData['errors']);

            throw new RuntimeException(sprintf('Neo4j Cypher Error(s): %s', implode(' | ', $errorMessages)));
        }

        return $responseData;
    }
}
