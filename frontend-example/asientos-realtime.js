/**
 * Integración en vivo para la pantalla "Asientos" (imagen 3 del mock).
 * Incluir este script en Asientos.php, después de pintar el mapa inicial
 * (que se carga con GET /api/asientos.php?id_evento=...).
 */

const ID_EVENTO = 1;
const ID_CLIENTE = 1; // en un login real, vendría de la sesión

let asientoSeleccionado = null;

// ---------------------------------------------------------
// 1) Conexión WebSocket: recibe cambios instantáneos de CUALQUIER
//    usuario (incluido uno mismo) y repinta el asiento correspondiente.
// ---------------------------------------------------------
function conectarWebSocket() {
    const ws = new WebSocket('ws://TU_SERVIDOR:8080');

    ws.onopen = () => console.log('Conectado al canal en vivo de TicketNow');

    ws.onmessage = (event) => {
        const data = JSON.parse(event.data);

        if (data.type === 'seat_update' && data.id_evento === ID_EVENTO) {
            pintarAsiento(data.id_asiento, data.estado);
        }

        if (data.type === 'stock_update' && data.id_evento === ID_EVENTO) {
            document.querySelector('#contador-remanentes').textContent =
                `${data.disponibles} entradas restantes`;
        }
    };

    ws.onclose = () => {
        console.warn('Conexión WebSocket perdida, reintentando en 3s...');
        setTimeout(conectarWebSocket, 3000);
    };
}

function pintarAsiento(idAsiento, estado) {
    const el = document.querySelector(`[data-asiento-id="${idAsiento}"]`);
    if (!el) return;

    el.classList.remove('asiento-disponible', 'asiento-reservado', 'asiento-vendido', 'asiento-seleccionado');

    const clase = {
        disponible: 'asiento-disponible', // verde
        reservado:  'asiento-reservado',  // rojo (ocupado momentáneamente por otro)
        vendido:    'asiento-vendido',    // rojo (vendido definitivo)
    }[estado] ?? 'asiento-disponible';

    el.classList.add(clase);
}

// ---------------------------------------------------------
// 2) Click en un asiento: intenta reservarlo. Si otro usuario
//    se lo llevó una fracción de segundo antes, el backend
//    responde 409 y mostramos el aviso "Asiento no disponible".
// ---------------------------------------------------------
async function seleccionarAsiento(idAsiento) {
    try {
        const resp = await fetch('/api/reservar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_asiento: idAsiento,
                id_cliente: ID_CLIENTE,
                id_evento: ID_EVENTO,
            }),
        });

        const data = await resp.json();

        if (resp.status === 409) {
            mostrarAviso('Asiento no disponible', 'Alguien más acaba de reservar esta butaca. Elegí otra.');
            return;
        }

        if (!resp.ok) {
            mostrarAviso('Error', data.error ?? 'No se pudo reservar el asiento');
            return;
        }

        asientoSeleccionado = idAsiento;
        pintarAsiento(idAsiento, 'reservado');
        // acá se habilitaría el botón "Continuar Compra"

    } catch (err) {
        console.error(err);
        mostrarAviso('Error de conexión', 'No se pudo comunicar con el servidor');
    }
}

function mostrarAviso(titulo, mensaje) {
    // Reemplazar por el toast/modal real del diseño (fondo oscuro, acento naranja/rojo)
    alert(`${titulo}: ${mensaje}`);
}

document.addEventListener('DOMContentLoaded', conectarWebSocket);
