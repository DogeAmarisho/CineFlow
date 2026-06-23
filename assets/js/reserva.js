/**
 * Selector de asientos de reserva.php. No recarga la pagina al
 * seleccionar/quitar asientos, todo se actualiza con JS.
 *
 * reserva.php define antes de cargar este archivo:
 *   window.CFReserva = { precio, maxAsientos }
 */

const PRECIO_UNITARIO = window.CFReserva?.precio      ?? 0;
const MAX_ASIENTOS    = window.CFReserva?.maxAsientos ?? 6;

// guarda los asientos que el usuario va seleccionando: id -> {fila, numero, precio}
const seleccionados = new Map();


// se llama al hacer click en un asiento del mapa
function toggleAsiento(el) {
    const id     = parseInt(el.dataset.id,     10);
    const fila   = el.dataset.fila;
    const numero = el.dataset.numero;

    if (seleccionados.has(id)) {
        seleccionados.delete(id);
        el.classList.remove('seleccionado');
        el.classList.add('libre');
    } else {
        if (seleccionados.size >= MAX_ASIENTOS) {
            mostrarMensajeTemporal(
                `Solo puedes seleccionar hasta ${MAX_ASIENTOS} asientos a la vez.`
            );
            return;
        }
        seleccionados.set(id, { fila, numero, precio: PRECIO_UNITARIO });
        el.classList.add('seleccionado');
        el.classList.remove('libre');
    }

    actualizarResumen();
}


// se llama al apretar la "x" de un asiento en el panel de resumen
function quitarAsiento(id) {
    seleccionados.delete(id);

    const el = document.querySelector(`.asiento[data-id="${id}"]`);
    if (el) {
        el.classList.remove('seleccionado');
        el.classList.add('libre');
    }

    actualizarResumen();
}


// redibuja el panel de la derecha cada vez que cambia la seleccion
function actualizarResumen() {
    const lista        = document.getElementById('lista-seleccionados');
    const vacio        = document.getElementById('resumen-vacio');
    const totalWrap    = document.getElementById('total-wrap');
    const valorTotal   = document.getElementById('valor-total');
    const btnConfirmar = document.getElementById('btn-confirmar');
    const inputsDiv    = document.getElementById('inputs-asientos');

    lista.innerHTML     = '';
    inputsDiv.innerHTML = '';

    if (seleccionados.size === 0) {
        vacio.style.display     = 'block';
        totalWrap.style.display = 'none';
        btnConfirmar.disabled   = true;
        const dc = document.getElementById('datos-cliente');
        const sf = document.getElementById('sep-form');
        if (dc) dc.style.display = 'none';
        if (sf) sf.style.display = 'none';
        return;
    }

    vacio.style.display     = 'none';
    totalWrap.style.display = 'flex';

    // recien aca aparece el formulario de nombre/correo
    const datosCliente = document.getElementById('datos-cliente');
    const sepForm      = document.getElementById('sep-form');
    if (datosCliente) datosCliente.style.display = 'block';
    if (sepForm)      sepForm.style.display      = 'block';

    // el boton de confirmar solo se habilita si ya escribio nombre y correo
    const nombreEl = document.getElementById('nombre-cliente');
    const emailEl  = document.getElementById('email-cliente');
    const nombreOk = nombreEl && nombreEl.value.trim().length > 0;
    const emailOk  = emailEl  && emailEl.value.trim().includes('@');
    btnConfirmar.disabled = !(nombreOk && emailOk);

    let total = 0;

    seleccionados.forEach(({ fila, numero, precio }, id) => {
        total += precio;

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

        // un input oculto por cada asiento, asi llegan todos al hacer submit
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'asientos[]';
        input.value = id;
        inputsDiv.appendChild(input);
    });

    valorTotal.textContent = '$' + total.toLocaleString('es-CL');
}


// pequeño aviso flotante que aparece y se borra solo
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


document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-reserva');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        if (seleccionados.size === 0) {
            e.preventDefault();
            mostrarMensajeTemporal('Selecciona al menos un asiento.');
            return;
        }

        // asi evitamos que manden el formulario dos veces con doble click
        const btn       = document.getElementById('btn-confirmar');
        btn.disabled    = true;
        btn.textContent = 'Procesando...';
    });

    // si escribe o borra el nombre/correo, se vuelve a revisar el boton de confirmar
    ['nombre-cliente', 'email-cliente'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', actualizarResumen);
    });
});
