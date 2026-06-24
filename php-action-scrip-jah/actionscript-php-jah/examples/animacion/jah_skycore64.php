<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../jah php/security/JahSalkToken.php';

use Jah\Security\JahSalkToken;

const SKY64_W = 1180;
const SKY64_H = 664;

$levels = [
    ['id' => 'Quantum Boot', 'bg' => 'quantum_blue', 'enemy_count' => 8, 'boss' => 'Gatekeeper Prime', 'boss_hp' => 140],
    ['id' => 'Neural Highway', 'bg' => 'neural_stream', 'enemy_count' => 12, 'boss' => 'Router Wraith', 'boss_hp' => 200],
    ['id' => 'Cache Nebula', 'bg' => 'cache_nebula', 'enemy_count' => 16, 'boss' => 'Cache Hydra', 'boss_hp' => 280],
    ['id' => 'GPU Cathedral', 'bg' => 'gpu_cathedral', 'enemy_count' => 22, 'boss' => 'Shader Seraph', 'boss_hp' => 360],
    ['id' => 'SALK Singularity', 'bg' => 'salk_singularity', 'enemy_count' => 28, 'boss' => 'NEXUS-NULL 64', 'boss_hp' => 520],
];

function sky64InitialState(array $levels): array
{
    return [
        'purpose' => 'skycore64_state',
        'x' => 118,
        'y' => 306,
        'energy' => 160,
        'score' => 0,
        'cores' => 0,
        'charge' => 0,
        'level' => 0,
        'turn' => 0,
        'shield' => 0,
        'boss_hp' => $levels[0]['boss_hp'],
        'message' => 'Sistema PHP puro listo. No hay JS.',
    ];
}

function sky64ReadState(array $levels): array
{
    $token = $_POST['state'] ?? '';
    if (!is_string($token) || $token === '') {
        return sky64InitialState($levels);
    }

    $verified = JahSalkToken::verify($token);
    if (($verified['ok'] ?? false) !== true || ($verified['payload']['purpose'] ?? '') !== 'skycore64_state') {
        $state = sky64InitialState($levels);
        $state['message'] = 'Estado rechazado por SALK. Reiniciado limpio.';
        return $state;
    }

    return array_replace(sky64InitialState($levels), $verified['payload']);
}

function sky64Clamp(int $value, int $min, int $max): int
{
    return max($min, min($max, $value));
}

function sky64Enemy(int $index, array $state, array $level): array
{
    $speed = 19 + (($index + $state['level']) % 9);
    $x = 1120 - (($state['turn'] * $speed + $index * 81) % 760);
    $y = 104 + (($index * 73 + $state['turn'] * (7 + $state['level'])) % 430);

    return ['x' => $x, 'y' => $y, 'hp' => 20 + $state['level'] * 7, 'id' => 'e' . $index];
}

function sky64Enemies(array $state, array $level): array
{
    $enemies = [];
    for ($i = 0; $i < $level['enemy_count']; $i++) {
        $enemies[] = sky64Enemy($i, $state, $level);
    }

    return $enemies;
}

function sky64ApplyAction(array $state, array $levels): array
{
    $action = $_POST['action'] ?? 'wait';
    if (!is_string($action)) {
        $action = 'wait';
    }

    if ($action === 'reset') {
        return sky64InitialState($levels);
    }

    $level = $levels[(int) $state['level']];
    $state['turn'] = (int) $state['turn'] + 1;
    $state['shield'] = max(0, (int) $state['shield'] - 1);
    $state['message'] = 'PHP recalculo el frame #' . $state['turn'] . '.';

    $speed = $state['shield'] > 0 ? 66 : 46;
    if ($action === 'up') {
        $state['y'] = sky64Clamp((int) $state['y'] - $speed, 78, SKY64_H - 88);
    } elseif ($action === 'down') {
        $state['y'] = sky64Clamp((int) $state['y'] + $speed, 78, SKY64_H - 88);
    } elseif ($action === 'left') {
        $state['x'] = sky64Clamp((int) $state['x'] - $speed, 22, SKY64_W - 130);
    } elseif ($action === 'right') {
        $state['x'] = sky64Clamp((int) $state['x'] + $speed, 22, SKY64_W - 130);
    } elseif ($action === 'shield') {
        if ((int) $state['cores'] > 0) {
            $state['cores'] = (int) $state['cores'] - 1;
            $state['shield'] = 3;
            $state['message'] = 'Escudo efimero SALK activo por 3 frames.';
        } else {
            $state['message'] = 'Sin cores SALK para escudo.';
        }
    } elseif ($action === 'shoot' || $action === 'laser') {
        $hit = false;
        foreach (sky64Enemies($state, $level) as $enemy) {
            if ($enemy['x'] > (int) $state['x'] && abs($enemy['y'] - (int) $state['y']) < ($action === 'laser' ? 92 : 52)) {
                $state['score'] = (int) $state['score'] + ($action === 'laser' ? 240 : 90);
                $state['charge'] = $action === 'laser' ? 0 : min(100, (int) $state['charge'] + 18);
                $state['message'] = $action === 'laser' ? 'Laser cuantico firmado impacto un nodo.' : 'Pulso PHP impacto un nodo.';
                $hit = true;
                break;
            }
        }

        if (!$hit) {
            $damage = $action === 'laser' && (int) $state['charge'] >= 100 ? 80 : 28;
            $state['boss_hp'] = max(0, (int) $state['boss_hp'] - $damage);
            $state['charge'] = $action === 'laser' ? 0 : min(100, (int) $state['charge'] + 12);
            $state['message'] = 'Ataque directo al jefe: -' . $damage . ' HP.';
        }
    }

    foreach (sky64Enemies($state, $level) as $enemy) {
        if (abs($enemy['x'] - (int) $state['x']) < 62 && abs($enemy['y'] - (int) $state['y']) < 50) {
            $damage = $state['shield'] > 0 ? 3 : 14;
            $state['energy'] = max(0, (int) $state['energy'] - $damage);
            $state['message'] = 'Colision procesada en PHP: -' . $damage . ' energia.';
            break;
        }
    }

    if ((int) $state['turn'] % 5 === 0) {
        $state['cores'] = (int) $state['cores'] + 1;
        $state['message'] = 'Core SALK recogido por ciclo firmado.';
    }

    if ((int) $state['boss_hp'] <= 0) {
        if ((int) $state['level'] < count($levels) - 1) {
            $state['level'] = (int) $state['level'] + 1;
            $next = $levels[(int) $state['level']];
            $state['boss_hp'] = $next['boss_hp'];
            $state['score'] = (int) $state['score'] + 1000;
            $state['message'] = 'Nivel liberado. Entrando a ' . $next['id'] . '.';
        } else {
            $state['message'] = 'NEXUS-NULL 64 destruido por PHP puro.';
        }
    }

    if ((int) $state['energy'] <= 0) {
        $state['message'] = 'SALK-01 quedo sin energia. Reinicia o conserva el estado firmado.';
    }

    return $state;
}

$state = sky64ApplyAction(sky64ReadState($levels), $levels);
$level = $levels[(int) $state['level']];
$enemies = sky64Enemies($state, $level);
$stateToken = JahSalkToken::make($state, 600);
$bossPercent = (int) round(((int) $state['boss_hp'] / $level['boss_hp']) * 100);
$energyPercent = (int) round(((int) $state['energy'] / 160) * 100);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JAH SkyCore 64 PHP Puro</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: radial-gradient(circle at 50% 18%, #164e63, #020617 58%); color: #f8fafc; font-family: system-ui, sans-serif; }
        .game64 { width: min(1180px, 100vw); min-height: 664px; position: relative; overflow: hidden; border: 1px solid rgba(125,211,252,.45); background: #020617; box-shadow: 0 30px 140px rgba(59,130,246,.24); }
        .game64[data-bg="salk_singularity"] { background: radial-gradient(circle at 78% 25%, rgba(239,68,68,.34), transparent 34%), #140409; }
        .game64[data-bg="gpu_cathedral"] { background: radial-gradient(circle at 72% 22%, rgba(168,85,247,.34), transparent 34%), #09051a; }
        .game64[data-bg="cache_nebula"] { background: radial-gradient(circle at 35% 65%, rgba(34,197,94,.24), transparent 36%), #04130d; }
        .game64[data-bg="neural_stream"] { background: radial-gradient(circle at 62% 30%, rgba(14,165,233,.30), transparent 34%), #06111f; }
        .grid { position: absolute; inset: 66px 0 0; background-image: linear-gradient(rgba(125,211,252,.09) 1px, transparent 1px), linear-gradient(90deg, rgba(125,211,252,.09) 1px, transparent 1px); background-size: 44px 44px; animation: grid64 6s linear infinite; transform: perspective(780px) rotateX(58deg) translateY(170px); transform-origin: bottom; }
        .hud { height: 66px; display: flex; align-items: center; gap: 18px; padding: 0 22px; background: rgba(2,6,23,.80); position: relative; z-index: 10; }
        .hud strong { color: #7dd3fc; text-shadow: 0 0 18px #38bdf8; margin-right: auto; font-size: 20px; }
        .meter { width: 130px; height: 9px; border: 1px solid rgba(226,232,240,.25); background: rgba(15,23,42,.88); }
        .meter span { display: block; height: 100%; background: linear-gradient(90deg, #22c55e, #7dd3fc); }
        .world { position: absolute; inset: 66px 0 92px; }
        .player { position: absolute; left: <?= (int) $state['x'] ?>px; top: <?= (int) $state['y'] ?>px; width: 86px; height: 52px; clip-path: polygon(0 50%, 25% 6%, 82% 0, 100% 50%, 82% 100%, 25% 94%); background: linear-gradient(135deg, #e0f2fe, #38bdf8 30%, #2563eb 52%, #22c55e); box-shadow: 0 0 26px #38bdf8, inset -12px 0 0 rgba(255,255,255,.25); animation: hover64 1.1s ease-in-out infinite alternate; }
        .player::after { content: ""; position: absolute; left: -34px; top: 18px; width: 42px; height: 14px; background: linear-gradient(90deg, transparent, #22c55e, #7dd3fc); filter: blur(1px); }
        .player.shield { outline: 2px solid #22c55e; box-shadow: 0 0 38px #22c55e; }
        .enemy { position: absolute; width: 58px; height: 44px; clip-path: polygon(0 15%, 82% 0, 100% 50%, 82% 100%, 0 85%, 28% 50%); background: linear-gradient(135deg, #fb7185, #991b1b 58%, #f97316); box-shadow: 0 0 18px rgba(248,113,113,.78); animation: enemyPulse .8s ease-in-out infinite alternate; }
        .boss { position: absolute; right: 54px; top: 222px; display: grid; place-items: center; width: 198px; height: 118px; border: 1px solid #f87171; background: linear-gradient(135deg, rgba(69,10,10,.95), rgba(153,27,27,.92)); color: #fecaca; font-weight: 900; text-align: center; box-shadow: 0 0 42px rgba(239,68,68,.72); animation: bossBreath 1.4s ease-in-out infinite alternate; }
        .shot { position: absolute; left: <?= (int) $state['x'] + 84 ?>px; top: <?= (int) $state['y'] + 24 ?>px; width: 280px; height: 7px; border-radius: 99px; background: #facc15; box-shadow: 0 0 18px #facc15; animation: shot64 .34s linear forwards; }
        .core { position: absolute; left: <?= 470 + (((int) $state['turn'] * 67) % 420) ?>px; top: <?= 126 + (((int) $state['turn'] * 43) % 320) ?>px; width: 30px; height: 30px; border-radius: 10px; background: conic-gradient(#22c55e, #7dd3fc, #facc15, #22c55e); box-shadow: 0 0 26px rgba(34,197,94,.8); animation: coreSpin 1.5s linear infinite; }
        .controls { position: absolute; left: 0; right: 0; bottom: 0; min-height: 92px; display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: center; padding: 14px 22px; background: rgba(2,6,23,.86); z-index: 15; }
        .pad { display: grid; grid-template-columns: repeat(3, 54px); gap: 8px; width: max-content; }
        .actions { display: flex; gap: 10px; justify-content: end; flex-wrap: wrap; }
        button { min-width: 54px; min-height: 42px; border: 1px solid rgba(125,211,252,.45); background: linear-gradient(135deg, #172033, #0f172a); color: #f8fafc; font-weight: 900; cursor: pointer; box-shadow: inset 0 0 0 1px rgba(255,255,255,.04); }
        button:hover { border-color: #7dd3fc; box-shadow: 0 0 20px rgba(56,189,248,.35); }
        .message { text-align: center; color: #cbd5e1; font-size: 14px; }
        .empty { visibility: hidden; }
        @keyframes grid64 { from { background-position: 0 0; } to { background-position: -176px 0; } }
        @keyframes hover64 { from { transform: translateY(-4px); } to { transform: translateY(4px); } }
        @keyframes enemyPulse { from { filter: brightness(.88); } to { filter: brightness(1.35); } }
        @keyframes bossBreath { from { transform: scale(.985); } to { transform: scale(1.025); } }
        @keyframes shot64 { from { transform: translateX(0); opacity: 1; } to { transform: translateX(500px); opacity: .12; } }
        @keyframes coreSpin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <main class="game64" data-bg="<?= htmlspecialchars($level['bg'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="grid"></div>
        <section class="hud">
            <strong>JAH SkyCore 64 PHP Puro</strong>
            <span>Nivel <?= (int) $state['level'] + 1 ?>: <?= htmlspecialchars($level['id'], ENT_QUOTES, 'UTF-8') ?></span>
            <span>Score <?= (int) $state['score'] ?></span>
            <span>Cores <?= (int) $state['cores'] ?></span>
            <span>Carga <?= (int) $state['charge'] ?>%</span>
            <span class="meter" title="Energia"><span style="width: <?= $energyPercent ?>%"></span></span>
            <span class="meter" title="Jefe"><span style="width: <?= $bossPercent ?>%; background: linear-gradient(90deg, #ef4444, #facc15);"></span></span>
        </section>

        <section class="world">
            <div class="player<?= (int) $state['shield'] > 0 ? ' shield' : '' ?>"></div>
            <?php if (($_POST['action'] ?? '') === 'shoot' || ($_POST['action'] ?? '') === 'laser') : ?>
                <div class="shot"></div>
            <?php endif; ?>
            <div class="core"></div>
            <?php foreach ($enemies as $enemy) : ?>
                <div class="enemy" style="left: <?= $enemy['x'] ?>px; top: <?= $enemy['y'] ?>px;"></div>
            <?php endforeach; ?>
            <div class="boss"><?= htmlspecialchars($level['boss'], ENT_QUOTES, 'UTF-8') ?><br>HP <?= (int) $state['boss_hp'] ?></div>
        </section>

        <form method="post" class="controls">
            <input type="hidden" name="state" value="<?= htmlspecialchars($stateToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="pad">
                <button class="empty" type="submit" name="action" value="wait">.</button>
                <button type="submit" name="action" value="up">UP</button>
                <button class="empty" type="submit" name="action" value="wait">.</button>
                <button type="submit" name="action" value="left">LEFT</button>
                <button type="submit" name="action" value="wait">WAIT</button>
                <button type="submit" name="action" value="right">RIGHT</button>
                <button class="empty" type="submit" name="action" value="wait">.</button>
                <button type="submit" name="action" value="down">DOWN</button>
                <button class="empty" type="submit" name="action" value="wait">.</button>
            </div>
            <p class="message"><?= htmlspecialchars((string) $state['message'], ENT_QUOTES, 'UTF-8') ?><br>Estado firmado por SALK en cada turno.</p>
            <div class="actions">
                <button type="submit" name="action" value="shoot">DISPARAR</button>
                <button type="submit" name="action" value="laser">LASER</button>
                <button type="submit" name="action" value="shield">ESCUDO</button>
                <button type="submit" name="action" value="reset">RESET</button>
            </div>
        </form>
    </main>
</body>
</html>
