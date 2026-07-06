<?php
/**
 * GET /api/asientos.php?id_evento=1
 *
 * Devuelve todos los sectores del evento con sus asientos y estado actual.
 * El front usa esto para pintar el mapa al cargar la página; después,
 * las actualizaciones en vivo llegan por WebSocket (no hace falta pedir
 * este endpoint de nuevo salvo al recargar la página).
 */

require __DIR__ . '/../config/db.php';
apiHeaders();

$id_evento = filter_input(INPUT_GET, 'id_evento', FILTER_VALIDATE_INT);
if (!$id_evento) {
    jsonResponse(400, ['error' => 'Falta id_evento']);
}

$pdo = getPDO();

$stmtSectores = $pdo->prepare(
    "SELECT id_sector, nombre, precio, capacidad
     FROM sector WHERE id_evento = :id_evento"
);
$stmtSectores->execute([':id_evento' => $id_evento]);
$sectores = $stmtSectores->fetchAll();

if (!$sectores) {
    jsonResponse(404, ['error' => 'Evento sin sectores configurados']);
}

$stmtAsientos = $pdo->prepare(
    "SELECT id_asiento, fila, numero, estado
     FROM asiento WHERE id_sector = :id_sector
     ORDER BY fila, numero"
);

foreach ($sectores as &$sector) {
    $stmtAsientos->execute([':id_sector' => $sector['id_sector']]);
    $sector['asientos'] = $stmtAsientos->fetchAll();
}
unset($sector);

jsonResponse(200, [
    'id_evento' => $id_evento,
    'sectores'  => $sectores,
]);
