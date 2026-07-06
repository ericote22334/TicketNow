<?php
require __DIR__ . '/../config/db.php';

$id_evento = filter_input(INPUT_GET, 'id_evento', FILTER_VALIDATE_INT) ?: 1;
$pdo = getPDO();

$stmt = $pdo->prepare(
    "SELECT nombre_evento, fecha, l.nombre_lugar
     FROM evento e INNER JOIN lugar l ON l.id_lugar = e.id_lugar
     WHERE id_evento = :id_evento"
);
$stmt->execute([':id_evento' => $id_evento]);
$evento = $stmt->fetch();

$activo = 'comprar';
$tituloPagina = 'Comprar';
include __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
  <div class="row g-4">

    <div class="col-lg-7">
      <div class="tn-card mb-4">
        <div class="d-flex align-items-center gap-2 mb-4">
          <span>👤</span><span class="fw-bold fs-5">Datos Personales</span>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="tn-form-label">Nombre</label>
            <input type="text" class="tn-input" id="f-nombre" placeholder="Juan">
          </div>
          <div class="col-md-6">
            <label class="tn-form-label">Apellido</label>
            <input type="text" class="tn-input" id="f-apellido" placeholder="García">
          </div>
          <div class="col-12">
            <label class="tn-form-label">Email</label>
            <input type="email" class="tn-input" id="f-email" placeholder="juan@email.com">
          </div>
          <div class="col-12">
            <label class="tn-form-label">Teléfono</label>
            <input type="text" class="tn-input" id="f-telefono" placeholder="+54 11 1234-5678">
          </div>
        </div>
      </div>

      <div class="tn-card">
        <div class="d-flex align-items-center gap-2 mb-4">
          <span>💳</span><span class="fw-bold fs-5">Método de Pago</span>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6"><div class="tn-payment-option selected" data-metodo="Tarjeta de Crédito">Tarjeta de Crédito</div></div>
          <div class="col-6"><div class="tn-payment-option" data-metodo="Tarjeta de Débito">Tarjeta de Débito</div></div>
          <div class="col-6"><div class="tn-payment-option" data-metodo="MercadoPago">MercadoPago</div></div>
          <div class="col-6"><div class="tn-payment-option" data-metodo="Transferencia">Transferencia</div></div>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-12"><input class="tn-input" placeholder="Número de tarjeta"></div>
          <div class="col-12"><input class="tn-input" placeholder="Titular de la tarjeta"></div>
          <div class="col-6"><input class="tn-input" placeholder="MM / AA"></div>
          <div class="col-6"><input class="tn-input" placeholder="CVV"></div>
        </div>
        <div class="d-flex align-items-center gap-2 p-3 mb-3 rounded" style="background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.25); color:#4ade80; font-size:0.85rem;">
          🔒 Transacción segura · Cifrado SSL 256-bit · PCI DSS
        </div>
        <button id="btn-confirmar" class="btn-tn-gradient w-100 py-3 fw-bold border-0">
          Confirmar Compra — <span id="btn-total">$0 ARS</span>
        </button>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="tn-card">
        <div class="fw-bold fs-5 mb-3">Resumen del Pedido</div>
        <div class="d-flex gap-3 align-items-center mb-3 pb-3" style="border-bottom:1px solid var(--card-border);">
          <div class="rounded" style="width:56px;height:56px;background:linear-gradient(135deg,var(--cyan),var(--purple));"></div>
          <div>
            <div class="fw-bold"><?= htmlspecialchars($evento['nombre_evento'] ?? '') ?></div>
            <div class="text-secondary small"><?= $evento ? (new DateTime($evento['fecha']))->format('d \d\e F, Y') : '' ?></div>
            <div class="text-secondary small"><?= htmlspecialchars($evento['nombre_lugar'] ?? '') ?></div>
          </div>
        </div>

        <div id="resumen-asientos"></div>

        <div id="resumen-vacio" class="d-flex align-items-center gap-2 p-3 rounded mb-3" style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.3); color:var(--orange); font-size:0.85rem;">
          ⚠️ No hay asientos seleccionados
        </div>

        <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Subtotal</span><span id="r-subtotal">$0</span></div>
        <div class="d-flex justify-content-between mb-3"><span class="text-secondary">Cargo de servicio (12%)</span><span id="r-cargo">$0</span></div>
        <div class="d-flex justify-content-between fw-bold fs-5 pt-3" style="border-top:1px solid var(--card-border);">
          <span>Total</span><span style="color:var(--cyan);" id="r-total">$0</span>
        </div>
        <div class="text-secondary small text-center mt-3">🛡️ Compra protegida por TicketNow</div>
      </div>
    </div>

  </div>
</div>

<div id="toast" class="tn-toast">
  <div class="titulo" id="toast-titulo"></div>
  <div class="mensaje" id="toast-mensaje"></div>
</div>

<script>
const ID_CLIENTE = 1; // en producción: id de la sesión autenticada
let metodoPago = 'Tarjeta de Crédito';

const carrito = JSON.parse(sessionStorage.getItem('tn_carrito') || 'null');

function money(n) { return '$' + Number(n).toLocaleString('es-AR'); }

function renderResumen() {
  const cont = document.getElementById('resumen-asientos');
  const vacio = document.getElementById('resumen-vacio');

  if (!carrito || !carrito.asientos || carrito.asientos.length === 0) {
    vacio.style.display = 'flex';
    cont.innerHTML = '';
    return;
  }
  vacio.style.display = 'none';

  let subtotal = 0;
  let html = '';
  carrito.asientos.forEach(a => {
    subtotal += Number(a.precio);
    html += `<div class="d-flex justify-content-between small mb-2">
      <span class="text-secondary">${a.sector} · Fila ${a.fila} - Asiento ${a.numero}</span>
      <span>${money(a.precio)}</span>
    </div>`;
  });
  cont.innerHTML = html;

  const cargo = Math.round(subtotal * 0.12 * 100) / 100;
  const total = subtotal + cargo;

  document.getElementById('r-subtotal').textContent = money(subtotal);
  document.getElementById('r-cargo').textContent = money(cargo);
  document.getElementById('r-total').textContent = money(total);
  document.getElementById('btn-total').textContent = money(total) + ' ARS';
}

document.querySelectorAll('.tn-payment-option').forEach(el => {
  el.addEventListener('click', () => {
    document.querySelectorAll('.tn-payment-option').forEach(x => x.classList.remove('selected'));
    el.classList.add('selected');
    metodoPago = el.dataset.metodo;
  });
});

function mostrarToast(titulo, mensaje, ok = false) {
  const toast = document.getElementById('toast');
  document.getElementById('toast-titulo').textContent = titulo;
  document.getElementById('toast-mensaje').textContent = mensaje;
  toast.classList.toggle('tn-toast-ok', ok);
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 4000);
}

document.getElementById('btn-confirmar').addEventListener('click', async () => {
  if (!carrito || !carrito.asientos || carrito.asientos.length === 0) {
    mostrarToast('Carrito vacío', 'Elegí al menos un asiento antes de confirmar.');
    return;
  }

  const resp = await fetch('../api/confirmar_compra.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      id_cliente: ID_CLIENTE,
      id_evento: carrito.id_evento,
      asientos: carrito.asientos.map(a => a.id_asiento),
      metodo_pago: metodoPago,
    }),
  });
  const data = await resp.json();

  if (resp.status === 409) {
    mostrarToast('Uno o más asientos ya no están disponibles', 'Volvé al mapa de asientos y elegí otras butacas.');
    return;
  }
  if (!resp.ok) {
    mostrarToast('Error', data.error ?? 'No se pudo confirmar la compra');
    return;
  }

  sessionStorage.removeItem('tn_carrito');
  mostrarToast('¡Compra confirmada!', `Total pagado: ${money(data.total)}. ¡Disfrutá el show!`, true);
  setTimeout(() => window.location.href = 'index.php', 2500);
});

renderResumen();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
