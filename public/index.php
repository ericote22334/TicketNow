<?php
require __DIR__ . '/../config/db.php';

$id_evento = filter_input(INPUT_GET, 'id_evento', FILTER_VALIDATE_INT) ?: 1;
$pdo = getPDO();

$stmt = $pdo->prepare(
    "SELECT e.id_evento, e.nombre_evento, e.fecha, e.hora,
            l.nombre_lugar, l.ubicacion,
            GROUP_CONCAT(DISTINCT a.nombre_artista SEPARATOR ', ') AS artistas
     FROM evento e
     INNER JOIN lugar l ON l.id_lugar = e.id_lugar
     LEFT JOIN evento_artista ea ON ea.id_evento = e.id_evento
     LEFT JOIN artista a ON a.id_artista = ea.id_artista
     WHERE e.id_evento = :id_evento
     GROUP BY e.id_evento"
);
$stmt->execute([':id_evento' => $id_evento]);
$evento = $stmt->fetch();

if (!$evento) {
    http_response_code(404);
    die('Evento no encontrado.');
}

$stmtSectores = $pdo->prepare(
    "SELECT s.id_sector, s.nombre, s.precio, s.capacidad,
            SUM(a.estado='disponible') AS disponibles
     FROM sector s
     LEFT JOIN asiento a ON a.id_sector = s.id_sector
     WHERE s.id_evento = :id_evento
     GROUP BY s.id_sector
     ORDER BY s.precio DESC"
);
$stmtSectores->execute([':id_evento' => $id_evento]);
$sectores = $stmtSectores->fetchAll();

$stmtStock = $pdo->prepare(
    "SELECT SUM(a.estado='disponible') AS disponibles
     FROM asiento a INNER JOIN sector s ON s.id_sector = a.id_sector
     WHERE s.id_evento = :id_evento"
);
$stmtStock->execute([':id_evento' => $id_evento]);
$disponibles = (int) ($stmtStock->fetch()['disponibles'] ?? 0);

$fechaFormateada = (new DateTime($evento['fecha']))->format('d \d\e F, Y');
$dias = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$diaSemana = $dias[(new DateTime($evento['fecha']))->format('l')];

$activo = 'inicio';
$tituloPagina = $evento['nombre_evento'];
include __DIR__ . '/includes/header.php';
?>

<section class="tn-hero">
  <div class="container">
    <div class="tn-hero-content" style="max-width:640px;">
      <span class="tn-eyebrow">
        <span class="tn-live-dot" style="background:var(--green);"></span>
        DISPONIBILIDAD EN TIEMPO REAL
      </span>
      <h1><?= htmlspecialchars($evento['nombre_evento']) ?></h1>
      <span class="artista"><?= htmlspecialchars($evento['artistas'] ?: 'Artista a confirmar') ?></span>
      <div class="tn-hero-meta">
        <span>📅 <?= $diaSemana ?> <?= (new DateTime($evento['fecha']))->format('d \d\e F, Y') ?></span>
        <span>🕘 <?= (new DateTime($evento['hora']))->format('H:i') ?> hs</span>
        <span>📍 <?= htmlspecialchars($evento['nombre_lugar']) ?>, <?= htmlspecialchars($evento['ubicacion']) ?></span>
      </div>
      <div class="d-flex flex-wrap gap-3">
        <a href="asientos.php?id_evento=<?= $id_evento ?>" class="btn-tn-primary">🎫 Comprar Entradas →</a>
        <span class="tn-pill-stat">⚡ <span id="hero-disponibles"><?= $disponibles ?></span> entradas restantes</span>
      </div>
    </div>
  </div>
</section>

<div class="container my-5">

  <div class="row g-3 mb-5">
    <div class="col-md-4">
      <div class="tn-card">
        <div class="tn-icon-box" style="background:rgba(34,211,238,0.12); color:var(--cyan);">📅</div>
        <div class="tn-card-label">Fecha</div>
        <div class="fw-bold fs-5"><?= (new DateTime($evento['fecha']))->format('d M Y') ?></div>
        <div class="text-secondary small"><?= $diaSemana ?></div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="tn-card">
        <div class="tn-icon-box" style="background:rgba(168,85,247,0.12); color:var(--purple);">🕘</div>
        <div class="tn-card-label">Horario</div>
        <div class="fw-bold fs-5"><?= (new DateTime($evento['hora']))->format('H:i') ?> hs</div>
        <div class="text-secondary small">Apertura de puertas 2 hs antes</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="tn-card">
        <div class="tn-icon-box" style="background:rgba(34,197,94,0.12); color:var(--green);">📍</div>
        <div class="tn-card-label">Lugar</div>
        <div class="fw-bold fs-5"><?= htmlspecialchars($evento['nombre_lugar']) ?></div>
        <div class="text-secondary small"><?= htmlspecialchars($evento['ubicacion']) ?></div>
      </div>
    </div>
  </div>

  <h2 class="tn-section-title">Sectores &amp; Precios</h2>
  <div class="row g-3 mb-5">
    <?php foreach ($sectores as $s): ?>
    <div class="col-6 col-md-3">
      <div class="tn-sector-card h-100">
        <div class="nombre"><?= htmlspecialchars($s['nombre']) ?></div>
        <div class="precio">$<?= number_format($s['precio'], 0, ',', '.') ?></div>
        <div class="detalle">ARS · <?= (int)$s['disponibles'] ?> disponibles</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="tn-cta-banner mb-5">
    <div>
      <div class="fw-bold fs-5 mb-1">¡No te quedes sin entradas!</div>
      <div class="text-secondary">El evento se está agotando. Comprá ahora y asegurá tu lugar.</div>
    </div>
    <a href="asientos.php?id_evento=<?= $id_evento ?>" class="btn-tn-gradient">Seleccionar Asientos ›</a>
  </div>

  <div class="text-center">
    <div class="tn-card-label mb-3">TECNOLOGÍAS DEL SISTEMA</div>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      <?php foreach (['HTML5','CSS3','Bootstrap 5','JavaScript','PHP 8','MySQL','WebSockets'] as $tech): ?>
        <span class="tn-tech-badge"><?= $tech ?></span>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<script>
const ID_EVENTO = <?= $id_evento ?>;
try {
  const ws = new WebSocket('ws://' + location.hostname + ':8080');
  ws.onmessage = (ev) => {
    const data = JSON.parse(ev.data);
    if (data.type === 'stock_update' && data.id_evento === ID_EVENTO) {
      document.getElementById('hero-disponibles').textContent = data.disponibles;
    }
  };
} catch (e) { console.warn('WebSocket no disponible', e); }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
