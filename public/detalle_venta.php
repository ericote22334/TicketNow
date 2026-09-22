<?php
/**
 * GET /public/detalle_venta.php?id_venta=123
 * Muestra el detalle completo de UNA venta: datos del cliente, datos de
 * la compra (fecha, método de pago, estado) y el listado de entradas
 * (sector, fila, asiento, precio) con el desglose de subtotal / cargo
 * de servicio / total. Se accede haciendo click en una fila de la tabla
 * "Últimas Ventas" del panel de admin.
 */

require __DIR__ . '/../config/db.php';

$id_venta = filter_input(INPUT_GET, 'id_venta', FILTER_VALIDATE_INT);
$pdo = getPDO();

$venta = null;
$asientosVenta = [];

if ($id_venta) {
    $stmt = $pdo->prepare(
        "SELECT v.id_venta, v.fecha_venta, v.subtotal, v.cargo_servicio, v.total,
                v.metodo_pago, v.estado, v.id_evento,
                c.nombre, c.apellido, c.telefono,
                e.nombre_evento, l.nombre_lugar
         FROM venta v
         INNER JOIN cliente c ON c.id_cliente = v.id_cliente
         INNER JOIN evento e ON e.id_evento = v.id_evento
         INNER JOIN lugar l ON l.id_lugar = e.id_lugar
         WHERE v.id_venta = :id_venta"
    );
    $stmt->execute([':id_venta' => $id_venta]);
    $venta = $stmt->fetch() ?: null;

    if ($venta) {
        $stmtAsientos = $pdo->prepare(
            "SELECT s.nombre AS sector, a.fila, a.numero, va.precio
             FROM venta_asiento va
             INNER JOIN asiento a ON a.id_asiento = va.id_asiento
             INNER JOIN sector s ON s.id_sector = a.id_sector
             WHERE va.id_venta = :id_venta
             ORDER BY s.nombre, a.fila, a.numero"
        );
        $stmtAsientos->execute([':id_venta' => $id_venta]);
        $asientosVenta = $stmtAsientos->fetchAll();
    }
}

$activo = 'admin';
$tituloPagina = 'Detalle de Venta';
include __DIR__ . '/includes/header.php';
?>

<div class="container my-4">
  <a href="admin.php" class="text-decoration-none" style="color:var(--cyan);">← Volver al panel</a>

  <?php if (!$venta): ?>
    <div class="tn-card mt-3 text-center py-5">
      <div class="fw-bold fs-5 mb-2">Venta no encontrada</div>
      <div class="text-secondary">No existe ninguna venta con ese ID, o el enlace es inválido.</div>
    </div>
  <?php else: ?>

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mt-3 mb-4">
      <div>
        <h1 class="tn-section-title mb-1">Venta #<?= 1000 + (int) $venta['id_venta'] ?></h1>
        <div class="text-secondary">
          <?= htmlspecialchars($venta['nombre_evento'] ?? '') ?> · <?= htmlspecialchars($venta['nombre_lugar'] ?? '') ?>
        </div>
      </div>
      <span class="badge-vendida">
        <?= $venta['estado'] === 'confirmada' ? '✓ Confirmada' : '✕ Cancelada' ?>
      </span>
    </div>

    <div class="row g-4">
      <div class="col-lg-5">
        <div class="tn-card mb-3">
          <div class="tn-card-label mb-3">DATOS DEL CLIENTE</div>
          <div class="mb-2"><span class="text-secondary">Nombre:</span>
            <?= htmlspecialchars(trim(($venta['nombre'] ?? '') . ' ' . ($venta['apellido'] ?? ''))) ?: '—' ?>
          </div>
          <div class="mb-0"><span class="text-secondary">Teléfono:</span>
            <?= htmlspecialchars($venta['telefono'] ?? '') ?: '—' ?>
          </div>
        </div>

        <div class="tn-card">
          <div class="tn-card-label mb-3">DATOS DE LA COMPRA</div>
          <div class="mb-2"><span class="text-secondary">Fecha:</span>
            <?= (new DateTime($venta['fecha_venta']))->format('d/m/Y H:i') ?>
          </div>
          <div class="mb-2"><span class="text-secondary">Método de pago:</span>
            <?= htmlspecialchars($venta['metodo_pago']) ?>
          </div>
          <div class="mb-0"><span class="text-secondary">Cantidad de entradas:</span>
            <?= count($asientosVenta) ?>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="tn-card">
          <div class="tn-card-label mb-3">ENTRADAS DE LA COMPRA</div>
          <div class="table-responsive">
            <table class="table tn-table mb-0" style="color:var(--text);">
              <thead>
                <tr><th>Sector</th><th>Fila</th><th>Asiento</th><th>Precio</th></tr>
              </thead>
              <tbody>
                <?php foreach ($asientosVenta as $a): ?>
                  <tr>
                    <td><?= htmlspecialchars($a['sector']) ?></td>
                    <td><?= htmlspecialchars($a['fila']) ?></td>
                    <td><?= htmlspecialchars($a['numero']) ?></td>
                    <td>$<?= number_format($a['precio'], 0, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($asientosVenta)): ?>
                  <tr><td colspan="4" class="text-secondary text-center py-4">Sin entradas asociadas</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between mt-3 small">
            <span class="text-secondary">Subtotal</span>
            <span>$<?= number_format($venta['subtotal'], 0, ',', '.') ?></span>
          </div>
          <div class="d-flex justify-content-between mb-2 small">
            <span class="text-secondary">Cargo de servicio</span>
            <span>$<?= number_format($venta['cargo_servicio'], 0, ',', '.') ?></span>
          </div>
          <div class="d-flex justify-content-between fw-bold fs-5 pt-2" style="border-top:1px solid var(--card-border);">
            <span>Total</span>
            <span style="color:var(--cyan);">$<?= number_format($venta['total'], 0, ',', '.') ?></span>
          </div>
        </div>
      </div>
    </div>

  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>