<?php
/**
 * POST /api/cancelar_reserva.php
 * body JSON: { "id_asiento": 123, "id_cliente": 1, "id_evento": 1 }
 *
 * Libera un hold antes de que expire (usuario deselecciona el asiento
 * o abandona el checkout). Sólo el cliente que reservó puede liberarlo.
 */

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../helpers/ws_notify.php';
apiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['error' => 'Método no permitido']);
}

$body = json_decode(file_get_contents('php://input'), true);
$id_asiento = filter_var($body['id_asiento'] ?? null, FILTER_VALIDATE_INT);
$id_cliente = filter_var($body['id_cliente'] ?? null, FILTER_VALIDATE_INT);
$id_evento  = filter_var($body['id_evento'] ?? null, FILTER_VALIDATE_INT);

if (!$id_asiento || !$id_cliente || !$id_evento) {
    jsonResponse(400, ['error' => 'Faltan datos']);
}

$pdo = getPDO();

$stmt = $pdo->prepare(
    "UPDATE asiento
     SET estado = 'disponible', id_cliente_reserva = NULL, reservado_en = NULL, version = version + 1
     WHERE id_asiento = :id_asiento AND estado = 'reservado' AND id_cliente_reserva = :id_cliente"
);
$stmt->execute([':id_asiento' => $id_asiento, ':id_cliente' => $id_cliente]);

if ($stmt->rowCount() === 0) {
    jsonResponse(409, ['error' => 'No se pudo liberar (ya no era tu reserva o cambió de estado)']);
}

notificarWebSocket([
    'type' => 'seat_update',
    'id_asiento' => $id_asiento,
    'estado' => 'disponible',
    'id_evento' => $id_evento,
]);
notificarStock($pdo, $id_evento);

jsonResponse(200, ['ok' => true]);
