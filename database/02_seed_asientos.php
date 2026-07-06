<?php
/**
 * Genera asientos individuales para un sector.
 * Uso: php 02_seed_asientos.php
 *
 * Por defecto genera el sector de ejemplo (id_sector=1): 5 filas (A-E) x 8 asientos = 40 butacas,
 * que coincide con la capacidad cargada en 01_schema_realtime.sql.
 * Ajustá $sectores según los sectores reales de tu evento.
 */

require __DIR__ . '/../config/db.php';

$sectores = [
    // id_sector => ['filas' => n, 'asientos_por_fila' => n]
    1 => ['filas' => 5, 'asientos_por_fila' => 8],
];

$pdo = getPDO();

foreach ($sectores as $id_sector => $conf) {
    $letras = range('A', chr(ord('A') + $conf['filas'] - 1));
    $insertados = 0;

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO asiento (id_sector, fila, numero, estado)
         VALUES (:id_sector, :fila, :numero, 'disponible')"
    );

    foreach ($letras as $fila) {
        for ($numero = 1; $numero <= $conf['asientos_por_fila']; $numero++) {
            $stmt->execute([
                ':id_sector' => $id_sector,
                ':fila' => $fila,
                ':numero' => $numero,
            ]);
            $insertados += $stmt->rowCount();
        }
    }

    echo "Sector {$id_sector}: {$insertados} asientos creados.\n";
}

echo "Listo.\n";
