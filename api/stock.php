<?php
/**
 * GET /api/stock.php?id_evento=1
 * Devuelve el stock actual. Se usa para pintar el número al cargar
 * la página; las actualizaciones en vivo posteriores llegan por WebSocket.
 */

require __DIR__ . '/../config/db.php';
apiHeaders();

$id_evento = filter_input(INPUT_GET, 'id_evento', FILTER_VALIDATE_INT);
if (!$id_evento) {
    jsonResponse(400, ['error' => 'Falta id_evento']);
}

$pdo = getPDO();
$stmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(a.estado = 'disponible') AS disponibles,
        SUM(a.estado = 'reservado') AS reservados,
        SUM(a.estado = 'vendido') AS vendidos
     FROM asiento a
     INNER JOIN sector s ON s.id_sector = a.id_sector
     WHERE s.id_evento = :id_evento"
);
$stmt->execute([':id_evento' => $id_evento]);
$stock = $stmt->fetch();

jsonResponse(200, [
    'id_evento' => $id_evento,
    'total' => (int) $stock['total'],
    'disponibles' => (int) $stock['disponibles'],
    'reservados' => (int) $stock['reservados'],
    'vendidos' => (int) $stock['vendidos'],
]);
