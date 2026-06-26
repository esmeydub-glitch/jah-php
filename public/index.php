<?php

declare(strict_types=1);

$boot = require dirname(__DIR__) . '/app/bootstrap.php';
$config = $boot['config'];

use Jah\Memory\TieredMemory;

$storagePath = (string)$config['paths']['datacore_storage'];
$hotStoragePath = (string)$config['paths']['hot_storage'];
$tiered = new TieredMemory($storagePath, $hotStoragePath);
require_once dirname(__DIR__) . '/app/actions/MemoryActionScript.php';
$runtime = new MemoryActionScript($tiered, $config);

$action = $_REQUEST['action'] ?? 'chat';
$collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($_REQUEST['collection'] ?? 'memories')) ?: 'memories';
$id = trim((string)($_REQUEST['id'] ?? ''));
$content = trim((string)($_REQUEST['content'] ?? ''));
$message = trim((string)($_REQUEST['message'] ?? ''));
$query = trim((string)($_REQUEST['query'] ?? ''));
$tier = in_array(($_REQUEST['tier'] ?? 'hot'), ['hot', 'warm', 'cold'], true) ? (string)$_REQUEST['tier'] : 'hot';

$feedback = '';
$response = '';
$searchResults = [];
$statsData = null;
$contextPreview = '';
$memoryResults = [];
$actionsTrace = [];

switch ($action) {
    case 'save':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($id !== '' && $content !== '') {
                $tags = array_values(array_filter(array_map('trim', explode(',', (string)($_REQUEST['tags'] ?? '')))));
                $result = $runtime->save($id, ['id' => $id, 'content' => $content, 'tags' => $tags], $tier);
                $feedback = ($result['success'] ?? false) ? "Guardado en tier [{$tier}]: {$id}" : ('Error: ' . ($result['error'] ?? 'no guardado'));
            } else {
                $feedback = 'Error: se requieren id y content';
            }
        }
        break;

    case 'search':
        if ($query !== '') {
            $result = $runtime->search($query, $collection, 30);
            $searchResults = $result['result']['memories'] ?? [];
        }
        break;

    case 'delete':
        if ($id !== '') {
            $result = $runtime->delete($id, $collection);
            $feedback = ($result['success'] ?? false) ? "Olvidado / Forgotten: {$id}" : ('Error: ' . ($result['error'] ?? 'no eliminado'));
            $action = 'search';
        }
        break;

    case 'migrate':
        $result = $runtime->migrate();
        $feedback = 'Migración ejecutada: ' . json_encode($result['result'] ?? [], JSON_UNESCAPED_UNICODE);
        $action = 'stats';
        // no break

    case 'stats':
        $result = $runtime->stats();
        $statsData = $result['result'] ?? [];
        break;

    case 'chat':
    default:
        $action = 'chat';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $message !== '') {
            $result = $runtime->runAgent($message, $collection, (string)($config['qwen']['model'] ?? 'qwen-max'));
            $response = (string)($result['response'] ?? '');
            $contextPreview = (string)($result['context_preview'] ?? '');
            $memoryResults = is_array($result['memories'] ?? null) ? $result['memories'] : [];
            $actionsTrace = is_array($result['actions_trace'] ?? null) ? $result['actions_trace'] : [];
        }
        break;
}

$tiered->close();

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function brief(mixed $value, int $length = 220): string {
    $text = is_string($value) ? $value : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
    return substr($text, 0, $length);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>JAH MemoryAgent — Qwen Cloud</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Courier New', monospace; background: #0f0f23; color: #e0e0e0; max-width: 1000px; margin: 0 auto; padding: 20px; }
        h1 { color: #00ff88; margin-bottom: 5px; }
        h2 { color: #00ff88; margin: 18px 0 10px; font-size: 1.1em; }
        .subtitle { color: #aaa; margin-bottom: 16px; font-size: 0.9em; }
        .runtime { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin-bottom: 20px; }
        .chip { border: 1px solid #00ff88; border-radius: 6px; padding: 8px; background: #111936; color: #00ff88; font-size: 0.85em; }
        .nav { margin-bottom: 20px; }
        .nav a { color: #00ff88; margin: 0 8px 8px 0; display: inline-block; text-decoration: none; padding: 6px 10px; border: 1px solid #00ff88; border-radius: 3px; }
        .nav a:hover, .nav a.active { background: #00ff88; color: #0f0f23; }
        form { background: #1a1a3e; padding: 20px; border-radius: 8px; margin-bottom: 15px; }
        label { display: block; margin: 10px 0 5px; color: #aaa; }
        input, textarea, select { width: 100%; padding: 10px; background: #0f0f23; border: 1px solid #333; color: #e0e0e0; border-radius: 4px; font-family: inherit; }
        button { background: #00ff88; color: #0f0f23; border: none; padding: 12px 24px; cursor: pointer; border-radius: 4px; font-weight: bold; margin-top: 10px; font-family: inherit; }
        .response { background: #1a1a3e; padding: 20px; border-radius: 8px; border-left: 4px solid #00ff88; white-space: pre-wrap; margin-top: 15px; }
        .item { background: #1a1a3e; padding: 15px; margin: 8px 0; border-radius: 5px; border-left: 3px solid #555; }
        .item.hot { border-left-color: #ff6b6b; }
        .item.warm { border-left-color: #feca57; }
        .item.cold { border-left-color: #48dbfb; }
        .meta { color: #888; font-size: 0.85em; margin-top: 5px; }
        .error { color: #ff6666; }
        .success { color: #00ff88; }
        .info { color: #48dbfb; }
        pre { white-space: pre-wrap; background: #0f0f23; padding: 12px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>JAH MemoryAgent</h1>
    <p class="subtitle">PHP puro + ActionScript PHP + DataCoreTurbo + MemoryPyramid + Qwen Cloud por cURL nativo</p>

    <div class="runtime">
        <div class="chip">ActionScript PHP: ACTIVE</div>
        <div class="chip">Qwen Cloud: cURL native</div>
        <div class="chip">DataCoreTurbo: binary memory</div>
        <div class="chip">MemoryPyramid: Hot / Warm / Cold</div>
    </div>

    <div class="nav">
        <a href="?action=chat" class="<?= $action === 'chat' ? 'active' : '' ?>">Chat</a>
        <a href="?action=save" class="<?= $action === 'save' ? 'active' : '' ?>">Guardar / Save</a>
        <a href="?action=search" class="<?= $action === 'search' ? 'active' : '' ?>">Buscar / Search</a>
        <a href="?action=stats" class="<?= $action === 'stats' ? 'active' : '' ?>">Estadísticas / Stats</a>
        <a href="?action=migrate">Migrar tiers / Migrate</a>
    </div>

    <?php if ($feedback !== ''): ?>
    <p class="<?= str_starts_with($feedback, 'Error') ? 'error' : 'success' ?>"><?= e($feedback) ?></p>
    <?php endif; ?>

    <?php if ($action === 'chat'): ?>
    <form method="POST" action="">
        <input type="hidden" name="action" value="chat">
        <label>Pregunta / Question:</label>
        <textarea name="message" rows="3" placeholder="Escribe tu mensaje... / Type your message..." required><?= e($message) ?></textarea>
        <button type="submit">Ejecutar MemoryAgent / Run MemoryAgent</button>
    </form>

    <?php if ($response !== ''): ?>
    <h2>Respuesta Qwen / Qwen response</h2>
    <div class="response"><?= nl2br(e($response)) ?></div>

    <h2>Memorias recuperadas / Retrieved memories (<?= count($memoryResults) ?>)</h2>
    <?php if ($memoryResults === []): ?>
        <p class="info">No se recuperó memoria previa para esta pregunta.</p>
    <?php else: foreach ($memoryResults as $item): $tierClass = $item['_memory_tier'] ?? $item['_tier'] ?? 'hot'; ?>
        <div class="item <?= e($tierClass) ?>">
            <strong>ID:</strong> <?= e($item['id'] ?? 'N/A') ?>
            | <strong>Tier:</strong> <?= e($tierClass) ?><br>
            <?= e(brief($item['content'] ?? $item)) ?>
        </div>
    <?php endforeach; endif; ?>

    <h2>Contexto enviado a Qwen / Context sent to Qwen</h2>
    <pre><?= e($contextPreview) ?></pre>

    <h2>ActionScript PHP trace</h2>
    <pre><?= e(json_encode($actionsTrace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    <?php endif; ?>

    <?php elseif ($action === 'save'): ?>
    <form method="POST" action="">
        <input type="hidden" name="action" value="save">
        <label>ID:</label>
        <input type="text" name="id" placeholder="identificador-unico" required>
        <label>Contenido / Content:</label>
        <textarea name="content" rows="4" placeholder="Información a guardar... / Information to save..." required></textarea>
        <label>Tags:</label>
        <input type="text" name="tags" placeholder="preference, project, memory">
        <label>Tier de memoria / Memory tier:</label>
        <select name="tier">
            <option value="hot">Hot / Caliente</option>
            <option value="warm">Warm / Tibia</option>
            <option value="cold">Cold / Fría</option>
        </select>
        <button type="submit">Guardar con ActionScript PHP / Save</button>
    </form>

    <?php elseif ($action === 'search'): ?>
    <form method="GET" action="">
        <input type="hidden" name="action" value="search">
        <label>Buscar / Search:</label>
        <input type="text" name="query" value="<?= e($query) ?>" placeholder="Términos de búsqueda... / Search terms..." required>
        <button type="submit">Buscar / Search</button>
    </form>

    <?php if ($searchResults !== []): ?>
    <h2>Resultados / Results (<?= count($searchResults) ?>)</h2>
    <?php foreach ($searchResults as $item): $tierClass = $item['_memory_tier'] ?? $item['_tier'] ?? 'hot'; ?>
    <div class="item <?= e($tierClass) ?>">
        <strong>ID:</strong> <?= e($item['id'] ?? 'N/A') ?>
        | <strong>Rol:</strong> <?= e($item['role'] ?? 'memory') ?>
        | <strong>Tier:</strong> <?= e($tierClass) ?><br>
        <?= e(brief($item['content'] ?? $item)) ?>
        <div class="meta">
            <?= e(date('Y-m-d H:i', (int)($item['_ts'] ?? time()))) ?>
            <?php if (isset($item['id'])): ?>
            | <a href="?action=delete&id=<?= urlencode((string)$item['id']) ?>" class="error">Olvidar / Forget</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php elseif ($query !== ''): ?>
    <p class="info">No se encontraron resultados / No results found</p>
    <?php endif; ?>

    <?php else: ?>
    <h2>Estadísticas / Statistics</h2>
    <div class="item"><pre><?= e(json_encode($statsData ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></div>
    <?php endif; ?>
</body>
</html>
