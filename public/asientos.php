<?php
require __DIR__ . '/../config/db.php';

$id_evento = filter_input(INPUT_GET, 'id_evento', FILTER_VALIDATE_INT) ?: 1;
$pdo = getPDO();

$stmtSectores = $pdo->prepare(
    "SELECT id_sector, nombre, precio FROM sector WHERE id_evento = :id_evento ORDER BY precio DESC"
);
$stmtSectores->execute([':id_evento' => $id_evento]);
$sectores = $stmtSectores->fetchAll();

$stmtAsientos = $pdo->prepare(
    "SELECT id_asiento, fila, numero, estado FROM asiento WHERE id_sector = :id_sector ORDER BY fila, numero"
);

$activo = 'asientos';
$tituloPagina = 'Asientos';
include __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
  <div class="row g-4">

    <div class="col-lg-8">
      <div class="tn-stadium-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div class="tn-card-label mb-0">PLANO DEL ESTADIO</div>
          <span class="tn-live-badge"><span class="tn-live-dot"></span> EN VIVO</span>
        </div>

        <?php foreach ($sectores as $sector): ?>
          <?php
            $stmtAsientos->execute([':id_sector' => $sector['id_sector']]);
            $asientos = $stmtAsientos->fetchAll();
            $porFila = [];
            foreach ($asientos as $a) { $porFila[$a['fila']][] = $a; }
          ?>
          <div class="mb-4">
            <div class="d-flex justify-content-between align-items-baseline mb-2">
              <div class="fw-bold"><?= htmlspecialchars($sector['nombre']) ?></div>
              <div class="text-secondary small">$<?= number_format($sector['precio'],0,',','.') ?> ARS</div>
            </div>
            <?php foreach ($porFila as $fila => $asientosFila): ?>
              <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <span class="tn-fila-label"><?= $fila ?></span>
                <?php foreach ($asientosFila as $a): ?>
                  <button
                    class="asiento-btn asiento-<?= $a['estado'] ?>"
                    data-asiento-id="<?= $a['id_asiento'] ?>"
                    data-sector="<?= htmlspecialchars($sector['nombre']) ?>"
                    data-fila="<?= $fila ?>"
                    data-numero="<?= $a['numero'] ?>"
                    data-precio="<?= $sector['precio'] ?>"
                    <?= $a['estado'] !== 'disponible' ? 'disabled' : '' ?>
                  ><?= $a['numero'] ?></button>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>

        <div class="tn-stage">ESCENARIO</div>

        <div class="d-flex justify-content-center gap-4 mt-3 small">
          <span><span class="tn-legend-dot" style="background:#4ade80;"></span>Disponible</span>
          <span><span class="tn-legend-dot" style="background:#f87171;"></span>Ocupado</span>
          <span><span class="tn-legend-dot" style="background:var(--cyan);"></span>Seleccionado</span>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="tn-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="tn-card-label mb-0">📶 SINCRONIZACIÓN EN VIVO</span>
        </div>
        <div class="text-secondary small mb-2">Última actualización: <span id="ultima-sync">ahora</span></div>
        <div class="tn-sync-bar"><div class="tn-sync-bar-fill"></div></div>
      </div>

      <div class="tn-card mb-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="fw-bold">Asientos Seleccionados</span>
          <span class="fw-bold" id="contador-seleccionados">0</span>
        </div>
        <div id="lista-seleccionados" class="text-secondary text-center py-4">
          📍 Hacé click en un asiento verde para seleccionarlo
        </div>
      </div>

      <button class="btn w-100 mb-2 py-3 fw-bold" id="btn-reservar" style="background:var(--bg-elevated); border:1px solid var(--card-border); color:var(--text-muted);" disabled>
        Reservar Asiento
      </button>
      <a href="#" id="btn-continuar" class="btn btn-tn-gradient w-100 py-3 fw-bold disabled" style="pointer-events:none; opacity:0.5;">
        Continuar Compra ›
      </a>
    </div>

  </div>
</div>

<div id="toast" class="tn-toast">
  <div class="titulo" id="toast-titulo"></div>
  <div class="mensaje" id="toast-mensaje"></div>
</div>

<script>
const ID_EVENTO = <?= $id_evento ?>;
// En un login real, esto vendría de la sesión del usuario autenticado.
const ID_CLIENTE = 1;

const seleccionados = new Map(); // id_asiento -> {sector, fila, numero, precio}

function mostrarToast(titulo, mensaje, ok = false) {
  const toast = document.getElementById('toast');
  document.getElementById('toast-titulo').textContent = titulo;
  document.getElementById('toast-mensaje').textContent = mensaje;
  toast.classList.toggle('tn-toast-ok', ok);
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3500);
}

function pintarAsiento(idAsiento, estado) {
  const btn = document.querySelector(`[data-asiento-id="${idAsiento}"]`);
  if (!btn) return;
  btn.classList.remove('asiento-disponible', 'asiento-reservado', 'asiento-vendido', 'asiento-seleccionado');
  if (estado === 'disponible') {
    btn.classList.add('asiento-disponible');
    btn.disabled = false;
  } else if (estado === 'mia') {
    btn.classList.add('asiento-seleccionado');
    btn.disabled = false;
  } else {
    btn.classList.add(estado === 'vendido' ? 'asiento-vendido' : 'asiento-reservado');
    btn.disabled = true;
  }
}

function renderPanel() {
  const cont = document.getElementById('lista-seleccionados');
  const contador = document.getElementById('contador-seleccionados');
  contador.textContent = seleccionados.size;

  if (seleccionados.size === 0) {
    cont.innerHTML = '📍 Hacé click en un asiento verde para seleccionarlo';
    cont.className = 'text-secondary text-center py-4';
    document.getElementById('btn-continuar').classList.add('disabled');
    document.getElementById('btn-continuar').style.pointerEvents = 'none';
    document.getElementById('btn-continuar').style.opacity = '0.5';
    return;
  }

  cont.className = '';
  let total = 0;
  let html = '';
  seleccionados.forEach((s, id) => {
    total += Number(s.precio);
    html += `<div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--card-border);">
      <div><div class="fw-bold small">${s.sector}</div><div class="text-secondary" style="font-size:0.78rem;">Fila ${s.fila} · Asiento ${s.numero}</div></div>
      <div class="fw-bold">$${Number(s.precio).toLocaleString('es-AR')}</div>
    </div>`;
  });
  html += `<div class="d-flex justify-content-between pt-3 fw-bold">
      <span>Total</span><span style="color:var(--cyan);">$${total.toLocaleString('es-AR')}</span>
    </div>`;
  cont.innerHTML = html;

  const btnContinuar = document.getElementById('btn-continuar');
  btnContinuar.classList.remove('disabled');
  btnContinuar.style.pointerEvents = 'auto';
  btnContinuar.style.opacity = '1';
}

async function toggleAsiento(btn) {
  const idAsiento = btn.dataset.asientoId;

  if (seleccionados.has(idAsiento)) {
    // liberar
    const resp = await fetch('../api/cancelar_reserva.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ id_asiento: Number(idAsiento), id_cliente: ID_CLIENTE, id_evento: ID_EVENTO }),
    });
    if (resp.ok) {
      seleccionados.delete(idAsiento);
      pintarAsiento(idAsiento, 'disponible');
      renderPanel();
    }
    return;
  }

  const resp = await fetch('../api/reservar.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ id_asiento: Number(idAsiento), id_cliente: ID_CLIENTE, id_evento: ID_EVENTO }),
  });
  const data = await resp.json();

  if (resp.status === 409) {
    mostrarToast('Asiento no disponible', 'Alguien más acaba de reservar esta butaca. Elegí otra.');
    pintarAsiento(idAsiento, 'reservado');
    return;
  }
  if (!resp.ok) {
    mostrarToast('Error', data.error ?? 'No se pudo reservar el asiento');
    return;
  }

  seleccionados.set(idAsiento, {
    sector: btn.dataset.sector, fila: btn.dataset.fila, numero: btn.dataset.numero, precio: btn.dataset.precio,
  });
  pintarAsiento(idAsiento, 'mia');
  renderPanel();
}

document.querySelectorAll('.asiento-btn').forEach(btn => {
  btn.addEventListener('click', () => toggleAsiento(btn));
});

document.getElementById('btn-continuar').addEventListener('click', (e) => {
  e.preventDefault();
  if (seleccionados.size === 0) return;
  const payload = { id_evento: ID_EVENTO, id_cliente: ID_CLIENTE, asientos: Array.from(seleccionados, ([id, s]) => ({ id_asiento: Number(id), ...s })) };
  sessionStorage.setItem('tn_carrito', JSON.stringify(payload));
  window.location.href = 'comprar.php';
});

// --- Sincronización en vivo: refleja cambios hechos por OTROS usuarios ---
try {
  const ws = new WebSocket('ws://' + location.hostname + ':8080');
  ws.onmessage = (ev) => {
    const data = JSON.parse(ev.data);
    document.getElementById('ultima-sync').textContent = 'hace instantes';

    if (data.type === 'seat_update' && data.id_evento === ID_EVENTO) {
      // si el asiento que cambió es uno que YO tengo seleccionado, no lo piso
      if (!seleccionados.has(String(data.id_asiento))) {
        pintarAsiento(data.id_asiento, data.estado);
      }
    }
  };
  ws.onclose = () => console.warn('WebSocket desconectado');
} catch (e) { console.warn('WebSocket no disponible', e); }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
