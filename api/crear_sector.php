<?php
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../helpers/ws_notify.php';
apiHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ['error' => 'Método no permitido']);
}

$body = json_decode(file_get_contents('php://input'), true);
$id_evento = filter_var($body['id_evento'] ?? null, FILTER_VALIDATE_INT);
$nombre = trim((string) ($body['nombre'] ?? ''));
$precio = filter_var($body['precio'] ?? null, FILTER_VALIDATE_FLOAT);
$capacidad = filter_var($body['capacidad'] ?? null, FILTER_VALIDATE_INT);

if (!$id_evento || $nombre === '' || $precio === false || !$capacidad || $capacidad < 1) {
    jsonResponse(400, ['error' => 'Faltan datos: id_evento, nombre, precio, capacidad']);
}

$pdo = getPDO();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "INSERT INTO sector (id_evento, nombre, precio, capacidad)
         VALUES (:id_evento, :nombre, :precio, :capacidad)"
    );
    $stmt->execute([
        ':id_evento' => $id_evento,
        ':nombre' => $nombre,
        ':precio' => $precio,
        ':capacidad' => $capacidad,
    ]);

    $id_sector = (int) $pdo->lastInsertId();

    $filas = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $stmtAsiento = $pdo->prepare(
        "INSERT INTO asiento (id_sector, fila, numero, estado)
         VALUES (:id_sector, :fila, :numero, 'disponible')"
    );

    for ($i = 1; $i <= $capacidad; $i++) {
        $fila = $filas[(int) floor(($i - 1) / 8)] ?? 'Z';
        $numero = (($i - 1) % 8) + 1;
        $stmtAsiento->execute([
            ':id_sector' => $id_sector,
            ':fila' => $fila,
            ':numero' => $numero,
        ]);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[crear_sector.php] ' . $e->getMessage());
    jsonResponse(500, ['error' => 'No se pudo crear el sector']);
}

notificarStock($pdo, $id_evento);

jsonResponse(200, [
    'ok' => true,
    'id_sector' => $id_sector,
    'nombre' => $nombre,
    'capacidad' => $capacidad,
]);
