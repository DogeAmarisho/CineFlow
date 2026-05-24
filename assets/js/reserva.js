/**
 * ============================================================
 *  CineFlow — reserva.js
 * ============================================================
 *  Propósito : Lógica del selector interactivo de asientos.
 *
 *  DEPENDENCIA:
 *    reserva.php inyecta antes de cargar este archivo:
 *      window.CFReserva = { precio: <float>, maxAsientos: 6 }
 *
 *  FUNCIONES GLOBALES (llamadas desde atributos onclick en PHP):
 *    toggleAsiento(el)   → alterna selección de un asiento
 *    quitarAsiento(id)   → quita un asiento desde el resumen
 *
 *  Autores : Cristóbal Yáñez y Álvaro Hormazabal
 * ============================================================
 */

// ─────────────────────────────────────────────────────────────
//  Configuración (inyectada por reserva.php vía window.CFReserva)
// ─────────────────────────────────────────────────────────────
const PRECIO_UNITARIO = window.CFReserva?.precio      ?? 0;
const MAX_ASIENTOS    = window.CFReserva?.maxAsientos ?? 6;

/** Map<asiento_id, {fila, numero, precio}> — estado del selector */
const seleccionados = new Map();


// ─────────────────────────────────────────────────────────────
//  FUNCIÓN: toggleAsiento
//  Alterna la selección de un asiento en el mapa visual.
//  Actualiza colores, el resumen lateral y los inputs ocultos.
//
//  @param {HTMLElement} el  El div.asiento clicado
// ─────────────────────────────────────────────────────────────
function toggleAsiento(el) {
    const id     = parseInt(el.dataset.id,     10);
    const fila   = el.dataset.fila;
    const numero = el.dataset.numero;

    if (seleccionados.has(id)) {
        // ── Deseleccionar ──────────────────────────────────
        seleccionados.delete(id);
        el.classList.remove('seleccionado');
        el.classList.add('libre');
        if (el.dataset.tipo === 'preferencial') {
            el.classList.add('preferencial');
        }
    } else {
        // ── Seleccionar ────────────────────────────────────
        if (seleccionados.size >= MAX_ASIENTOS) {
            mostrarMensajeTemporal(
                `Solo puedes seleccionar hasta ${MAX_ASIENTOS} asientos a la vez.`
            );
            return;
        }
        seleccionados.set(id, { fila, numero, precio: PRECIO_UNITARIO });
        el.classList.add('seleccionado');
        el.classList.remove('libre', 'preferencial');
    }

    actualizarResumen();
}


// ─────────────────────────────────────────────────────────────
//  FUNCIÓN: quitarAsiento
//  Elimina un asiento de la selección desde el panel lateral
//  y lo desmarca en el mapa.
//
//  @param {number} id  ID del asiento a quitar
// ─────────────────────────────────────────────────────────────
function quitarAsiento(id) {
    seleccionados.delete(id);

    const el = document.querySelector(`.asiento[data-id="${id}"]`);
    if (el) {
        el.classList.remove('seleccionado');
        el.classList.add('libre');
        if (el.dataset.tipo === 'preferencial') {
            el.classList.add('preferencial');
        }
    }

    actualizarResumen();
}


// ─────────────────────────────────────────────────────────────
//  FUNCIÓN: actualizarResumen  (privada, llamada internamente)
//  Sincroniza el panel derecho con el estado de `seleccionados`:
//    · Lista de asientos elegidos con botón "Quitar"
//    · Total a pagar
//    · Inputs hidden del formulario
//    · Estado del botón Confirmar
// ─────────────────────────────────────────────────────────────
function actualizarResumen() {
    const lista        = document.getElementById('lista-seleccionados');
    const vacio        = document.getElementById('resumen-vacio');
    const totalWrap    = document.getElementById('total-wrap');
    const valorTotal   = document.getElementById('valor-total');
    const btnConfirmar = document.getElementById('btn-confirmar');
    const inputsDiv    = document.getElementById('inputs-asientos');

    // Limpiar
    lista.innerHTML     = '';
    inputsDiv.innerHTML = '';

    if (seleccionados.size === 0) {
        vacio.style.display     = 'block';
        totalWrap.style.display = 'none';
        btnConfirmar.disabled   = true;
        return;
    }

    vacio.style.display     = 'none';
    totalWrap.style.display = 'flex';
    btnConfirmar.disabled   = false;

    let total = 0;

    seleccionados.forEach(({ fila, numero, precio }, id) => {
        total += precio;

        // ── Fila en la lista del resumen ───────────────────
        const li = document.createElement('li');
        li.innerHTML = `
            <span>Asiento <strong>${fila}${numero}</strong></span>
            <span>
                $${precio.toLocaleString('es-CL')}
                <button
                    type="button"
                    class="quitar-asiento"
                    onclick="quitarAsiento(${id})"
                    title="Quitar asiento ${fila}${numero}"
                    aria-label="Quitar asiento ${fila}${numero}">
                    ×
                </button>
            </span>`;
        lista.appendChild(li);

        // ── Input hidden para el formulario ────────────────
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'asientos[]';
        input.value = id;
        inputsDiv.appendChild(input);
    });

    valorTotal.textContent = '$' + total.toLocaleString('es-CL');
}


// ─────────────────────────────────────────────────────────────
//  FUNCIÓN: mostrarMensajeTemporal  (privada)
//  Muestra un toast flotante que desaparece tras 2.5 s.
//
//  @param {string} texto
// ─────────────────────────────────────────────────────────────
function mostrarMensajeTemporal(texto) {
    let el = document.getElementById('msg-temporal');

    if (!el) {
        el = document.createElement('div');
        el.id = 'msg-temporal';
        el.setAttribute('role', 'alert');
        el.setAttribute('aria-live', 'polite');
        Object.assign(el.style, {
            position:     'fixed',
            top:          '80px',
            left:         '50%',
            transform:    'translateX(-50%)',
            background:   '#333',
            color:        '#fff',
            padding:      '10px 22px',
            borderRadius: '8px',
            fontSize:     '.9rem',
            zIndex:       '999',
            transition:   'opacity .3s',
            pointerEvents: 'none',
            whiteSpace:   'nowrap',
        });
        document.body.appendChild(el);
    }

    el.textContent   = texto;
    el.style.opacity = '1';
    clearTimeout(el._timer);
    el._timer = setTimeout(() => { el.style.opacity = '0'; }, 2500);
}


// ─────────────────────────────────────────────────────────────
//  EVENTO: submit del formulario de reserva
//  Previene el doble envío y valida que haya al menos un asiento.
// ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-reserva');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        if (seleccionados.size === 0) {
            e.preventDefault();
            mostrarMensajeTemporal('Selecciona al menos un asiento.');
            return;
        }

        // Deshabilitar botón para evitar doble clic
        const btn       = document.getElementById('btn-confirmar');
        btn.disabled    = true;
        btn.textContent = 'Procesando...';
    });
});
