<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Services Test Interface</title>
    <style>
        body { font-family: sans-serif; margin: 20px; line-height: 1.6; }
        .container { max-width: 800px; margin: auto; }
        .card { border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], textarea { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; border: none; padding: 10px 15px; cursor: pointer; border-radius: 4px; margin-right: 10px; }
        button:hover { background-color: #0056b3; }
        pre { background-color: #f8f9fa; padding: 15px; border: 1px solid #e9ecef; border-radius: 4px; overflow-x: auto; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Database Services Test Interface</h1>

        <div class="card">
            <h2>Manual Testing</h2>
            <form method="POST" action="index.php?action=test">
                <label for="qdrant_payload">Qdrant Test Payload Text:</label>
                <input type="text" id="qdrant_payload" name="qdrant_payload" value="<?= htmlspecialchars($_POST['qdrant_payload'] ?? 'Test payload text') ?>" required>

                <label for="neo4j_cypher">Neo4j Cypher Query:</label>
                <textarea id="neo4j_cypher" name="neo4j_cypher" rows="4" required><?= htmlspecialchars($_POST['neo4j_cypher'] ?? 'MATCH (n) RETURN n LIMIT 1') ?></textarea>

                <button type="submit">Run Test</button>
            </form>
        </div>

        <div class="card">
            <h2>Hugging Face LaTeX Ingestion</h2>
            <p>Fetches real LaTeX data from Hugging Face and ingests it into Qdrant and Neo4j.</p>
            <form method="POST" action="index.php?action=ingest">
                <button type="submit">Run Ingestion Process</button>
            </form>
        </div>

        <?php if (isset($qdrantResult) || isset($qdrantError)): ?>
            <div class="card">
                <h3>Qdrant Results</h3>
                <?php if (isset($qdrantError)): ?>
                    <p class="error">Error: <?= htmlspecialchars($qdrantError) ?></p>
                <?php else: ?>
                    <pre><code><?= htmlspecialchars(json_encode($qdrantResult, JSON_PRETTY_PRINT)) ?></code></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($neo4jResult) || isset($neo4jError)): ?>
            <div class="card">
                <h3>Neo4j Results</h3>
                <?php if (isset($neo4jError)): ?>
                    <p class="error">Error: <?= htmlspecialchars($neo4jError) ?></p>
                <?php else: ?>
                    <pre><code><?= htmlspecialchars(json_encode($neo4jResult, JSON_PRETTY_PRINT)) ?></code></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($ingestionResult) || isset($ingestionError)): ?>
            <div class="card">
                <h3>Ingestion Results</h3>
                <?php if (isset($ingestionError)): ?>
                    <p class="error">Error: <?= htmlspecialchars($ingestionError) ?></p>
                <?php else: ?>
                    <pre><code><?= htmlspecialchars(json_encode($ingestionResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></pre>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>