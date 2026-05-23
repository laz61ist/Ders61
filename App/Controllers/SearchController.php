<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\QdrantService;
use App\Services\Neo4jService;
use PDO;
use Exception;
use RuntimeException;

readonly class SearchController
{
    public function __construct(
        private QdrantService $qdrantService,
        private Neo4jService $neo4jService,
        private PDO $db,
    ) {}

    public function buildContextForQuery(array $queryVectors): string
    {
        $startTimeTotal = microtime(true);
        $qdrantTime = 0.0;
        $neo4jTime = 0.0;

        $contextString = '';

        try {
            // Step 1: Query Qdrant
            $collection = 'en81_50_standards';
            $limit = 5;

            // Assuming queryVectors has 'text_vector' or 'formula_vector'. We will use 'text_vector' for primary search.
            // In a real scenario, we might do reciprocal rank fusion, but here we just search with one vector for simplicity.
            $searchVector = $queryVectors['text_vector'] ?? $queryVectors['formula_vector'] ?? [];
            $vectorName = isset($queryVectors['text_vector']) ? 'text_vector' : (isset($queryVectors['formula_vector']) ? 'formula_vector' : null);

            if (empty($searchVector)) {
                 throw new RuntimeException("No valid vector provided for search.");
            }

            $startQdrant = microtime(true);
            $qdrantResults = $this->qdrantService->searchPoints($collection, $searchVector, $vectorName, $limit);
            $qdrantTime = microtime(true) - $startQdrant;

            $points = $qdrantResults['result'] ?? [];
            if (empty($points)) {
                return "No matching context found.";
            }

            $clauseIds = [];
            $qdrantDataMap = [];
            foreach ($points as $point) {
                // Step 2: Extract clause_id
                $clauseId = $point['payload']['clause_id'] ?? null;
                if ($clauseId) {
                    $clauseIds[] = $clauseId;
                    $qdrantDataMap[$clauseId] = [
                        'text' => $point['payload']['text'] ?? '',
                        'formula' => $point['payload']['formula'] ?? '',
                        'score' => $point['score'] ?? 0,
                    ];
                }
            }

            if (empty($clauseIds)) {
                return "Matching vectors found, but missing clause_id payload.";
            }

            // Step 3: Query Neo4j
            $startNeo4j = microtime(true);
            $cypherQuery = <<<CYPHER
            UNWIND \$clauseIds AS clauseId
            MATCH (c:Clause {id: clauseId})
            OPTIONAL MATCH (p:Clause)-[:HAS_SUBCLAUSE]->(c)
            OPTIONAL MATCH (c)-[:USES_VARIABLE]->(d:Definition)
            RETURN c.id AS clause_id, p.text AS parent_text, collect({name: d.name, description: d.description}) AS definitions
            CYPHER;

            $neo4jResults = $this->neo4jService->executeCypher($cypherQuery, ['clauseIds' => $clauseIds]);
            $neo4jTime = microtime(true) - $startNeo4j;

            $neo4jDataMap = [];
            $results = $neo4jResults['results'][0]['data'] ?? [];
            foreach ($results as $row) {
                $rowMap = array_combine($neo4jResults['results'][0]['columns'], $row['row']);
                $neo4jDataMap[$rowMap['clause_id']] = [
                    'parent_text' => $rowMap['parent_text'],
                    'definitions' => $rowMap['definitions'] ?? [],
                ];
            }

            // Step 4: Combine Qdrant and Neo4j data
            foreach ($clauseIds as $clauseId) {
                $qData = $qdrantDataMap[$clauseId];
                $nData = $neo4jDataMap[$clauseId] ?? ['parent_text' => null, 'definitions' => []];

                $contextString .= "--- Clause ID: {$clauseId} ---\n";
                if (!empty($nData['parent_text'])) {
                    $contextString .= "Context (Parent): {$nData['parent_text']}\n";
                }

                if (!empty($qData['text'])) {
                     $contextString .= "Text: {$qData['text']}\n";
                }

                if (!empty($qData['formula'])) {
                    $contextString .= "Formula: {$qData['formula']}\n";
                }

                $definitions = array_filter($nData['definitions'], fn($def) => $def['name'] !== null);
                if (!empty($definitions)) {
                    $contextString .= "Variables:\n";
                    foreach ($definitions as $def) {
                        $contextString .= "- {$def['name']}: {$def['description']}\n";
                    }
                }
                $contextString .= "\n";
            }

        } catch (Exception $e) {
            $contextString = "Error building context: " . $e->getMessage();
        }

        // Step 5: Log metrics
        try {
            $totalTime = microtime(true) - $startTimeTotal;
            $stmt = $this->db->prepare(
                "INSERT INTO search_logs (qdrant_time_ms, neo4j_time_ms, total_time_ms, created_at)
                 VALUES (:qTime, :nTime, :tTime, NOW())"
            );
            $stmt->execute([
                ':qTime' => $qdrantTime * 1000,
                ':nTime' => $neo4jTime * 1000,
                ':tTime' => $totalTime * 1000,
            ]);
        } catch (Exception $e) {
             // Silently fail logging or log to file if MariaDB is unavailable
             error_log("Failed to log search metrics: " . $e->getMessage());
        }

        return trim($contextString);
    }
}
