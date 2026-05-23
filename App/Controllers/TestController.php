<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\QdrantService;
use App\Services\Neo4jService;
use Exception;

readonly class TestController
{
    public function __construct(
        private QdrantService $qdrantService,
        private Neo4jService $neo4jService,
    ) {}

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'test') {
            $qdrantPayload = $_POST['qdrant_payload'] ?? '';
            $cypherQuery = $_POST['neo4j_cypher'] ?? '';

            $qdrantResult = null;
            $qdrantError = null;
            $neo4jResult = null;
            $neo4jError = null;

            // Qdrant Test
            try {
                $dummyVector = array_fill(0, 1024, 0.5);
                $point = [
                    'id' => random_int(1, 1000000), // Using simple integer ID for testing
                    'vector' => $dummyVector,
                    'payload' => ['text' => $qdrantPayload],
                ];
                $qdrantResult = $this->qdrantService->upsertPoints('test_collection', [$point]);
            } catch (Exception $e) {
                $qdrantError = $e->getMessage();
            }

            // Neo4j Test
            try {
                $neo4jResult = $this->neo4jService->executeCypher($cypherQuery);
            } catch (Exception $e) {
                $neo4jError = $e->getMessage();
            }

            require __DIR__ . '/../Views/test_form.php';
            return;
        }

        // Default GET request renders the form
        require __DIR__ . '/../Views/test_form.php';
    }
}
