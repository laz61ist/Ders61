<?php

declare(strict_types=1);

require_once __DIR__ . '/App/Services/QdrantService.php';
require_once __DIR__ . '/App/Services/Neo4jService.php';
require_once __DIR__ . '/App/Controllers/TestController.php';
require_once __DIR__ . '/App/Controllers/IngestionController.php';

use App\Services\QdrantService;
use App\Services\Neo4jService;
use App\Controllers\TestController;
use App\Controllers\IngestionController;

// Initialize services (with default dev credentials, adapt as needed)
$qdrantService = new QdrantService('127.0.0.1:6333');
$neo4jService = new Neo4jService('http://127.0.0.1:7474', 'neo4j', 'senin_sifren'); // Replace with actual credentials

$action = $_GET['action'] ?? '';

if ($action === 'ingest' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new IngestionController($qdrantService, $neo4jService);
    $controller->fetchHuggingFaceLatex();
} else {
    $controller = new TestController($qdrantService, $neo4jService);
    $controller->handleRequest();
}
