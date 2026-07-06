<?php
/**
 * POST /api/confirmar_compra.php
 * body JSON: {
 *   "id_cliente": 1,
 *   "id_evento": 1,
 *   "asientos": [123, 124],
 *   "metodo_pago": "Tarjeta de Crédito"
 * }
 *
 * Pasa cada asiento de 'reservado' (por ESTE cliente) a 'vendido' dentro
 * de una única transacción. Si algún asiento ya no está reservado por él
 * (por ejemplo, se le venció el hold y otro usuario lo tomó), se aborta
 * TODA la compra y se informa cuáles asientos fallaron, sin cobrar nada.
 *
 * La UNIQUE KEY (id_asiento) en venta_asiento es la última red de
 * seguridad: aunque hubiera un error de lógica más arriba, la base de
 * datos físicamente no permite insertar el mismo asiento dos veces.
 */

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../helpers/ws_notify.php';
apiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['error' => 'Método no permitido']);
}

$body = json_decode(file_get_contents('php://input'), true);
$id_cliente  = filter_var($body['id_cliente'] ?? null, FILTER_VALIDATE_INT);
$id_evento   = filter_var($body['id_evento'] ?? null, FILTER_VALIDATE_INT);
$asientos    = $body['asientos'] ?? [];
$metodo_pago = trim($body['metodo_pago'] ?? '');

if (!$id_cliente || !$id_evento || !is_array($asientos) || count($asientos) === 0 || $metodo_pago === '') {
    jsonResponse(400, ['error' => 'Faltan datos: id_cliente, id_evento, asientos[], metodo_pago']);
}
$asientos = array_map('intval', $asientos);

$pdo = getPDO();

try {
    $pdo->beginTransaction();

    // Traemos precio + estado de cada asiento, bloqueando las filas.
    $in = implode(',', array_fill(0, count($asientos), '?'));
    $stmtCheck = $pdo->prepare(
        "SELECT a.id_asiento, a.estado, a.id_cliente_reserva, s.precio
         FROM asiento a
         INNER JOIN sector s ON s.id_sector = a.id_sector
         WHERE a.id_asiento IN ({$in})
         FOR UPDATE"
    );
    $stmtCheck->execute($asientos);
    $filas = $stmtCheck->fetchAll();

    $fallidos = [];
    $subtotal = 0.0;
    $filasPorId = [];
    foreach ($filas as $f) {
        $filasPorId[$f['id_asiento']] = $f;
    }

    foreach ($asientos as $id_asiento) {
        $f = $filasPorId[$id_asiento] ?? null;
        if (!$f || $f['estado'] !== 'reservado' || (int) $f['id_cliente_reserva'] !== $id_cliente) {
            $fallidos[] = $id_asiento;
        } else {
            $subtotal += (float) $f['precio'];
        }
    }

    if (!empty($fallidos)) {
        $pdo->rollBack();
        jsonResponse(409, [
            'error' => 'Uno o más asientos ya no están disponibles',
            'asientos_fallidos' => $fallidos,
        ]);
    }

    $cargo_servicio = round($subtotal * 0.12, 2);
    $total = $subtotal + $cargo_servicio;

    // Cabecera de venta
    $stmtVenta = $pdo->prepare(
        "INSERT INTO venta (id_cliente, id_evento, subtotal, cargo_servicio, total, metodo_pago, estado)
         VALUES (:id_cliente, :id_evento, :subtotal, :cargo_servicio, :total, :metodo_pago, 'confirmada')"
    );
    $stmtVenta->execute([
        ':id_cliente' => $id_cliente,
        ':id_evento' => $id_evento,
        ':subtotal' => $subtotal,
        ':cargo_servicio' => $cargo_servicio,
        ':total' => $total,
        ':metodo_pago' => $metodo_pago,
    ]);
    $id_venta = $pdo->lastInsertId();

    // Detalle + marcar asientos como vendidos
    $stmtDetalle = $pdo->prepare(
        "INSERT INTO venta_asiento (id_venta, id_asiento, precio) VALUES (:id_venta, :id_asiento, :precio)"
    );
    $stmtMarcar = $pdo->prepare(
        "UPDATE asiento SET estado = 'vendido', version = version + 1
         WHERE id_asiento = :id_asiento AND estado = 'reservado' AND id_cliente_reserva = :id_cliente"
    );

    foreach ($asientos as $id_asiento) {
        $precio = $filasPorId[$id_asiento]['precio'];

        $stmtMarcar->execute([':id_asiento' => $id_asiento, ':id_cliente' => $id_cliente]);
        if ($stmtMarcar->rowCount() === 0) {
            // No debería pasar (ya lo validamos arriba con FOR UPDATE),
            // pero si pasa, abortamos todo por seguridad.
            throw new RuntimeException("El asiento {$id_asiento} cambió de estado durante la confirmación");
        }

        $stmtDetalle->execute([
            ':id_venta' => $id_venta,
            ':id_asiento' => $id_asiento,
            ':precio' => $precio,
        ]);
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[confirmar_compra.php] ' . $e->getMessage());
    jsonResponse(500, ['error' => 'Error interno al confirmar la compra']);
}

// Notificamos en vivo: cada asiento pasa a rojo/vendido para todos,
// y se decrementa el contador global de remanentes.
foreach ($asientos as $id_asiento) {
    notificarWebSocket([
        'type' => 'seat_update',
        'id_asiento' => $id_asiento,
        'estado' => 'vendido',
        'id_evento' => $id_evento,
    ]);
}
notificarStock($pdo, $id_evento);

jsonResponse(200, [
    'ok' => true,
    'id_venta' => $id_venta,
    'subtotal' => $subtotal,
    'cargo_servicio' => $cargo_servicio,
    'total' => $total,
]);
