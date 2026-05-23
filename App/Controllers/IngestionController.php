<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\QdrantService;
use App\Services\Neo4jService;
use Exception;
use RuntimeException;

readonly class IngestionController
{
    public function __construct(
        private QdrantService $qdrantService,
        private Neo4jService $neo4jService,
    ) {}

    public function processStandardChunk(array $chunkData): void
    {
        // 1. Qdrant Logic
        try {
            $id = $chunkData['id'] ?? self::generateUuid4();
            $point = [
                'id' => $id,
                'vector' => [
                    'text_vector' => $chunkData['text_vector'] ?? [],
                    'formula_vector' => $chunkData['formula_vector'] ?? [],
                ],
                'payload' => [
                    'clause_id' => $chunkData['clause_id'] ?? '',
                    'text' => $chunkData['text'] ?? '',
                    'formula' => $chunkData['formula'] ?? '',
                ]
            ];

            // For Qdrant points format: UUID or integer. Assuming $chunkData['id'] provides a valid UUID.
            if (empty($point['vector']['text_vector']) && empty($point['vector']['formula_vector'])) {
                // Adjust vectors if qdrant requires a single vector vs named vectors.
                // Using named vectors requires the Qdrant collection to be configured appropriately.
                $point['vector'] = []; // Handle case without vectors
            }

            $qdrantPayload = [$point];

            // Assuming the collection name is 'en81_50_standards'
            $this->qdrantService->upsertPoints('en81_50_standards', $qdrantPayload);

        } catch (Exception $e) {
            // In a real application, you'd use a logger. Here we rethrow or handle it.
            throw new RuntimeException('Failed to upsert to Qdrant: ' . $e->getMessage(), 0, $e);
        }

        // 2. Neo4j Logic
        try {
            $cypherQuery = <<<CYPHER
            // Create Clause Node
            MERGE (c:Clause {id: \$clause_id})
            SET c.text = \$text, c.formula = \$formula

            // Handle Parent Clause logic if parent_id exists
            FOREACH (ignoreMe IN CASE WHEN \$parent_id IS NOT NULL THEN [1] ELSE [] END |
                MERGE (p:Clause {id: \$parent_id})
                MERGE (p)-[:HAS_SUBCLAUSE]->(c)
            )

            // Handle Variable Definitions
            WITH c
            UNWIND (CASE WHEN size(\$variables) > 0 THEN \$variables ELSE [] END) AS var
            MERGE (d:Definition {id: var.id})
            SET d.name = var.name, d.description = var.description
            MERGE (c)-[:USES_VARIABLE]->(d)
            CYPHER;

            $params = [
                'clause_id' => $chunkData['clause_id'] ?? '',
                'text' => $chunkData['text'] ?? '',
                'formula' => $chunkData['formula'] ?? '',
                'parent_id' => $chunkData['parent_id'] ?? null,
                'variables' => $chunkData['variables'] ?? [],
            ];

            $this->neo4jService->executeCypher($cypherQuery, $params);

        } catch (Exception $e) {
             throw new RuntimeException('Failed to execute Cypher query: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function generateUuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
