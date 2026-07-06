# TicketNow — Backend en tiempo real (PHP + MySQL + WebSockets)

Este backend se conecta sobre tu base de datos `ticketnow` existente y resuelve
los 3 requisitos del enunciado:

1. **Mapa de asientos sincronizado** → WebSocket (`websocket/server.php`)
2. **Contador global de remanentes en vivo** → mismo canal WebSocket (evento `stock_update`)
3. **Bloqueo por concurrencia** → transacciones SQL con `SELECT ... FOR UPDATE` + `UPDATE ... WHERE estado='disponible'` en `api/reservar.php`

## 1. Instalación

### 1.1 Base de datos
```bash
mysql -u root -p ticketnow < database/01_schema_realtime.sql
php database/02_seed_asientos.php   # genera las butacas individuales
```

Esto agrega a tu esquema original:
- `sector.capacidad` (columna nueva)
- tabla `asiento` (butacas individuales, con su estado)
- tabla `venta` (cabecera de compra)
- tabla `venta_asiento` (detalle; con UNIQUE sobre `id_asiento` como
  segunda barrera anti-duplicados a nivel de base de datos)

### 1.2 API REST (Apache/Nginx + PHP 8, como ya tenés)
Colocá las carpetas `api/`, `config/`, `helpers/` en tu servidor web tal cual.
Ajustá las credenciales en `config/db.php`.

### 1.3 Servidor WebSocket (proceso aparte, persistente)
```bash
cd websocket
composer install
php server.php
```
Esto deja escuchando:
- `ws://tu-servidor:8080` → se conectan los navegadores
- `tcp://127.0.0.1:8081` → canal interno, solo el backend PHP le habla (no exponer a internet)

Recomendado correrlo con `supervisor`, `pm2` o un servicio `systemd` para que
se reinicie solo si se cae.

### 1.4 Liberador de reservas vencidas (cron)
```bash
*/1 * * * * php /ruta/al/proyecto/cron/liberar_expiradas.php
```
(o un loop de shell con `sleep 30` si necesitás más frecuencia que el mínimo de 1 min de cron)

## 2. Cómo se resuelve cada requisito

### Mapa sincronizado
- Al entrar a "Asientos", el front pide `GET /api/asientos.php?id_evento=1` (estado inicial).
- Se abre una conexión WebSocket. Cada vez que CUALQUIER usuario reserva,
  compra o libera un asiento, el backend emite `{type:"seat_update", id_asiento, estado}`
  y todos los navegadores conectados repintan esa butaca al instante.

### Contador global
- Cada operación que cambia el estado de un asiento dispara también
  `{type:"stock_update", disponibles, reservados, vendidos}` con el conteo
  recalculado desde la base de datos (no en memoria), así nunca se desincroniza.

### Bloqueo por concurrencia
El punto crítico es `api/reservar.php`. Cuando dos usuarios clickean el mismo
asiento casi al mismo tiempo:

```
Usuario A ──▶ BEGIN; SELECT ... FOR UPDATE (asiento=55)  ──▶ fila bloqueada
Usuario B ──▶ BEGIN; SELECT ... FOR UPDATE (asiento=55)  ──▶ queda esperando...
Usuario A ──▶ UPDATE estado='reservado' WHERE estado='disponible' (1 fila afectada)
Usuario A ──▶ COMMIT                                      ──▶ fila liberada
Usuario B ──▶ (ahora sigue) ve estado='reservado' ──▶ ROLLBACK ──▶ 409 "Asiento no disponible"
```

MySQL/InnoDB garantiza el orden y la exclusión mutua a nivel de fila; no hace
falta ningún lock manual en PHP (que además no funcionaría entre distintos
procesos PHP-FPM). La `UNIQUE KEY` en `venta_asiento.id_asiento` es una
segunda red de seguridad a prueba de bugs de aplicación.

## 3. Estructura de archivos

```
database/
  01_schema_realtime.sql     ← correr una sola vez sobre la BD existente
  02_seed_asientos.php       ← genera las butacas (ajustar cantidad/sectores)
config/
  db.php                     ← conexión PDO + headers JSON/CORS
api/
  asientos.php               ← GET  mapa completo de un evento
  reservar.php                ← POST reservar 1 asiento (bloqueo por concurrencia)
  confirmar_compra.php        ← POST confirmar compra de N asientos reservados
  cancelar_reserva.php         ← POST liberar un hold antes de que expire
  stock.php                   ← GET  contador global (carga inicial)
helpers/
  ws_notify.php                ← puente HTTP → WebSocket
cron/
  liberar_expiradas.php        ← libera holds vencidos (>5 min sin comprar)
websocket/
  server.php                   ← proceso persistente (correr aparte)
  SeatHub.php                  ← lógica de broadcast (Ratchet)
  composer.json
public/
  index.php / asientos.php / comprar.php / admin.php   ← frontend completo
  includes/, assets/                                    ← header/footer, css/js
frontend-example/
  asientos-realtime.js         ← snippet de referencia (ya integrado en public/)
```

## 4. Frontend incluido

La carpeta `public/` tiene el sitio completo, ya conectado a la API y al
WebSocket (mismo look dark/cian/violeta que el mock original):

```
public/
  index.php     ← Inicio: hero del evento, sectores y precios (desde la BD)
  asientos.php  ← Mapa de asientos con bloqueo por concurrencia en vivo
  comprar.php   ← Checkout: datos personales, pago, resumen dinámico
  admin.php     ← Panel admin: métricas, gráfico, stock, últimas ventas
  includes/     ← header.php / footer.php compartidos
  assets/       ← css/js propios (sin dependencias además de Bootstrap 5 CDN)
```

### Cómo levantarlo (todo junto)
1. Poné la carpeta **`ticketnow-backend/` completa** (no solo `public/`) como
   raíz de tu Virtual Host / carpeta servida por Apache o el servidor
   embebido de PHP. Esto es importante: las páginas de `public/` llaman a
   `../api/...`, por lo que `api/` tiene que quedar accesible un nivel
   arriba de `public/`.
   - Con el servidor embebido de PHP, para probar rápido:
     ```bash
     cd ticketnow-backend
     php -S localhost:8000
     ```
     y entrás por `http://localhost:8000/public/index.php`
2. Corré el `server.php` del WebSocket aparte (ver sección 1.3) — las
   páginas se conectan solas a `ws://<mismo-host>:8080`.
3. Las carpetas `config/`, `database/`, `cron/`, `helpers/` y `websocket/`
   ya vienen con un `.htaccess` que bloquea el acceso HTTP directo (solo
   se usan internamente vía `require`/`include`); `api/` y `public/` quedan
   abiertas porque son las que el navegador necesita llamar.

### Usuario/cliente de prueba
Las páginas usan `id_cliente = 1` (el cliente `Juan Pérez` que ya venía en
tu dump) para poder probar todo el flujo sin armar login. Reemplazá esa
constante por el ID de la sesión real cuando integres autenticación.

## 5. Notas
- El charset de las tablas nuevas se mantuvo en `latin1` para ser consistente
  con el dump original; si más adelante migrás todo a `utf8mb4` no hay problema,
  no afecta la lógica de concurrencia.
- `id_cliente` en los ejemplos se pasa "a mano"; en producción reemplazarlo
  por el ID de la sesión autenticada.
- El servidor WebSocket es completamente independiente del servidor web
  (Apache/Nginx): son dos procesos distintos que corren en paralelo.
