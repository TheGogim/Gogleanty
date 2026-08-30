<?php
/**
 * backfill_hashes.php
 *
 * Genera hashes MD5 del contenido físico de cada archivo en el servidor
 * y los guarda en el campo device_file_hash de la tabla media.
 *
 * Navegador : http://fotosanty.ip-ddns.com/backfill_hashes.php
 *   ?dry_run=1          → solo muestra qué haría, sin modificar nada
 *   &clear=1            → borra todos los hashes existentes antes de empezar
 *   &limit=100          → procesa solo N registros
 *   &offset=0           → empieza desde el registro N (para paginación manual)
 *   &only_missing=1     → procesa solo los que tienen device_file_hash vacío (default)
 *   &all=1              → procesa TODOS aunque ya tengan hash
 * CLI: php backfill_hashes.php [--dry-run] [--clear] [--limit=N] [--offset=N] [--all]
 */

set_time_limit(0);          // Sin límite de tiempo
ini_set('memory_limit', '256M');

// ─── Parámetros ───────────────────────────────────────────────────────────────
$IS_CLI  = (php_sapi_name() === 'cli');
$DRY_RUN      = false;
$CLEAR_HASHES = false;
$LIMIT        = 0;
$OFFSET       = 0;
$FORCE_ALL    = false;   // true = reprocesa aunque ya tenga hash

if ($IS_CLI) {
    foreach ($argv as $arg) {
        if ($arg === '--dry-run')                        $DRY_RUN      = true;
        if ($arg === '--clear')                          $CLEAR_HASHES = true;
        if ($arg === '--all')                            $FORCE_ALL    = true;
        if (preg_match('/^--limit=(\d+)$/',  $arg, $m)) $LIMIT        = (int)$m[1];
        if (preg_match('/^--offset=(\d+)$/', $arg, $m)) $OFFSET       = (int)$m[1];
    }
} else {
    if (!empty($_GET['dry_run'])) $DRY_RUN      = true;
    if (!empty($_GET['clear']))   $CLEAR_HASHES = true;
    if (!empty($_GET['all']))     $FORCE_ALL    = true;
    if (!empty($_GET['limit']))   $LIMIT        = (int)$_GET['limit'];
    if (!empty($_GET['offset']))  $OFFSET       = (int)$_GET['offset'];
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function isCli(): bool { return php_sapi_name() === 'cli'; }
function flush_out(): void { if (ob_get_level()) ob_flush(); flush(); }
function he(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ─── HTML header ──────────────────────────────────────────────────────────────
if (!$IS_CLI) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Accel-Buffering: no');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Backfill Hashes — Gogleanty</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg:#0d1117; --surface:#161b22; --border:#30363d;
    --text:#e6edf3; --muted:#8b949e;
    --green:#3fb950; --yellow:#d29922; --red:#f85149;
    --blue:#58a6ff; --orange:#ffa657; --purple:#bc8cff;
    --radius:10px;
  }
  body { background:var(--bg); color:var(--text); font-family:'Inter',sans-serif;
         font-size:14px; padding:32px 24px 64px; min-height:100vh; }
  h1 { font-size:22px; font-weight:700; color:var(--blue); margin-bottom:6px; }
  h2 { font-size:16px; font-weight:600; margin-bottom:14px; }
  .subtitle { color:var(--muted); font-size:13px; margin-bottom:20px; }
  .badge {
    display:inline-flex; align-items:center; gap:5px;
    padding:3px 10px; border-radius:20px; font-size:12px;
    font-weight:600; border:1px solid;
  }
  .badge-sim  { color:var(--yellow); border-color:var(--yellow); background:#d299220f; }
  .badge-real { color:var(--green);  border-color:var(--green);  background:#3fb9500f; }
  .badge-warn { color:var(--red);    border-color:var(--red);    background:#f851490f; }

  /* Botones de acción */
  .actions { margin-bottom:24px; display:flex; gap:10px; flex-wrap:wrap; }
  .btn {
    padding:8px 16px; border-radius:7px; border:1px solid;
    font-family:'Inter',sans-serif; font-size:13px; font-weight:600;
    cursor:pointer; text-decoration:none;
    display:inline-flex; align-items:center; gap:6px;
  }
  .btn-sim    { color:var(--yellow); border-color:var(--yellow); background:#d299220a; }
  .btn-real   { color:var(--green);  border-color:var(--green);  background:#3fb9500a; }
  .btn-danger { color:var(--red);    border-color:var(--red);    background:#f851490a; }
  .btn-muted  { color:var(--muted);  border-color:var(--border); background:#ffffff05; }
  .btn-sim:hover    { background:#d2992220; }
  .btn-real:hover   { background:#3fb95020; }
  .btn-danger:hover { background:#f8514920; }
  .btn-muted:hover  { background:#ffffff10; }

  /* Alerta */
  .alert {
    padding:14px 18px; border-radius:var(--radius);
    margin-bottom:20px; font-size:13px; font-weight:500;
  }
  .alert-warn { background:#d2992215; border:1px solid var(--yellow); color:var(--yellow); }
  .alert-danger { background:#f8514915; border:1px solid var(--red); color:var(--red); }

  /* Log streaming */
  #log {
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius); padding:16px 18px;
    font-family:'JetBrains Mono',monospace; font-size:12.5px; line-height:1.75;
    max-height:500px; overflow-y:auto; margin-bottom:24px;
  }
  .log-ok     { color:var(--green);  }
  .log-change { color:var(--orange); }
  .log-skip   { color:var(--muted);  }
  .log-err    { color:var(--red);    }
  .log-info   { color:var(--blue);   }
  .log-warn   { color:var(--yellow); }

  /* Stats */
  .stats-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(155px,1fr));
    gap:14px; margin-bottom:32px;
  }
  .stat-card {
    background:var(--surface); border:1px solid var(--border);
    border-radius:var(--radius); padding:16px; text-align:center;
  }
  .stat-num { font-size:32px; font-weight:700; line-height:1.1; margin-bottom:4px; }
  .stat-label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; }

  /* Tabla */
  .table-wrap { overflow-x:auto; border:1px solid var(--border); border-radius:var(--radius); margin-bottom:20px; }
  table { width:100%; border-collapse:collapse; }
  thead th {
    background:#1c2230; color:var(--muted); font-size:11px; font-weight:600;
    text-transform:uppercase; letter-spacing:.5px; padding:10px 14px; text-align:left; white-space:nowrap;
  }
  tbody td { padding:9px 14px; border-top:1px solid var(--border); font-size:13px; vertical-align:middle; }
  tbody tr:hover td { background:#ffffff06; }
  .mono { font-family:'JetBrains Mono',monospace; font-size:12px; }
  .ellipsis { max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .tag { display:inline-block; border-radius:4px; padding:1px 7px; font-size:11px; font-family:'JetBrains Mono',monospace; }
  .tag-ok     { background:#3fb95018; color:var(--green); }
  .tag-err    { background:#f8514918; color:var(--red); }
  .tag-skip   { background:#8b949e18; color:var(--muted); }
  .tag-warn   { background:#d2992218; color:var(--yellow); }

  /* Spinner */
  @keyframes spin { to { transform:rotate(360deg); } }
  .spinner {
    display:inline-block; width:12px; height:12px;
    border:2px solid var(--muted); border-top-color:var(--blue);
    border-radius:50%; animation:spin .7s linear infinite;
    vertical-align:middle; margin-right:6px;
  }
  #progress-bar-wrap {
    background:var(--surface); border:1px solid var(--border);
    border-radius:30px; height:8px; margin:10px 0 20px; overflow:hidden;
  }
  #progress-bar { height:100%; background:var(--blue); border-radius:30px; width:0%; transition:width .3s; }
</style>
</head>
<body>

<h1>🔑 Backfill Hashes MD5</h1>
<p class="subtitle">
  Genera y guarda en la BD el hash MD5 de cada archivo físico del servidor
  &nbsp;·&nbsp;
  <span class="badge <?= $DRY_RUN ? 'badge-sim' : 'badge-real' ?>">
    <?= $DRY_RUN ? '⚠ Simulación' : '✅ Modo real' ?>
  </span>
  <?php if ($FORCE_ALL): ?>
    &nbsp;<span class="badge badge-warn">♻ Reprocesando TODOS</span>
  <?php endif; ?>
  <?php if ($CLEAR_HASHES && !$DRY_RUN): ?>
    &nbsp;<span class="badge badge-warn">🗑 Limpiando hashes previos</span>
  <?php endif; ?>
  <?php if ($LIMIT): ?>
    &nbsp;<span class="badge badge-sim">Límite: <?= $LIMIT ?></span>
  <?php endif; ?>
</p>

<!-- Botones -->
<div class="actions">
  <a class="btn btn-sim"    href="?dry_run=1">🔍 Simular (solo faltantes)</a>
  <a class="btn btn-real"   href="?">🚀 Ejecutar (solo faltantes)</a>
  <a class="btn btn-muted"  href="?all=1&dry_run=1">🔍 Simular (todos)</a>
  <a class="btn btn-muted"  href="?all=1">♻ Re-generar todos</a>
  <a class="btn btn-danger" href="?clear=1&dry_run=1"
     onclick="return confirm('¿Seguro que quieres borrar TODOS los hashes?')">
    🗑 Limpiar hashes (simular)
  </a>
  <a class="btn btn-danger" href="?clear=1"
     onclick="return confirm('¿Seguro? Esto borra TODOS los hashes de la BD.')">
    🗑 Limpiar hashes (real)
  </a>
</div>

<?php if ($CLEAR_HASHES && $DRY_RUN): ?>
<div class="alert alert-warn">
  ⚠ Con <strong>?clear=1</strong> sin <strong>dry_run</strong> se borrarían todos los hashes existentes de la BD.
  <a href="?clear=1" style="color:var(--red);margin-left:8px;">Hacerlo de verdad →</a>
</div>
<?php endif; ?>

<p style="margin-bottom:8px;color:var(--muted)">
  <span class="spinner" id="spinner"></span>
  <span id="running-msg">Procesando…</span>
  <span id="done-msg" style="display:none;color:var(--green);font-weight:600">✅ ¡Proceso completado!</span>
</p>
<div id="progress-bar-wrap"><div id="progress-bar"></div></div>

<div id="log">
<?php
    flush_out();
} // end !$IS_CLI html header

// ─── Conexión ─────────────────────────────────────────────────────────────────
if (!file_exists(__DIR__ . '/.env')) {
    $m = "❌ Archivo .env no encontrado.";
    $IS_CLI ? print($m."\n") : print('<span class="log-err">'.he($m).'</span>');
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
    $m = "❌ Error de conexión: " . $db->connect_error;
    $IS_CLI ? print($m."\n") : print('<span class="log-err">'.he($m).'</span>');
    exit(1);
}
$db->set_charset('utf8mb4');

// ─── Helpers de log ───────────────────────────────────────────────────────────
function logLine(string $type, string $msg): void
{
    $cls = ['ok'=>'log-ok','change'=>'log-change','skip'=>'log-skip',
            'err'=>'log-err','info'=>'log-info','warn'=>'log-warn'][$type] ?? '';
    if (isCli()) {
        $pre = ['ok'=>'✓ ','change'=>'✏  ','skip'=>'·  ','err'=>'✗  ','info'=>'→  ','warn'=>'⚠  '][$type] ?? '';
        echo "  $pre$msg\n";
    } else {
        echo '<span class="'.$cls.'">'.htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')."</span>\n";
        echo '<script>var l=document.getElementById("log");if(l)l.scrollTop=l.scrollHeight;</script>';
    }
    flush_out();
}

function updateProgress(int $done, int $total): void
{
    if (isCli() || $total === 0) return;
    $pct = round($done / $total * 100, 1);
    echo '<script>var b=document.getElementById("progress-bar");if(b)b.style.width="'.$pct.'%";</script>';
    flush_out();
}

// ─── Resolución de ruta física ────────────────────────────────────────────────
// Los file_path en BD pueden estar guardados como:
//   /Gogleanty/uploads/images/file.jpg  (con subfolder)
//   /uploads/images/file.jpg             (relativo limpio)
//   uploads/images/file.jpg              (sin slash inicial)
//   http://localhost/Gogleanty/uploads/… (URL completa antigua)
// ROOT_DIR = directorio donde está este script = raíz del proyecto en el servidor.

define('ROOT_DIR', __DIR__);

function resolvePhysicalPath(string $dbPath): ?string
{
    // 1. Quitar cualquier prefijo de URL (http://cualquier-cosa/ruta)
    $path = $dbPath;
    if (preg_match('#^https?://[^/]+(/.*)$#', $path, $m)) {
        $path = $m[1];
    }

    // 2. Quitar APP_BASE_PATH si coincide (/Gogleanty, /fotosanty, etc.)
    //    Detectamos el folder del proyecto desde ROOT_DIR
    $projectFolder = '/' . basename(ROOT_DIR); // ej: "/Gogleanty"
    if (strpos($path, $projectFolder . '/') === 0) {
        $path = substr($path, strlen($projectFolder));
    }

    // 3. Quitar slash inicial
    $path = ltrim($path, '/');

    // 4. Ruta candidata principal
    $candidates = [
        ROOT_DIR . '/' . $path,
        // Fallback: si solo quedaron los dos últimos segmentos (dirname/filename)
        ROOT_DIR . '/uploads/' . basename(dirname($path)) . '/' . basename($path),
    ];

    foreach ($candidates as $c) {
        // Normalizar separadores
        $c = str_replace('\\', '/', $c);
        if (file_exists($c) && is_file($c)) {
            return $c;
        }
    }

    return null;
}

// ─── Paso 0: Limpiar hashes si se pidió ──────────────────────────────────────
if ($CLEAR_HASHES && !$DRY_RUN) {
    $res = $db->query("UPDATE media SET device_file_hash = NULL");
    $affected = $db->affected_rows;
    logLine('warn', "🗑  Hashes borrados: $affected registros puestos a NULL");
    if (!$IS_CLI) echo "<br>\n";
    flush_out();
} elseif ($CLEAR_HASHES && $DRY_RUN) {
    logLine('warn', "🗑  [SIMULACIÓN] Se borrarían todos los hashes existentes.");
    if (!$IS_CLI) echo "<br>\n";
    flush_out();
}

// ─── Consulta ─────────────────────────────────────────────────────────────────
$whereClause = $FORCE_ALL
    ? "1=1"
    : "(device_file_hash IS NULL OR device_file_hash = '')";

$limitSql  = $LIMIT  > 0 ? "LIMIT $LIMIT"  : '';
$offsetSql = $OFFSET > 0 ? "OFFSET $OFFSET" : '';

// Contar primero para la barra de progreso
$countRow = $db->query("SELECT COUNT(*) as c FROM media WHERE $whereClause")->fetch_assoc();
$totalRows = (int)($countRow['c'] ?? 0);

$sql = "SELECT id, file_path, original_filename FROM media WHERE $whereClause ORDER BY id $limitSql $offsetSql";
$result = $db->query($sql);
if (!$result) {
    logLine('err', "Error en consulta: " . $db->error);
    exit(1);
}

$toProcess = $result->num_rows;
logLine('info', "Registros a procesar: $toProcess" . ($LIMIT ? " (límite: $LIMIT)" : '') . ($FORCE_ALL ? " — Modo: TODOS" : " — Modo: solo faltantes"));
if (!$IS_CLI) echo "<br>\n";
flush_out();

// ─── Stats ────────────────────────────────────────────────────────────────────
$stats = [
    'encontrado'    => 0,
    'no_encontrado' => 0,
    'actualizado'   => 0,
    'sin_cambio'    => 0,  // ya tenía hash idéntico (--all mode)
    'error'         => 0,
];

$log_changed  = [];
$log_missing  = [];
$log_errors   = [];

$processed = 0;

// ─── Procesamiento ────────────────────────────────────────────────────────────
while ($row = $result->fetch_assoc()) {
    $processed++;
    $id       = (int)$row['id'];
    $dbPath   = $row['file_path'];
    $fname    = $row['original_filename'];

    $physPath = resolvePhysicalPath($dbPath);

    if ($physPath === null) {
        $stats['no_encontrado']++;
        logLine('err', "ID=$id  ❌ Archivo no encontrado en disco — $fname  [$dbPath]");
        $log_missing[] = ['id'=>$id,'filename'=>$fname,'db_path'=>$dbPath];
        updateProgress($processed, $toProcess);
        continue;
    }

    $stats['encontrado']++;

    // Calcular MD5 del archivo físico
    $md5 = md5_file($physPath);
    if ($md5 === false) {
        $stats['error']++;
        logLine('err', "ID=$id  ❌ Error al leer el archivo para MD5 — $fname");
        $log_errors[] = ['id'=>$id,'filename'=>$fname,'error'=>'md5_file devolvió false'];
        updateProgress($processed, $toProcess);
        continue;
    }

    if (!$DRY_RUN) {
        $upd = $db->prepare("UPDATE media SET device_file_hash = ? WHERE id = ?");
        $upd->bind_param("si", $md5, $id);
        if ($upd->execute()) {
            $stats['actualizado']++;
            logLine('ok', "ID=$id  ✓  $md5  ← $fname");
            $log_changed[] = ['id'=>$id,'filename'=>$fname,'hash'=>$md5,'path'=>$physPath];
        } else {
            $stats['error']++;
            logLine('err', "ID=$id  ❌ Error UPDATE: " . $upd->error . " — $fname");
            $log_errors[] = ['id'=>$id,'filename'=>$fname,'error'=>$upd->error];
        }
        $upd->close();
    } else {
        $stats['actualizado']++;
        logLine('change', "ID=$id  [SIM]  $md5  ← $fname");
        $log_changed[] = ['id'=>$id,'filename'=>$fname,'hash'=>$md5,'path'=>$physPath];
    }

    updateProgress($processed, $toProcess);
}

$result->free();
$db->close();

// ─── Cierre HTML ──────────────────────────────────────────────────────────────
if (!$IS_CLI) {
?>
</div><!-- /#log -->

<script>
  document.getElementById('spinner').style.display   = 'none';
  document.getElementById('running-msg').style.display = 'none';
  document.getElementById('done-msg').style.display    = 'inline';
  var b = document.getElementById('progress-bar');
  if(b) b.style.width = '100%';
</script>

<!-- Estadísticas -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-num" style="color:var(--blue)"><?= $toProcess ?></div>
    <div class="stat-label">Procesados</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--green)"><?= $stats['encontrado'] ?></div>
    <div class="stat-label">Archivo encontrado</div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--orange)"><?= $stats['actualizado'] ?></div>
    <div class="stat-label"><?= $DRY_RUN ? 'Actualizaría' : 'Actualizados' ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--red)"><?= $stats['no_encontrado'] ?></div>
    <div class="stat-label">No encontrado</div>
  </div>
  <?php if ($stats['error'] > 0): ?>
  <div class="stat-card">
    <div class="stat-num" style="color:var(--red)"><?= $stats['error'] ?></div>
    <div class="stat-label">Errores</div>
  </div>
  <?php endif; ?>
</div>

<?php if ($DRY_RUN): ?>
<div class="alert alert-warn">
  ⚠ <strong>Modo Simulación</strong> — nada fue modificado en la BD.
  <a href="<?= he(strtok($_SERVER['REQUEST_URI'],'?')) ?>" style="color:var(--green);margin-left:12px;">🚀 Aplicar cambios reales →</a>
</div>
<?php endif; ?>

<!-- Tabla: actualizados/simulados -->
<?php if (count($log_changed) > 0): ?>
<h2>
  <?= $DRY_RUN ? '📋 Archivos que se actualizarían' : '📋 Archivos actualizados' ?>
  <span style="color:var(--muted);font-size:13px;font-weight:400;margin-left:8px">(<?= count($log_changed) ?>)</span>
</h2>
<div class="table-wrap">
<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Nombre original</th>
      <th>Hash MD5 generado</th>
      <th>Ruta física usada</th>
      <th>Estado</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($log_changed as $c): ?>
    <tr>
      <td class="mono" style="color:var(--muted)"><?= $c['id'] ?></td>
      <td class="ellipsis" title="<?= he($c['filename']) ?>"><?= he($c['filename']) ?></td>
      <td class="mono" style="color:var(--green)"><?= he($c['hash']) ?></td>
      <td class="mono ellipsis" style="color:var(--muted);font-size:11px" title="<?= he($c['path']) ?>"><?= he($c['path']) ?></td>
      <td>
        <span class="tag <?= $DRY_RUN ? 'tag-warn' : 'tag-ok' ?>">
          <?= $DRY_RUN ? 'Simulado' : 'OK' ?>
        </span>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<!-- Tabla: archivos no encontrados -->
<?php if (count($log_missing) > 0): ?>
<h2 style="color:var(--red);margin-top:28px">
  ❌ Archivos no encontrados en disco
  <span style="color:var(--muted);font-size:13px;font-weight:400;margin-left:8px">(<?= count($log_missing) ?>)</span>
</h2>
<div class="alert alert-danger">
  Estos registros existen en la BD pero su archivo físico no fue localizado en el servidor.
  Puede que la ruta guardada en <code>file_path</code> sea incorrecta, o el archivo fue eliminado.
</div>
<div class="table-wrap">
<table>
  <thead>
    <tr><th>ID</th><th>Nombre</th><th>file_path en BD</th></tr>
  </thead>
  <tbody>
  <?php foreach ($log_missing as $r): ?>
    <tr>
      <td class="mono" style="color:var(--muted)"><?= $r['id'] ?></td>
      <td class="ellipsis"><?= he($r['filename']) ?></td>
      <td class="mono ellipsis" style="color:var(--red);font-size:11px" title="<?= he($r['db_path']) ?>"><?= he($r['db_path']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>

<!-- Errores -->
<?php if (count($log_errors) > 0): ?>
<h2 style="color:var(--red);margin-top:28px">⚠ Otros errores (<?= count($log_errors) ?>)</h2>
<div class="table-wrap">
<table>
  <thead><tr><th>ID</th><th>Nombre</th><th>Error</th></tr></thead>
  <tbody>
  <?php foreach ($log_errors as $e): ?>
    <tr>
      <td class="mono"><?= $e['id'] ?></td>
      <td class="ellipsis"><?= he($e['filename']) ?></td>
      <td class="mono" style="color:var(--red);font-size:12px"><?= he($e['error']) ?></td>
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
    // ─── Resumen CLI ──────────────────────────────────────────────────────────
    echo "\n=== Resumen ===\n";
    echo "  Procesados       : $toProcess\n";
    echo "  Encontrados      : {$stats['encontrado']}\n";
    echo "  No encontrados   : {$stats['no_encontrado']}\n";
    $label = $DRY_RUN ? 'Actualizaría' : 'Actualizados';
    echo "  $label      : {$stats['actualizado']}\n";
    if ($stats['error'] > 0) echo "  ❌ Errores       : {$stats['error']}\n";
    if ($DRY_RUN) echo "\n⚠  MODO SIMULACIÓN — nada fue modificado.\n";
    echo "\n";
}
