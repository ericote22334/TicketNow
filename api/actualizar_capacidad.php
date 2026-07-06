<?php
/**
 * POST /api/actualizar_capacidad.php
 * body JSON: { "id_sector": 1, "delta": 1 }   (delta puede ser 1 o -1)
 *
 * Suma: crea un asiento nuevo (estado disponible) en una fila "EXTRA".
 * Resta: elimina un asiento que esté disponible (nunca uno reservado o vendido).
 */

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../helpers/ws_notify.php';
apiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['error' => 'Método no permitido']);
}

$body = json_decode(file_get_contents('php://input'), true);
$id_sector = filter_var($body['id_sector'] ?? null, FILTER_VALIDATE_INT);
$delta = filter_var($body['delta'] ?? null, FILTER_VALIDATE_INT);

if (!$id_sector || !in_array($delta, [1, -1], true)) {
    jsonResponse(400, ['error' => 'Datos inválidos']);
}

$pdo = getPDO();

$stmtSector = $pdo->prepare("SELECT id_evento FROM sector WHERE id_sector = :id_sector");
$stmtSector->execute([':id_sector' => $id_sector]);
$sector = $stmtSector->fetch();
if (!$sector) {
    jsonResponse(404, ['error' => 'Sector no encontrado']);
}

if ($delta === 1) {
    $stmtMax = $pdo->prepare(
        "SELECT COALESCE(MAX(numero),0) AS max_numero FROM asiento WHERE id_sector = :id_sector AND fila = 'EXTRA'"
    );
    $stmtMax->execute([':id_sector' => $id_sector]);
    $siguiente = (int) $stmtMax->fetch()['max_numero'] + 1;

    $stmtInsert = $pdo->prepare(
        "INSERT INTO asiento (id_sector, fila, numero, estado) VALUES (:id_sector, 'EXTRA', :numero, 'disponible')"
    );
    $stmtInsert->execute([':id_sector' => $id_sector, ':numero' => $siguiente]);

} else {
    $stmtBorrar = $pdo->prepare(
        "DELETE FROM asiento WHERE id_sector = :id_sector AND estado = 'disponible' ORDER BY id_asiento DESC LIMIT 1"
    );
    $stmtBorrar->execute([':id_sector' => $id_sector]);
    if ($stmtBorrar->rowCount() === 0) {
        jsonResponse(409, ['error' => 'No hay asientos disponibles para quitar (todos están reservados o vendidos)']);
    }
}

$stmtCapacidad = $pdo->prepare("SELECT COUNT(*) AS capacidad FROM asiento WHERE id_sector = :id_sector");
$stmtCapacidad->execute([':id_sector' => $id_sector]);
$capacidad = (int) $stmtCapacidad->fetch()['capacidad'];

$pdo->prepare("UPDATE sector SET capacidad = :capacidad WHERE id_sector = :id_sector")
    ->execute([':capacidad' => $capacidad, ':id_sector' => $id_sector]);

notificarStock($pdo, (int) $sector['id_evento']);

jsonResponse(200, ['ok' => true, 'capacidad' => $capacidad]);
