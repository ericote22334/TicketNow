<?php
require __DIR__ . '/../config/db.php';

$id_evento = filter_input(INPUT_GET, 'id_evento', FILTER_VALIDATE_INT) ?: 1;

$pdoTmp = getPDO();
$stmtSector = $pdoTmp->prepare("SELECT id_sector FROM sector WHERE id_evento = :id_evento ORDER BY id_sector LIMIT 1");
$stmtSector->execute([':id_evento' => $id_evento]);
$primerSector = $stmtSector->fetch()['id_sector'] ?? null;

$activo = 'admin';
$tituloPagina = 'Admin';
include __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
      <h1 class="tn-section-title mb-1">Panel de Administrador</h1>
      <div class="text-secondary">TicketNow · Gestión de Entradas</div>
    </div>
    <span class="tn-live-badge"><span class="tn-live-dot"></span> EN VIVO</span>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="tn-card">
        <div class="tn-icon-box" style="background:rgba(34,211,238,0.12); color:var(--cyan);">🎫</div>
        <div class="tn-stat-value" id="stat-vendidas">–</div>
        <div class="text-secondary small">Vendidas · de <span id="stat-total">–</span></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="tn-card">
        <div class="tn-icon-box" style="background:rgba(34,197,94,0.12); color:var(--green);">📦</div>
        <div class="tn-stat-value" style="color:var(--green);" id="stat-disponibles">–</div>
        <div class="text-secondary small">Disponibles · restantes</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="tn-card">
        <div class="tn-icon-box" style="background:rgba(168,85,247,0.12); color:var(--purple);">📈</div>
        <div class="tn-stat-value" style="color:var(--purple);" id="stat-ocupacion">–</div>
        <div class="text-secondary small">Ocupación · del aforo</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="tn-card">
        <div class="tn-icon-box" style="background:rgba(245,158,11,0.12); color:var(--orange);">$</div>
        <div class="tn-stat-value" style="color:var(--orange);" id="stat-recaudacion">–</div>
        <div class="text-secondary small">Recaudación · ARS</div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-7">
      <div class="tn-card h-100">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <span class="fw-bold fs-5">Ventas por Sector</span><span>📊</span>
        </div>
        <div id="chart-sectores" class="d-flex align-items-end gap-3" style="height:220px;"></div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="tn-card mb-3">
        <div class="fw-bold fs-5 mb-3 text-center">Gestión de Stock</div>
        <div class="tn-stock-control mb-3">
          <button class="tn-stock-btn" id="btn-menos">−</button>
          <div class="text-center">
            <div class="tn-stat-value" id="stock-actual">–</div>
            <div class="text-secondary small">stock actual</div>
          </div>
          <button class="tn-stock-btn" id="btn-mas">+</button>
        </div>
        <button class="btn w-100 py-2" style="background:var(--bg-elevated); border:1px solid var(--card-border); color:var(--cyan);" id="btn-cargar-stock">
          Cargar Nuevo Stock
        </button>
      </div>

      <div class="tn-card">
        <div class="fw-bold fs-5 mb-3">Ocupación por Sector</div>
        <div id="ocupacion-sectores"></div>
      </div>
    </div>
  </div>

  <div class="tn-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <span class="fw-bold fs-5">Últimas Ventas</span>
      <a href="#" style="color:var(--cyan); font-size:0.9rem;">Ver todas →</a>
    </div>
    <div class="table-responsive">
      <table class="table tn-table mb-0" style="color:var(--text);">
        <thead>
          <tr><th># ID</th><th>Sector</th><th>Fila</th><th>Asiento</th><th>Precio</th><th>Estado</th></tr>
        </thead>
        <tbody id="tabla-ventas"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const ID_EVENTO = <?= $id_evento ?>;
const ID_SECTOR_STOCK = <?= $primerSector ? (int)$primerSector : 'null' ?>;

function money(n) { return '$' + Number(n).toLocaleString('es-AR'); }

async function cargarStats() {
  const resp = await fetch(`../api/admin_stats.php?id_evento=${ID_EVENTO}`);
  const d = await resp.json();

  document.getElementById('stat-vendidas').textContent = d.vendidas;
  document.getElementById('stat-total').textContent = d.total;
  document.getElementById('stat-disponibles').textContent = d.disponibles;
  document.getElementById('stat-ocupacion').textContent = d.ocupacion + '%';
  document.getElementById('stat-recaudacion').textContent = '$' + (d.recaudacion/1000000).toFixed(1) + 'M';
  document.getElementById('stock-actual').textContent = d.total;

  // Gráfico de barras simple (CSS), altura proporcional a la mayor venta
  const max = Math.max(1, ...d.ventas_por_sector.map(s => Number(s.vendidas) || 0));
  document.getElementById('chart-sectores').innerHTML = d.ventas_por_sector.map(s => {
    const alturaPct = Math.max(4, (Number(s.vendidas || 0) / max) * 100);
    return `<div class="d-flex flex-column align-items-center justify-content-end" style="flex:1; height:100%;">
      <div class="text-secondary small mb-1">${s.vendidas || 0}</div>
      <div class="tn-bar" style="height:${alturaPct}%;"></div>
      <div class="text-secondary small mt-2">${s.nombre}</div>
    </div>`;
  }).join('');

  // Ocupación por sector
  document.getElementById('ocupacion-sectores').innerHTML = d.ocupacion_por_sector.map(s => {
    const pct = s.capacidad > 0 ? Math.round((s.ocupados / s.capacidad) * 100) : 0;
    return `<div class="mb-3">
      <div class="d-flex justify-content-between small mb-1">
        <span>${s.nombre}</span><span class="text-secondary">${s.ocupados}/${s.capacidad}</span>
      </div>
      <div class="tn-progress-track"><div class="tn-progress-fill" style="width:${pct}%;"></div></div>
    </div>`;
  }).join('');

  // Últimas ventas
  document.getElementById('tabla-ventas').innerHTML = d.ultimas_ventas.map(v => `
    <tr>
      <td class="text-secondary">#${1000 + Number(v.id)}</td>
      <td>${v.sector}</td>
      <td>${v.fila}</td>
      <td>${v.numero}</td>
      <td class="fw-bold">${money(v.precio)}</td>
      <td><span class="badge-vendida">✓ Vendida</span></td>
    </tr>
  `).join('') || '<tr><td colspan="6" class="text-secondary text-center py-4">Sin ventas todavía</td></tr>';
}

async function ajustarStock(delta) {
  if (!ID_SECTOR_STOCK) return;
  const resp = await fetch('../api/actualizar_capacidad.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id_sector: ID_SECTOR_STOCK, delta }),
  });
  const data = await resp.json();
  if (!resp.ok) { alert(data.error ?? 'No se pudo actualizar el stock'); return; }
  cargarStats();
}

document.getElementById('btn-mas').addEventListener('click', () => ajustarStock(1));
document.getElementById('btn-menos').addEventListener('click', () => ajustarStock(-1));
document.getElementById('btn-cargar-stock').addEventListener('click', () => ajustarStock(1));

cargarStats();
setInterval(cargarStats, 5000); // refresco de respaldo cada 5s

try {
  const ws = new WebSocket('ws://' + location.hostname + ':8080');
  ws.onmessage = (ev) => {
    const data = JSON.parse(ev.data);
    if ((data.type === 'stock_update' || data.type === 'seat_update') && data.id_evento === ID_EVENTO) {
      cargarStats(); // actualización instantánea en vez de esperar los 5s
    }
  };
} catch (e) { console.warn('WebSocket no disponible', e); }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
