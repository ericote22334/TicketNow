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
          <div class="col-12"><input class="tn-input" id="f-tarjeta" placeholder="Número de tarjeta"></div>
          <div class="col-12"><input class="tn-input" id="f-titular" placeholder="Titular de la tarjeta"></div>
          <div class="col-6"><input class="tn-input" id="f-vencimiento" placeholder="MM / AA"></div>
          <div class="col-6"><input class="tn-input" id="f-cvv" placeholder="CVV"></div>
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

function validarFormulario() {
  const nombre = document.getElementById('f-nombre').value.trim();
  const apellido = document.getElementById('f-apellido').value.trim();
  const email = document.getElementById('f-email').value.trim();
  const telefono = document.getElementById('f-telefono').value.trim();
  const tarjeta = document.getElementById('f-tarjeta').value.trim();
  const titular = document.getElementById('f-titular').value.trim();
  const vencimiento = document.getElementById('f-vencimiento').value.trim();
  const cvv = document.getElementById('f-cvv').value.trim();

  if (!nombre) { mostrarToast('Faltan datos', 'Ingresá tu nombre.'); return false; }
  if (!apellido) { mostrarToast('Faltan datos', 'Ingresá tu apellido.'); return false; }
  if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { mostrarToast('Email inválido', 'Ingresá un correo electrónico válido.'); return false; }
  if (!telefono || !/^\+?[0-9\s()-]{7,15}$/.test(telefono)) { mostrarToast('Teléfono inválido', 'Ingresá un teléfono válido.'); return false; }
  if (!tarjeta || tarjeta.replace(/\s+/g, '').length < 12) { mostrarToast('Tarjeta inválida', 'Ingresá un número de tarjeta válido.'); return false; }
  if (!titular || titular.length < 3) { mostrarToast('Titular inválido', 'Ingresá el nombre del titular.'); return false; }
  if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(vencimiento)) { mostrarToast('Vencimiento inválido', 'Usá el formato MM/AA.'); return false; }
  if (!/^\d{3,4}$/.test(cvv)) { mostrarToast('CVV inválido', 'Ingresá un CVV de 3 o 4 dígitos.'); return false; }
  if (!metodoPago) { mostrarToast('Método de pago', 'Seleccioná un método de pago.'); return false; }
  return true;
}

document.getElementById('btn-confirmar').addEventListener('click', async () => {
  if (!carrito || !carrito.asientos || carrito.asientos.length === 0) {
    mostrarToast('Carrito vacío', 'Elegí al menos un asiento antes de confirmar.');
    return;
  }
  if (!validarFormulario()) {
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
