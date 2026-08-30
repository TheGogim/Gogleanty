<?php
/**
 * fix_date_from_filename.php
 *
 * Corrige el campo date_taken en la tabla `media` cuando el nombre del archivo
 * original contiene una fecha reconocible y esa fecha difiere de date_taken.
 *
 * Navegador : http://localhost/Gogleanty/fix_date_from_filename.php
 *             ?dry_run=1   → simulación (no modifica nada)
 *             &limit=100   → procesa solo N registros
 * CLI        : php fix_date_from_filename.php [--dry-run] [--limit=N]
 */

// ─── Parámetros ──────────────────────────────────────────────────────────────
$IS_CLI  = (php_sapi_name() === 'cli');
$DRY_RUN = false;
$LIMIT   = 0;

if ($IS_CLI) {
    foreach ($argv as $arg) {
        if ($arg === '--dry-run')                       $DRY_RUN = true;
        if (preg_match('/^--limit=(\d+)$/', $arg, $m)) $LIMIT   = (int)$m[1];
    }
} else {
    if (!empty($_GET['dry_run'])) $DRY_RUN = true;
    if (!empty($_GET['limit']))   $LIMIT   = (int)$_GET['limit'];
}

// ─── Helpers HTML / CLI ───────────────────────────────────────────────────────
function isCli(): bool { return php_sapi_name() === 'cli'; }

function flush_output(): void {
    if (ob_get_level()) ob_flush();
    flush();
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ─── Cabecera HTML (solo navegador) ──────────────────────────────────────────
if (!$IS_CLI) {
    // Sin buffer de salida para streaming en tiempo real
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Accel-Buffering: no'); // desactiva buffer de nginx si aplica
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fix date_taken — Gogleanty</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #0d1117;
    --surface:  #161b22;
    --border:   #30363d;
    --text:     #e6edf3;
    --muted:    #8b949e;
    --green:    #3fb950;
    --yellow:   #d29922;
    --red:      #f85149;
    --blue:     #58a6ff;
    --purple:   #bc8cff;
    --orange:   #ffa657;
    --radius:   10px;
  }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    padding: 32px 24px 64px;
    min-height: 100vh;
  }

  h1 {
    font-size: 22px;
    font-weight: 700;
    color: var(--blue);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
  }

  .subtitle { color: var(--muted); font-size: 13px; margin-bottom: 24px; }

  /* Badges */
  .badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid;
  }
  .badge-sim  { color: var(--yellow); border-color: var(--yellow); background: #d299220f; }
  .badge-real { color: var(--green);  border-color: var(--green);  background: #3fb9500f; }

  /* Log en tiempo real */
  #log {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 18px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 12.5px;
    line-height: 1.7;
    max-height: 480px;
    overflow-y: auto;
    margin-bottom: 28px;
    scroll-behavior: smooth;
  }

  .log-ok     { color: var(--green);  }
  .log-change { color: var(--orange); }
  .log-skip   { color: var(--muted);  }
  .log-err    { color: var(--red);    }
  .log-info   { color: var(--blue);   }
  .log-bold   { font-weight: 600;     }

  /* Tarjetas de estadísticas */
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 32px;
  }

  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    text-align: center;
  }

  .stat-num {
    font-size: 32px;
    font-weight: 700;
    line-height: 1.1;
    margin-bottom: 4px;
  }

  .stat-label {
    font-size: 11px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: .5px;
  }

  /* Tabla resumen */
  h2 { font-size: 16px; font-weight: 600; margin-bottom: 14px; }

  .table-wrap {
    overflow-x: auto;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 14px;
  }

  table { width: 100%; border-collapse: collapse; }

  thead th {
    background: #1c2230;
    color: var(--muted);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 10px 14px;
    text-align: left;
    white-space: nowrap;
  }

  tbody td {
    padding: 9px 14px;
    border-top: 1px solid var(--border);
    font-size: 13px;
    vertical-align: middle;
  }

  tbody tr:hover td { background: #ffffff06; }

  .filename-cell {
    font-family: 'JetBrains Mono', monospace;
    font-size: 12px;
    color: var(--text);
    max-width: 280px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .date-cell { font-family: 'JetBrains Mono', monospace; font-size: 12px; }
  .old-date  { color: var(--red);    }
  .new-date  { color: var(--green);  }
  .null-date { color: var(--muted); font-style: italic; }

  .arrow { color: var(--muted); margin: 0 6px; }

  .tag {
    display: inline-block;
    background: #58a6ff18;
    color: var(--blue);
    border-radius: 4px;
    padding: 1px 7px;
    font-size: 11px;
    font-family: 'JetBrains Mono', monospace;
  }

  .tag-err {
    background: #f8514918;
    color: var(--red);
  }

  .tag-ok {
    background: #3fb95018;
    color: var(--green);
  }

  /* Spinner */
  @keyframes spin { to { transform: rotate(360deg); } }
  .spinner {
    display: inline-block;
    width: 12px; height: 12px;
    border: 2px solid var(--muted);
    border-top-color: var(--blue);
    border-radius: 50%;
    animation: spin .7s linear infinite;
    vertical-align: middle;
    margin-right: 6px;
  }

  #done-msg { display: none; }

  /* Botones de acción */
  .actions { margin-bottom: 24px; display: flex; gap: 10px; flex-wrap: wrap; }
  .btn {
    padding: 8px 18px;
    border-radius: 7px;
    border: 1px solid;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .btn-sim  { color: var(--yellow); border-color: var(--yellow); background: #d299220a; }
  .btn-real { color: var(--green);  border-color: var(--green);  background: #3fb9500a; }
  .btn-sim:hover  { background: #d2992220; }
  .btn-real:hover { background: #3fb95020; }
</style>
</head>
<body>

<h1>🗓️ Fix <code style="font-size:18px">date_taken</code></h1>
<p class="subtitle">
  Corrige fechas en la BD extrayéndolas del nombre original del archivo
  &nbsp;·&nbsp;
  <span class="badge <?= $DRY_RUN ? 'badge-sim' : 'badge-real' ?>">
    <?= $DRY_RUN ? '⚠ Simulación — sin cambios reales' : '✅ Modo real — aplicando cambios' ?>
  </span>
  <?php if ($LIMIT > 0): ?>
  &nbsp;<span class="badge badge-sim">Límite: <?= $LIMIT ?> registros</span>
  <?php endif; ?>
</p>

<div class="actions">
  <a class="btn btn-sim"  href="?dry_run=1<?= $LIMIT ? '&limit='.$LIMIT : '' ?>">⚡ Simular</a>
  <a class="btn btn-real" href="?<?= $LIMIT ? 'limit='.$LIMIT : '' ?>"             >🚀 Aplicar cambios</a>
  <a class="btn btn-sim"  href="?dry_run=1&limit=50">🔍 Simular (50 muestras)</a>
</div>

<p style="margin-bottom:10px;color:var(--muted)">
  <span class="spinner" id="spinner"></span>
  <span id="running-msg">Procesando…</span>
  <span id="done-msg" style="color:var(--green);font-weight:600">✅ ¡Proceso completado!</span>
</p>

<div id="log">
<?php
    flush_output();
} // end !$IS_CLI header

// ─── Conexión a la BD ────────────────────────────────────────────────────────
if (!file_exists(__DIR__ . '/.env')) {
    $msg = "❌ Archivo .env no encontrado. Ejecuta setup.php primero.";
    $IS_CLI ? print($msg."\n") : print('<span class="log-err">'.$msg.'</span>');
    exit(1);
}

$env = parse_ini_file(__DIR__ . '/.env');
$db  = new mysqli(
    $env['DB_HOST'] ?? 'localhost',
    $env['DB_USER'] ?? 'root',
    $env['DB_PASS'] ?? '',
    $env['DB_NAME'] ?? 'gogleanty_db',
    (int)($env['DB_PORT'] ?? 3306)
);

if ($db->connect_error) {
    $msg = "❌ Error de conexión: " . $db->connect_error;
    $IS_CLI ? print($msg."\n") : print('<span class="log-err">'.e($msg).'</span>');
    exit(1);
}
$db->set_charset('utf8mb4');

// ─── Patrones reconocidos ────────────────────────────────────────────────────
$PATTERNS = [
    ['name' => 'WhatsApp IMG',
     'regex' => '/^IMG-(?P<Y>\d{4})(?P<m>\d{2})(?P<d>\d{2})-WA\d+\./i'],

    ['name' => 'WhatsApp VID',
     'regex' => '/^VID-(?P<Y>\d{4})(?P<m>\d{2})(?P<d>\d{2})-WA\d+\./i'],

    ['name' => 'Screenshot_YYYYMMDD-HHmmss',
     'regex' => '/^Screenshot_(?P<Y>\d{4})(?P<m>\d{2})(?P<d>\d{2})-(?P<H>\d{2})(?P<i>\d{2})(?P<s>\d{2})\./i'],

    ['name' => 'Screenshot_YYYY-MM-DD-HH-mm-ss',
     'regex' => '/^Screenshot_(?P<Y>\d{4})-(?P<m>\d{2})-(?P<d>\d{2})-(?P<H>\d{2})-(?P<i>\d{2})-(?P<s>\d{2})\./i'],

    ['name' => 'IMG_YYYYMMDD_HHmmss',
     'regex' => '/^IMG_(?P<Y>\d{4})(?P<m>\d{2})(?P<d>\d{2})_(?P<H>\d{2})(?P<i>\d{2})(?P<s>\d{2})\./i'],

    ['name' => 'VID_YYYYMMDD_HHmmss',
     'regex' => '/^VID_(?P<Y>\d{4})(?P<m>\d{2})(?P<d>\d{2})_(?P<H>\d{2})(?P<i>\d{2})(?P<s>\d{2})\./i'],

    ['name' => 'Pixel PXL_YYYYMMDD',
     'regex' => '/^PXL_(?P<Y>\d{4})(?P<m>\d{2})(?P<d>\d{2})_(?P<H>\d{2})(?P<i>\d{2})(?P<s>\d{2})\d*\./i'],

    ['name' => 'YYYYMMDD_HHmmss',
     'regex' => '/^(?P<Y>\d{4})(?P<m>\d{2})(?P<d>\d{2})_(?P<H>\d{2})(?P<i>\d{2})(?P<s>\d{2})\./i'],

    ['name' => 'YYYY-MM-DD HH.mm.ss',
     'regex' => '/^(?P<Y>\d{4})-(?P<m>\d{2})-(?P<d>\d{2}) (?P<H>\d{2})\.(?P<i>\d{2})\.(?P<s>\d{2})\./i'],
];

// ─── Función: extrae fecha del nombre ────────────────────────────────────────
function extractDateFromFilename(string $filename, array $patterns): ?array
{
    foreach ($patterns as $pattern) {
        if (!preg_match($pattern['regex'], $filename, $matches)) continue;

        $Y = (int)$matches['Y'];
        $m = (int)$matches['m'];
        $d = (int)$matches['d'];
        $H = isset($matches['H']) ? (int)$matches['H'] : 0;
        $i = isset($matches['i']) ? (int)$matches['i'] : 0;
        $s = isset($matches['s']) ? (int)$matches['s'] : 0;

        if ($Y < 1970 || $Y > 2100)    continue;
        if (!checkdate($m, $d, $Y))     continue;
        if ($H > 23 || $i > 59 || $s > 59) continue;

        $hasTime = isset($matches['H']);

        return [
            'datetime'   => sprintf('%04d-%02d-%02d %02d:%02d:%02d', $Y, $m, $d, $H, $i, $s),
            'date_only'  => sprintf('%04d-%02d-%02d', $Y, $m, $d),
            'time_exact' => $hasTime,
            'pattern'    => $pattern['name'],
        ];
    }
    return null;
}

// ─── Helper de log ───────────────────────────────────────────────────────────
function logLine(string $type, string $msg): void
{
    if (isCli()) {
        $prefixes = [
            'ok'     => '  ✓ ',
            'change' => '  ✏  ',
            'skip'   => '  ·  ',
            'err'    => '  ✗  ',
            'info'   => '  →  ',
        ];
        echo ($prefixes[$type] ?? '  ') . $msg . "\n";
    } else {
        $cls = [
            'ok'     => 'log-ok',
            'change' => 'log-change',
            'skip'   => 'log-skip',
            'err'    => 'log-err',
            'info'   => 'log-info',
        ][$type] ?? '';
        echo '<span class="' . $cls . '">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</span>\n";
        // Scroll automático al final del log
        echo '<script>var l=document.getElementById("log");if(l)l.scrollTop=l.scrollHeight;</script>';
    }
    flush_output();
}

// ─── Consulta ────────────────────────────────────────────────────────────────
$limitSql = $LIMIT > 0 ? "LIMIT $LIMIT" : '';
$sql = "
    SELECT id, original_filename, date_taken
    FROM   media
    WHERE  original_filename REGEXP '^(IMG|VID|Screenshot|PXL|[0-9]{8})[_\\\\-]?[0-9]'
    ORDER  BY id
    $limitSql
";

$result = $db->query($sql);
if (!$result) {
    logLine('err', "Error en la consulta: " . $db->error);
    exit(1);
}

$totalRows = $result->num_rows;
logLine('info', "Registros candidatos encontrados: $totalRows");
if (!$IS_CLI) { echo "<br>\n"; flush_output(); }
else echo "\n";

// ─── Estadísticas + log de cambios ───────────────────────────────────────────
$stats = [
    'total_revisados' => 0,
    'sin_patron'      => 0,
    'ya_correcta'     => 0,
    'actualizados'    => 0,
    'errores'         => 0,
];

$changed = []; // para la tabla resumen al final
$errors  = []; // errores de UPDATE

while ($row = $result->fetch_assoc()) {
    $stats['total_revisados']++;

    $id     = (int)$row['id'];
    $fname  = $row['original_filename'];
    $dbDate = $row['date_taken'];

    $extracted = extractDateFromFilename($fname, $PATTERNS);

    // Sin patrón reconocido
    if ($extracted === null) {
        $stats['sin_patron']++;
        logLine('skip', "ID=$id  Sin patrón → $fname");
        continue;
    }

    $newDateOnly  = $extracted['date_only'];
    $newDatetime  = $extracted['datetime'];
    $patternName  = $extracted['pattern'];
    $hasExactTime = $extracted['time_exact'];

    $dbDateOnly = $dbDate ? substr($dbDate, 0, 10) : null;

    // Ya correcta
    if ($dbDateOnly === $newDateOnly) {
        $stats['ya_correcta']++;
        logLine('ok', "ID=$id  ✓ Fecha ya correcta ($newDateOnly) — $fname");
        continue;
    }

    // Calcular valor a guardar
    if ($hasExactTime) {
        $valueToDB = $newDatetime;
    } else {
        $originalTime = ($dbDate && strlen($dbDate) >= 19)
            ? substr($dbDate, 11, 8)
            : '00:00:00';
        $valueToDB = $newDateOnly . ' ' . $originalTime;
    }

    $oldDisplay = $dbDate ?? 'NULL';
    logLine('change', "ID=$id  [$fname]  $oldDisplay  →  $valueToDB  ($patternName)");

    if (!$DRY_RUN) {
        $stmt = $db->prepare("UPDATE media SET date_taken = ? WHERE id = ?");
        $stmt->bind_param('si', $valueToDB, $id);
        if ($stmt->execute()) {
            $stats['actualizados']++;
            $changed[] = [
                'id'       => $id,
                'filename' => $fname,
                'pattern'  => $patternName,
                'old'      => $oldDisplay,
                'new'      => $valueToDB,
                'ok'       => true,
            ];
        } else {
            $errMsg = $stmt->error;
            logLine('err', "  ERROR al actualizar ID=$id: $errMsg");
            $stats['errores']++;
            $errors[] = ['id' => $id, 'filename' => $fname, 'error' => $errMsg];
            $changed[] = [
                'id'       => $id,
                'filename' => $fname,
                'pattern'  => $patternName,
                'old'      => $oldDisplay,
                'new'      => $valueToDB,
                'ok'       => false,
                'err'      => $errMsg,
            ];
        }
        $stmt->close();
    } else {
        $stats['actualizados']++;
        $changed[] = [
            'id'       => $id,
            'filename' => $fname,
            'pattern'  => $patternName,
            'old'      => $oldDisplay,
            'new'      => $valueToDB,
            'ok'       => true,
        ];
    }
}

$result->free();
$db->close();

// ─── Cierre del div#log y resumen (HTML) ─────────────────────────────────────
if (!$IS_CLI) {
?>
</div><!-- /#log -->

<script>
  document.getElementById('spinner').style.display  = 'none';
  document.getElementById('running-msg').style.display = 'none';
  document.getElementById('done-msg').style.display    = 'inline';
</script>

<!-- Estadísticas -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-num" style="color:var(--blue)"><?= $stats['total_revisados'] ?></div>
    <div class="stat-label">Revisados</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--green)"><?= $stats['ya_correcta'] ?></div>
    <div class="stat-label">Ya correctos</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--orange)"><?= $stats['actualizados'] ?></div>
    <div class="stat-label"><?= $DRY_RUN ? 'Actualizaría' : 'Actualizados' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--muted)"><?= $stats['sin_patron'] ?></div>
    <div class="stat-label">Sin patrón</div>
  </div>
  <?php if ($stats['errores'] > 0): ?>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--red)"><?= $stats['errores'] ?></div>
    <div class="stat-label">Errores</div>
  </div>
  <?php endif; ?>
</div>

<?php if ($DRY_RUN): ?>
<div style="background:#d2992215;border:1px solid var(--yellow);border-radius:var(--radius);padding:14px 18px;margin-bottom:28px;color:var(--yellow);font-weight:500;">
  ⚠ <strong>Modo Simulación</strong> — ningún registro fue modificado en la base de datos.
  <a href="?" style="color:var(--green);margin-left:12px;">🚀 Aplicar cambios reales</a>
</div>
<?php endif; ?>

<?php if (count($changed) > 0): ?>
<h2>
  <?= $DRY_RUN ? '📋 Registros que se modificarían' : '📋 Registros modificados' ?>
  <span style="color:var(--muted);font-size:13px;font-weight:400;margin-left:8px">(<?= count($changed) ?>)</span>
</h2>

<div class="table-wrap">
<table>
  <thead>
    <tr>
      <th style="width:55px">ID</th>
      <th>Nombre original</th>
      <th>Patrón detectado</th>
      <th>Fecha anterior</th>
      <th></th>
      <th>Fecha nueva</th>
      <th style="width:80px">Estado</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($changed as $c): ?>
    <tr>
      <td style="color:var(--muted);font-family:'JetBrains Mono',monospace"><?= $c['id'] ?></td>
      <td class="filename-cell" title="<?= e($c['filename']) ?>"><?= e($c['filename']) ?></td>
      <td><span class="tag"><?= e($c['pattern']) ?></span></td>
      <td class="date-cell <?= $c['old'] === 'NULL' ? 'null-date' : 'old-date' ?>"><?= e($c['old']) ?></td>
      <td class="arrow">→</td>
      <td class="date-cell new-date"><?= e($c['new']) ?></td>
      <td>
        <?php if (!$c['ok']): ?>
          <span class="tag tag-err" title="<?= e($c['err'] ?? '') ?>">Error</span>
        <?php elseif ($DRY_RUN): ?>
          <span class="tag" style="color:var(--yellow);background:#d299220f;">Simulado</span>
        <?php else: ?>
          <span class="tag tag-ok">OK</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php else: ?>
<p style="color:var(--muted);padding:16px 0;">
  Ningún registro requería corrección de fecha.
</p>
<?php endif; ?>

<?php if (count($errors) > 0): ?>
<h2 style="color:var(--red);margin-top:28px">❌ Errores de actualización</h2>
<div class="table-wrap">
<table>
  <thead>
    <tr><th>ID</th><th>Nombre</th><th>Error MySQL</th></tr>
  </thead>
  <tbody>
  <?php foreach ($errors as $err): ?>
    <tr>
      <td class="date-cell"><?= $err['id'] ?></td>
      <td class="filename-cell"><?= e($err['filename']) ?></td>
      <td style="color:var(--red);font-family:'JetBrains Mono',monospace;font-size:12px"><?= e($err['error']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

</body>
</html>
<?php

} else {
    // ─── Resumen CLI ─────────────────────────────────────────────────────────
    echo "\n=== Resumen ===\n";
    echo "  Revisados          : {$stats['total_revisados']}\n";
    echo "  Sin patrón         : {$stats['sin_patron']}\n";
    echo "  Fecha ya correcta  : {$stats['ya_correcta']}\n";
    $label = $DRY_RUN ? 'Actualizaría' : 'Actualizados';
    echo "  $label           : {$stats['actualizados']}\n";
    if ($stats['errores'] > 0)
        echo "  ❌ Errores         : {$stats['errores']}\n";

    if (count($changed) > 0) {
        echo "\n--- Detalle de cambios ---\n";
        printf("%-5s %-38s %-30s %-22s %-22s %s\n",
            'ID', 'Nombre', 'Patrón', 'Anterior', 'Nueva', 'Estado');
        echo str_repeat('-', 130) . "\n";
        foreach ($changed as $c) {
            printf("%-5s %-38s %-30s %-22s %-22s %s\n",
                $c['id'],
                substr($c['filename'], 0, 37),
                $c['pattern'],
                $c['old'],
                $c['new'],
                $c['ok'] ? 'OK' : 'ERROR'
            );
        }
    }

    if ($DRY_RUN) {
        echo "\n⚠  MODO SIMULACIÓN — nada fue modificado.\n";
        echo "   Ejecuta sin --dry-run para aplicar.\n";
    }
    echo "\n";
}
