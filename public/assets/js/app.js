// =====================================================================
//  SPG — comportamientos comunes de la interfaz
//  1. Separador de miles automático en los campos numéricos
//  2. Confirmación en botones marcados con data-confirmar
//  3. Evita el doble envío accidental de un formulario
//  4. Selector de disponibilidad de la agenda
//  5. Señales de carga
// =====================================================================

// ---------------------------------------------------------------------
//  Señales de carga
//
//  El sistema navega a la vieja usanza: cada clic pide una página nueva y
//  el navegador no muestra NADA hasta que llega la respuesta. Con la base
//  cargada, una lista con filtros o un informe tardan lo suyo, y esa
//  espera en blanco se lee como «se colgó» cuando en realidad está
//  trabajando. Acá se le pone cara a esa espera.
//
//  Se expone como `SPGCarga` para que las pantallas que traen su propio
//  JavaScript (la agenda, el portal) lo usen en vez de inventar el suyo.
//
//  Nada de esto es funcional: si el JS no carga, todo sigue andando igual.
// ---------------------------------------------------------------------
window.SPGCarga = (function () {
  'use strict';

  var barra = null;
  var pendientes = 0;
  var reloj = null;

  function elemento() {
    if (!barra) {
      barra = document.createElement('div');
      barra.className = 'spg-barra-carga';
      barra.setAttribute('role', 'status');
      barra.setAttribute('aria-live', 'polite');
      barra.setAttribute('aria-label', 'Cargando');
      document.body.appendChild(barra);
    }
    return barra;
  }

  // La barra no aparece de inmediato: si la respuesta llega en 200 ms, un
  // parpadeo molesta más que la espera. Recién a partir de ahí hay algo
  // que avisar.
  function mostrar() {
    pendientes++;
    if (reloj) return;
    reloj = setTimeout(function () {
      reloj = null;
      if (pendientes > 0) elemento().classList.add('visible');
    }, 250);
  }

  function ocultar() {
    pendientes = Math.max(0, pendientes - 1);
    if (pendientes > 0) return;
    if (reloj) { clearTimeout(reloj); reloj = null; }
    if (barra) barra.classList.remove('visible');
  }

  function todoListo() {
    pendientes = 0;
    if (reloj) { clearTimeout(reloj); reloj = null; }
    if (barra) barra.classList.remove('visible');
  }

  // Marca un botón como «esperando»: el ícono se convierte en spinner.
  function ocupar(boton) {
    if (!boton || boton.classList.contains('cargando')) return;
    if (!boton.querySelector('i')) boton.classList.add('sin-icono');
    boton.classList.add('cargando');
  }

  function liberar(boton) {
    if (boton) boton.classList.remove('cargando', 'sin-icono');
  }

  // Envuelve un fetch: prende la barra, atenúa el bloque que se va a
  // rehacer y lo devuelve a la normalidad pase lo que pase.
  function envolver(promesa, bloque) {
    mostrar();
    if (bloque) bloque.classList.add('spg-actualizando');

    return promesa.finally(function () {
      ocultar();
      if (bloque) bloque.classList.remove('spg-actualizando');
    });
  }

  // Volver con el botón «atrás» restaura la página desde la caché del
  // navegador, con la barra tal como quedó: hay que apagarla a mano.
  window.addEventListener('pageshow', todoListo);
  window.addEventListener('pagehide', todoListo);

  return {
    mostrar: mostrar, ocultar: ocultar, todoListo: todoListo,
    ocupar: ocupar, liberar: liberar, envolver: envolver,
  };
})();

// ---------------------------------------------------------------------
//  Cuándo se muestra la barra
// ---------------------------------------------------------------------
(function () {
  'use strict';

  // Un enlace que NO va a cambiar de página no tiene que encender nada:
  // descargas, anclas, pestañas nuevas, `mailto:`, y el clic con Ctrl o
  // rueda del mouse, que abre en otra pestaña y deja ésta quieta.
  function navegaDeVerdad(a, ev) {
    if (!a || !a.href) return false;
    if (ev && (ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.altKey || ev.button !== 0)) return false;
    if (a.target && a.target !== '_self') return false;
    if (a.hasAttribute('download')) return false;
    if (a.getAttribute('href').indexOf('#') === 0) return false;
    if (!/^https?:/i.test(a.href)) return false;              // mailto:, tel:, javascript:
    if (a.origin !== window.location.origin) return false;
    // Las exportaciones y el .ics de la cita bajan un archivo y la página se
    // queda donde está: la barra se quedaría prendida para siempre. El `.ics`
    // va acá además del atributo `download` del enlace, para que valga aunque
    // alguien arme el enlace sin el atributo.
    if (/[?&]export=csv\b/.test(a.href)) return false;
    if (/\/mi-cita\/calendario\b/.test(a.href)) return false;

    return true;
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest ? ev.target.closest('a') : null;
    if (!navegaDeVerdad(a, ev)) return;
    // Si algo canceló el clic (una confirmación que se respondió que no),
    // no hay navegación que anunciar.
    setTimeout(function () { if (!ev.defaultPrevented) window.SPGCarga.mostrar(); }, 0);
  });

  // Al enviar un formulario. Va en la fase de captura y ANTES que el
  // bloqueo de doble envío, pero se difiere para leer `defaultPrevented`:
  // si la validación de miles o un `data-confirmar` cortaron el envío, no
  // hay nada esperando.
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!(form instanceof HTMLFormElement)) return;

    setTimeout(function () {
      if (ev.defaultPrevented) return;
      window.SPGCarga.mostrar();
      if (ev.submitter) window.SPGCarga.ocupar(ev.submitter);
    }, 0);
  });
})();

// ---------------------------------------------------------------------
//  Campo de celular
//  Deja escribir solo dígitos y avisa en el momento si el número lleva el
//  0 inicial que no corresponde al usar el código de país (0984… → 984…).
//  La validación de verdad la hace el servidor con telefono_normalizar().
// ---------------------------------------------------------------------
(function () {
  'use strict';
  document.querySelectorAll('.spg-tel').forEach(function (grupo) {
    var sel = grupo.querySelector('.spg-tel-pais');
    var num = grupo.querySelector('.spg-tel-num');
    if (!sel || !num) return;
    var pista = grupo.parentNode.querySelector('.spg-tel-pista');

    function opcion() { return sel.options[sel.selectedIndex]; }

    function refrescarPista() {
      if (!pista) return;
      var o = opcion(), tr = o.getAttribute('data-troncal');
      pista.textContent = 'Para ' + o.textContent.split('·')[1].trim() + ' son '
        + o.getAttribute('data-min') + ' a ' + o.getAttribute('data-max') + ' dígitos'
        + (tr ? ', sin el ' + tr + ' inicial.' : '.');
    }

    function limpiar() {
      var o = opcion(), tr = o.getAttribute('data-troncal');
      var v = num.value.replace(/\D+/g, '');
      // El 0 (o troncal del país) no va cuando se usa el código internacional
      if (tr && v.indexOf(tr) === 0 && v.length > tr.length) {
        v = v.slice(tr.length);
        num.classList.add('spg-tel-ajustado');
        setTimeout(function () { num.classList.remove('spg-tel-ajustado'); }, 900);
      }
      var max = parseInt(o.getAttribute('data-max'), 10) || 15;
      if (v.length > max) v = v.slice(0, max);
      if (num.value !== v) num.value = v;
    }

    num.addEventListener('input', limpiar);
    num.addEventListener('blur', limpiar);
    sel.addEventListener('change', function () { refrescarPista(); limpiar(); });
    refrescarPista();
  });
})();

// ---------------------------------------------------------------------
//  Limpiar un formulario largo
//
//  <button data-limpiar="#formCita">
//
//  `type="reset"` del navegador no alcanza acá: devuelve los campos al valor
//  con el que se dibujó la página —que después de un intento fallido es lo que
//  la persona ya había cargado, o sea nada—, y además NO dispara `change`, así
//  que el selector de agenda se quedaría mostrando los días de la búsqueda
//  anterior. Se vacía a mano y se avisa al selector.
// ---------------------------------------------------------------------
(function () {
  'use strict';
  document.querySelectorAll('[data-limpiar]').forEach(function (b) {
    b.addEventListener('click', function () {
      var form = document.querySelector(b.getAttribute('data-limpiar'));
      if (!form) return;

      form.querySelectorAll('input, select, textarea').forEach(function (c) {
        if (c.name === '_token' || c.type === 'hidden' && c.name === '_borrador') return;
        if (c.type === 'checkbox' || c.type === 'radio') c.checked = false;
        else if (c.tagName === 'SELECT') c.selectedIndex = 0;
        else c.value = '';
      });

      // El selector de agenda escucha los servicios: al quedar todos sin
      // marcar, se vuelve solo a «elegí primero los servicios».
      var srv = form.querySelector('.srv');
      if (srv) srv.dispatchEvent(new Event('change', { bubbles: true }));

      var primero = form.querySelector('select, input:not([type=hidden])');
      if (primero) primero.focus();
    });
  });
})();

// ---------------------------------------------------------------------
//  Selector de disponibilidad
//
//  Reemplaza al campo de fecha y hora libre. Le pregunta al servidor qué días
//  y qué horas quedan libres de verdad para los servicios elegidos, y solo
//  ofrece esos: ya no se puede pedir un horario en el que no hay nadie. El
//  servidor lo vuelve a comprobar al guardar, dentro del candado del
//  procedimiento, así que esto es comodidad, no la autoridad.
//
//  Lo usan las dos pantallas que reservan —Nueva cita y el portal de la
//  clienta—, que hacían lo mismo con dos copias distintas del mismo código.
//  El contenedor declara todo lo que cambia entre una y otra:
//
//    <div data-agenda="{{ route('citas.disponibilidad') }}"
//         data-agenda-sujeto="La cita"
//         data-agenda-boton="#btnAgendar">
//      <div data-agenda-aviso></div>
//      <div data-agenda-dias></div>
//      <div data-agenda-horas></div>
//    </div>
//
//  El profesional se resuelve solo, y no es igual en las dos pantallas:
//  si hay selectores por servicio (`prof_servicio[ID]`, que es como pide la
//  clienta) se consulta a esa persona únicamente cuando TODOS los servicios
//  elegidos la piden a ella; si piden a varias, o alguno quedó en «quien me
//  atienda», se juntan los huecos de todo el equipo y el servidor asigna al
//  reservar. Si no hay esos selectores, manda el combo `id_usuario`.
// ---------------------------------------------------------------------
(function () {
  'use strict';
  var cont = document.querySelector('[data-agenda]');
  if (!cont) return;

  var url     = cont.getAttribute('data-agenda');
  var sujeto  = cont.getAttribute('data-agenda-sujeto') || 'La cita';
  var selBtn  = cont.getAttribute('data-agenda-boton');
  var aviso   = cont.querySelector('[data-agenda-aviso]');
  var diasEl  = cont.querySelector('[data-agenda-dias]');
  var horasEl = cont.querySelector('[data-agenda-horas]');
  var campo   = document.querySelector('[name="fecha_hora"]');
  var btn     = selBtn ? document.querySelector(selBtn) : null;
  var diaElegido = null;

  // Si app.js se cargó a medias, reservar tiene que seguir andando igual: la
  // señal de carga es un adorno, no parte del funcionamiento.
  var SPGCarga = window.SPGCarga || { envolver: function (p) { return p; } };

  function elegidos() {
    return Array.prototype.slice.call(document.querySelectorAll('.srv:checked'))
      .map(function (c) { return c.value; });
  }

  function profesional() {
    // Nueva cita tiene un combo de profesional para toda la cita: ese manda,
    // y es el que le bloquea el bloque más largo en la agenda. Los selectores
    // por servicio de esa misma pantalla reparten el trabajo, pero no cambian
    // a quién se le consultan los huecos.
    var combo = document.querySelector('[name="id_usuario"]');
    if (combo) return combo.value || 0;

    // El portal no tiene ese combo a propósito: cada servicio trae su
    // selector, con «quien me atienda» por defecto.
    var pedidos = elegidos().map(function (id) {
      var sel = document.querySelector('[name="prof_servicio[' + id + ']"]');
      return sel ? sel.value : '0';
    });
    var distintos = pedidos.filter(function (v, i, a) { return a.indexOf(v) === i; });

    return (distintos.length === 1 && distintos[0] !== '0') ? distintos[0] : 0;
  }

  function params(extra) {
    var p = new URLSearchParams();
    elegidos().forEach(function (s) { p.append('servicios[]', s); });
    p.append('id_usuario', profesional());
    // La sucursal elegida viaja con la consulta: el turno es del local, asi
    // que sin ella el servidor contestaria con los horarios de otra sede.
    var suc = document.querySelector('[name="id_sucursal"]');
    if (suc && suc.value) { p.append('sucursal', suc.value); }
    for (var k in (extra || {})) { p.append(k, extra[k]); }

    return p;
  }

  function pedir(extra, destino) {
    return SPGCarga
      .envolver(fetch(url + '?' + params(extra).toString(),
        { headers: { 'Accept': 'application/json' } }), destino)
      .then(function (r) { return r.json(); });
  }

  function cargando(el, texto) {
    el.innerHTML = '<span class="spg-cargando-texto">'
      + '<span class="spg-spinner"></span> ' + texto + '</span>';
  }

  function limpiar() {
    diasEl.innerHTML = '';
    horasEl.innerHTML = '';
    if (campo) campo.value = '';
    if (btn) btn.disabled = true;
  }

  function chip(texto, alTocar, clase) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'spg-chip' + (clase ? ' ' + clase : '');
    b.textContent = texto;
    b.addEventListener('click', function () { alTocar(b); });

    return b;
  }

  // Los días y las horas son dos listas de fichas iguales, una debajo de la
  // otra, y se confundían: no se sabía cuál se estaba tocando. Cada una lleva
  // ahora su rótulo numerado, y las horas además se dibujan distinto (ver
  // `.spg-chip-hora` en app.css).
  function rotulo(caja, texto) {
    var r = document.createElement('span');
    r.className = 'spg-agenda-rotulo';
    r.textContent = texto;
    caja.appendChild(r);
  }

  function marcarUno(caja, boton) {
    Array.prototype.forEach.call(caja.children, function (c) { c.classList.remove('activo'); });
    boton.classList.add('activo');
  }

  function cargarDias() {
    limpiar();
    if (!elegidos().length) {
      aviso.textContent = 'Elegí primero los servicios para ver los horarios disponibles.';
      return;
    }
    // El cálculo mira turnos, citas y ausencias de 60 días: con la agenda
    // cargada tarda, y sin señal parece que el sistema se quedó.
    cargando(aviso, 'Buscando días con lugar…');

    pedir(null, diasEl).then(function (d) {
      if (!d.ok) {
        aviso.textContent = d.motivo || 'No se pudo consultar la agenda.';
        return;
      }
      if (!d.dias || !d.dias.length) {
        // El servidor sabe distinguir «está todo tomado» de «no entra en
        // ningún turno», que se arreglan de formas distintas.
        aviso.textContent = d.motivo
          || ('No quedan días con lugar en los próximos dos meses. '
              + 'Probá con otro profesional o con menos servicios.');
        return;
      }
      aviso.textContent = sujeto + ' dura ' + d.duracion + ' minutos.';
      rotulo(diasEl, '1. Elegí el día');
      d.dias.forEach(function (f) {
        var b = chip(f.split('-').reverse().slice(0, 2).join('/'), function (boton) {
          elegirDia(f, boton);
        });
        b.title = f;
        diasEl.appendChild(b);
      });
    }).catch(function () { aviso.textContent = 'No se pudo consultar la agenda.'; });
  }

  function elegirDia(f, boton) {
    diaElegido = f;
    marcarUno(diasEl, boton);
    cargando(horasEl, 'Buscando horarios…');
    if (campo) campo.value = '';
    if (btn) btn.disabled = true;

    pedir({ fecha: f }, horasEl).then(function (d) {
      horasEl.innerHTML = '';
      if (!d.ok || !d.horas || !d.horas.length) {
        horasEl.textContent = 'Ese día ya no tiene horarios libres.';
        return;
      }
      var p = f.split('-');
      rotulo(horasEl, '2. Elegí la hora del ' + p[2] + '/' + p[1]);
      d.horas.forEach(function (h) {
        horasEl.appendChild(chip(h.hora, function (b) {
          marcarUno(horasEl, b);
          if (campo) campo.value = diaElegido + ' ' + h.hora + ':00';
          if (btn) btn.disabled = false;
        }, 'spg-chip-hora'));
      });
    }).catch(function () { horasEl.textContent = 'No se pudo consultar la agenda.'; });
  }

  // Cambiar de servicio o de profesional cambia los huecos posibles, así que
  // en los dos casos se vuelve a pedir la agenda. Los selectores por servicio
  // solo se escuchan cuando NO hay combo: con combo no cambian la consulta, y
  // escucharlos sería un viaje al servidor para el mismo resultado.
  document.querySelectorAll('.srv').forEach(function (c) {
    c.addEventListener('change', cargarDias);
  });
  var combo = document.querySelector('[name="id_usuario"]');
  if (combo) {
    combo.addEventListener('change', cargarDias);
  } else {
    document.querySelectorAll('[name^="prof_servicio["]').forEach(function (s) {
      s.addEventListener('change', cargarDias);
    });
  }

  cargarDias();
})();

(function () {
  'use strict';

  // ---------------------------------------------------------------
  //  Separador de miles
  //  <input class="input-miles">                → entero (7.000)
  //  <input class="input-miles" data-decimales="2"> → admite 0,5
  //  El servidor recibe "7.000" y lo interpreta con num() de helpers.php,
  //  así que el formato se mantiene aunque el navegador no ejecute el JS.
  // ---------------------------------------------------------------
  function agrupar(entero) {
    entero = entero.replace(/^0+(?=\d)/, '');       // sin ceros a la izquierda
    return entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function formatear(valor, decimales) {
    var negativo = /^-/.test(valor);
    var limpio = valor.replace(/[^\d,]/g, '');
    var partes = limpio.split(',');
    var entero = agrupar(partes[0] || '');
    var salida = entero;
    if (decimales > 0 && partes.length > 1) {
      salida += ',' + partes.slice(1).join('').slice(0, decimales);
    }
    if (salida === '' ) return '';
    return (negativo ? '-' : '') + salida;
  }

  // Cuenta cuántos dígitos hay antes de la posición del cursor, para poder
  // devolverlo al mismo lugar después de reformatear.
  function digitosAntes(texto, pos) {
    var n = 0;
    for (var i = 0; i < pos && i < texto.length; i++) {
      if (/[\d,]/.test(texto[i])) n++;
    }
    return n;
  }

  function posicionDeDigito(texto, n) {
    if (n <= 0) return 0;
    var vistos = 0;
    for (var i = 0; i < texto.length; i++) {
      if (/[\d,]/.test(texto[i])) {
        vistos++;
        if (vistos === n) return i + 1;
      }
    }
    return texto.length;
  }

  function aplicar(el) {
    var decimales = parseInt(el.getAttribute('data-decimales') || '0', 10);
    var antes = el.value;
    var cursor = el.selectionStart;
    var nDig = digitosAntes(antes, cursor === null ? antes.length : cursor);
    var despues = formatear(antes, decimales);
    if (despues === antes) return;
    el.value = despues;
    if (cursor !== null && el.type === 'text') {
      var nuevo = posicionDeDigito(despues, nDig);
      try { el.setSelectionRange(nuevo, nuevo); } catch (e) { /* input sin selección */ }
    }
  }

  function prepararCampos(raiz) {
    (raiz || document).querySelectorAll('.input-miles').forEach(function (el) {
      if (el.dataset.milesListo === '1') return;
      el.dataset.milesListo = '1';
      // type=number no admite el punto de miles: se pasa a texto numérico
      if (el.type === 'number') el.type = 'text';
      el.setAttribute('inputmode', parseInt(el.getAttribute('data-decimales') || '0', 10) > 0 ? 'decimal' : 'numeric');
      el.autocomplete = 'off';
      if (el.value !== '') aplicar(el);
      el.addEventListener('input', function () { aplicar(el); });
      el.addEventListener('blur', function () { aplicar(el); });
    });
  }

  // Convierte "7.000" a 7000 para poder comparar contra data-min / data-max
  function valorNumerico(el) {
    var s = (el.value || '').replace(/\./g, '').replace(',', '.');
    return s === '' ? null : parseFloat(s);
  }

  // ---------------------------------------------------------------
  //  Validación de mínimos y máximos de los campos con miles
  // ---------------------------------------------------------------
  function validarMiles(form) {
    var malo = null;
    form.querySelectorAll('.input-miles').forEach(function (el) {
      if (malo) return;
      var v = valorNumerico(el);
      var min = el.getAttribute('data-min');
      var max = el.getAttribute('data-max');
      if (el.hasAttribute('required') && (v === null || isNaN(v))) {
        malo = { el: el, msg: 'Completá este campo.' };
      } else if (v !== null && !isNaN(v)) {
        if (min !== null && v < parseFloat(min)) malo = { el: el, msg: 'El valor mínimo es ' + min + '.' };
        if (max !== null && v > parseFloat(max)) malo = { el: el, msg: 'El valor máximo es ' + max + '.' };
      }
    });
    if (malo) {
      malo.el.setCustomValidity(malo.msg);
      malo.el.reportValidity();
      setTimeout(function () { malo.el.setCustomValidity(''); }, 50);
      return false;
    }
    return true;
  }

  // ---------------------------------------------------------------
  //  Buscador sobre un <select> largo (clientes, productos, servicios)
  //  <input data-filtra="#selCliente">  filtra las opciones de ese select
  //  <input data-filtra=".chk-servicio"> filtra bloques marcados con esa clase
  // ---------------------------------------------------------------
  function prepararFiltros(raiz) {
    (raiz || document).querySelectorAll('[data-filtra]').forEach(function (caja) {
      if (caja.dataset.filtroListo === '1') return;
      caja.dataset.filtroListo = '1';
      var destino = caja.getAttribute('data-filtra');
      var contador = caja.getAttribute('data-contador');

      var sel = document.querySelector(destino);
      var esSelect = sel && sel.tagName === 'SELECT';
      var originales = esSelect ? Array.prototype.slice.call(sel.options).map(function (o) {
        return { v: o.value, t: o.text, buscar: o.text.toLowerCase() + ' ' + (o.getAttribute('data-buscar') || '').toLowerCase() };
      }) : null;

      function filtrar() {
        var q = caja.value.trim().toLowerCase();
        var visibles = 0;
        if (esSelect) {
          var elegido = sel.value;
          sel.innerHTML = '';
          originales.forEach(function (o) {
            if (q && o.v !== '' && o.buscar.indexOf(q) === -1) return;
            var op = document.createElement('option');
            op.value = o.v; op.text = o.t;
            if (o.v === elegido) op.selected = true;
            sel.appendChild(op);
            if (o.v !== '') visibles++;
          });
        } else {
          document.querySelectorAll(destino).forEach(function (el) {
            var txt = (el.textContent || '').toLowerCase();
            var ok = !q || txt.indexOf(q) !== -1;
            el.style.display = ok ? '' : 'none';
            if (ok) visibles++;
          });
        }
        if (contador) {
          var c = document.querySelector(contador);
          if (c) c.textContent = visibles + (visibles === 1 ? ' resultado' : ' resultados');
        }
      }
      caja.addEventListener('input', filtrar);
      // Enter dentro del buscador no debe enviar el formulario
      caja.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') ev.preventDefault(); });
    });
  }

  // ---------------------------------------------------------------
  //  Confirmación y bloqueo de doble envío
  // ---------------------------------------------------------------
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (!validarMiles(form)) { ev.preventDefault(); return; }

    var enviado = ev.submitter;
    var pregunta = (enviado && enviado.getAttribute('data-confirmar')) || form.getAttribute('data-confirmar');
    if (pregunta && !window.confirm(pregunta)) { ev.preventDefault(); return; }

    // Bloquea el botón para que no se registre dos veces la misma operación
    if (form.dataset.enviando === '1') { ev.preventDefault(); return; }
    form.dataset.enviando = '1';
    setTimeout(function () {
      form.querySelectorAll('button[type=submit], button:not([type])').forEach(function (b) {
        b.disabled = true;
        if (!b.dataset.textoOriginal) b.dataset.textoOriginal = b.innerHTML;
      });
    }, 0);
    // Si la navegación no ocurre (validación del servidor), se rehabilita
    setTimeout(function () {
      form.dataset.enviando = '';
      form.querySelectorAll('button[disabled]').forEach(function (b) { b.disabled = false; });
      // Y se apaga la señal de carga: dejarla girando sobre un formulario
      // que ya no está esperando nada es peor que no haberla puesto.
      form.querySelectorAll('.btn.cargando').forEach(function (b) { window.SPGCarga.liberar(b); });
      window.SPGCarga.todoListo();
    }, 8000);
  });

  // Enlaces que piden confirmación
  document.addEventListener('click', function (ev) {
    var a = ev.target.closest ? ev.target.closest('a[data-confirmar]') : null;
    if (a && !window.confirm(a.getAttribute('data-confirmar'))) ev.preventDefault();
  });

  function iniciar() { prepararCampos(); prepararFiltros(); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }

  window.SPG = { prepararCampos: prepararCampos, prepararFiltros: prepararFiltros, valorNumerico: valorNumerico };
})();

// ---------------------------------------------------------------------
//  Borrador del formulario principal
//
//  Las altas rápidas (crear una sucursal desde «Nuevo usuario», un cliente
//  desde «Nueva cita») mandan su propio POST y vuelven con un redirect, así
//  que la pantalla se dibuja otra vez y todo lo tipeado se perdía: había que
//  cargar de nuevo nombre, apellido, usuario, email…
//
//  El formulario del modal declara de cuál quiere guardar el borrador:
//    <form data-borrador="#formUsuario">
//  y acá se le pega lo escrito en un campo `_borrador`, que el servidor
//  guarda en la sesión y la pantalla vuelve a poner al redibujarse.
// ---------------------------------------------------------------------
(function () {
  'use strict';
  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!(form instanceof HTMLFormElement)) return;
    var sel = form.getAttribute('data-borrador');
    if (!sel) return;
    // Puede haber más de un formulario (las pestañas de Cargar stock): se
    // recorren todos y solo pisa el valor el que tenga algo escrito.
    var principales = document.querySelectorAll(sel);
    if (!principales.length) return;

    var datos = {};
    Array.prototype.forEach.call(principales, function (principal) {
      new FormData(principal).forEach(function (valor, clave) {
        if (clave === '_csrf' || clave === '_borrador') return;
        if (clave.slice(-2) === '[]') {
          clave = clave.slice(0, -2);
          if (!Array.isArray(datos[clave])) datos[clave] = [];
          datos[clave].push(valor);
        } else if (valor !== '' || !(clave in datos)) {
          datos[clave] = valor;
        }
      });
    });

    var campo = form.querySelector('input[name="_borrador"]');
    if (!campo) {
      campo = document.createElement('input');
      campo.type = 'hidden';
      campo.name = '_borrador';
      form.appendChild(campo);
    }
    campo.value = JSON.stringify(datos);
  }, true);   // en captura: corre antes del bloqueo de doble envío
})();

// ---------------------------------------------------------------------
//  Cobro con varios medios de pago
//
//  Una factura puede cobrarse en partes: algo en efectivo, algo con tarjeta,
//  algo con cheque. Cada línea termina siendo un cobro propio en la base.
//  Los campos de tarjeta o de banco aparecen solo cuando el medio elegido
//  los necesita, para no llenar la pantalla de campos que no van.
//
//  Va al final del archivo a propósito: usa window.SPG, que se define arriba.
// ---------------------------------------------------------------------
(function () {
  'use strict';
  document.querySelectorAll('.spg-cobro').forEach(function (caja) {
    var molde   = caja.parentNode.querySelector('.spg-cobro-molde');
    var cont    = caja.querySelector('.spg-cobro-lineas');
    var agregar = caja.querySelector('.spg-cobro-add');
    var resumen = caja.querySelector('.spg-cobro-total');
    var saldo   = parseFloat(caja.getAttribute('data-saldo') || '0');
    if (!molde || !cont) return;

    function aNumero(txt) {
      var s = String(txt || '').replace(/\./g, '').replace(',', '.').replace(/[^\d.]/g, '');
      var n = parseFloat(s);
      return isNaN(n) ? 0 : n;
    }
    function miles(n) { return n.toLocaleString('es-PY', { maximumFractionDigits: 0 }); }

    function recalcular() {
      var suma = 0;
      cont.querySelectorAll('.spg-cobro-monto').forEach(function (i) { suma += aNumero(i.value); });
      var falta = saldo - suma;
      var texto, clase;
      if (suma === 0)        { texto = 'Sin montos cargados.'; clase = 'text-muted-warm'; }
      else if (falta > 0.5)  { texto = 'Suma ' + miles(suma) + ' · queda pendiente ' + miles(falta); clase = 'txt-oro'; }
      else if (falta < -0.5) { texto = 'Suma ' + miles(suma) + ' · se pasa ' + miles(-falta) + ' del saldo'; clase = 'txt-no'; }
      else                   { texto = 'Suma ' + miles(suma) + ' · cubre todo el saldo'; clase = 'txt-ok'; }
      resumen.className = 'spg-cobro-total mt-3 ' + clase;
      resumen.textContent = texto;
      // No dejar enviar si se pasa del saldo
      var f = caja.closest('form');
      var btn = f ? f.querySelector('.modal-footer .btn-oro') : null;
      if (btn) btn.disabled = falta < -0.5;
    }

    // Qué dice el campo «Referencia» según el medio. Decía siempre «Nº de
    // operación, boleta…», y con efectivo eso prometía una boleta que no
    // existe: `nro_boleta` es una columna de `cobro_tarjeta`, no del cobro.
    var PISTA = {
      EFECTIVO: 'Nº de recibo interno (opcional)',
      TARJETA:  'Referencia interna (la boleta va abajo)',
      BANCO:    'Referencia interna (el nº de operación va abajo)',
      CHEQUE:   'Referencia interna (el nº de cheque va abajo)',
      OTRO:     'Nº de operación de la billetera'
    };

    function ajustarExtras(linea) {
      var sel = linea.querySelector('.spg-cobro-metodo');
      var tipo = sel.options[sel.selectedIndex].getAttribute('data-tipo');
      linea.querySelector('.spg-extra-tarjeta').style.display = (tipo === 'TARJETA') ? '' : 'none';
      linea.querySelector('.spg-extra-banco').style.display   = (tipo === 'BANCO' || tipo === 'CHEQUE') ? '' : 'none';

      // Transferencia y cheque comparten la tabla, no los campos: una
      // transferencia no tiene número de cheque y un cheque no tiene número
      // de operación. Se ocultan con `display`, así el input SIGUE en el
      // formulario y los arreglos no se corren de lugar.
      linea.querySelectorAll('[data-solo]').forEach(function (c) {
        var muestra = c.getAttribute('data-solo') === tipo;
        c.style.display = muestra ? '' : 'none';
        if (!muestra) c.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      });

      // Y la fecha se llama distinto en cada uno
      var fe = linea.querySelector('.spg-fecha-banco');
      if (fe) fe.textContent = (tipo === 'CHEQUE') ? 'Fecha del cheque' : 'Fecha de la transferencia';

      var ref = linea.querySelector('[name="referencia[]"]');
      if (ref) ref.placeholder = PISTA[tipo] || 'Referencia (opcional)';

      if (typeof ajustarVuelto === 'function') ajustarVuelto();
    }

    // El vuelto es una cuenta de EFECTIVO: preguntar «¿con cuánto paga?» en una
    // transferencia no tiene sentido, no hay billete ni cambio que dar.
    function ajustarVuelto() {
      var bloque = caja.querySelector('.spg-vuelto-bloque');
      if (!bloque) return;
      var hayEfectivo = false;
      cont.querySelectorAll('.spg-cobro-metodo').forEach(function (s) {
        var op = s.options[s.selectedIndex];
        if (op && op.getAttribute('data-tipo') === 'EFECTIVO') hayEfectivo = true;
      });
      bloque.style.display = hayEfectivo ? '' : 'none';
      if (!hayEfectivo && recibido) { recibido.value = ''; if (vueltoRes) vueltoRes.textContent = ''; }
    }

    function nuevaLinea(monto) {
      var linea = molde.content.firstElementChild.cloneNode(true);
      cont.appendChild(linea);
      if (monto) linea.querySelector('.spg-cobro-monto').value = miles(monto);
      ajustarExtras(linea);
      linea.querySelector('.spg-cobro-metodo').addEventListener('change', function () { ajustarExtras(linea); recalcular(); });
      linea.querySelector('.spg-cobro-monto').addEventListener('input', recalcular);
      linea.querySelector('.spg-cobro-quitar').addEventListener('click', function () {
        if (cont.children.length > 1) { linea.remove(); recalcular(); }
      });
      if (window.SPG) window.SPG.prepararCampos(linea);
      recalcular();
      return linea;
    }

    // ---------------------------------------------------------------
    //  Vuelto
    //
    //  La clienta paga con un billete más grande y hay que devolverle la
    //  diferencia. Es una cuenta de mostrador: NO se guarda nada. Lo que se
    //  registra como cobro sigue siendo el monto de la línea, porque el
    //  vuelto no cambia ni el saldo de la factura ni lo que queda en el
    //  cajón (entra 100.000 y salen 30.000: neto, los 70.000 del cobro).
    // ---------------------------------------------------------------
    var recibido = caja.querySelector('.spg-vuelto-recibido');
    var vueltoRes = caja.querySelector('.spg-vuelto-res');

    function calcularVuelto() {
      if (!recibido || !vueltoRes) return;
      var dado = aNumero(recibido.value);
      var aCobrar = 0;
      cont.querySelectorAll('.spg-cobro-monto').forEach(function (i) { aCobrar += aNumero(i.value); });
      if (dado <= 0 || aCobrar <= 0) { vueltoRes.textContent = ''; vueltoRes.className = 'spg-vuelto-res mt-2'; return; }
      var v = dado - aCobrar;
      if (v < -0.5) {
        vueltoRes.className = 'spg-vuelto-res mt-2 txt-no';
        vueltoRes.textContent = 'Falta ' + miles(-v) + ' para cubrir los ' + miles(aCobrar) + ' que se van a cobrar.';
      } else if (v < 0.5) {
        vueltoRes.className = 'spg-vuelto-res mt-2 txt-ok';
        vueltoRes.textContent = 'Justo: no hay vuelto.';
      } else {
        vueltoRes.className = 'spg-vuelto-res mt-2 spg-vuelto-monto';
        vueltoRes.textContent = 'Vuelto a entregar: ' + miles(v);
      }
    }
    if (recibido) {
      recibido.addEventListener('input', calcularVuelto);
      cont.addEventListener('input', calcularVuelto);
    }

    // Arranca con una sola línea por el saldo completo: el caso más común
    nuevaLinea(saldo);
    agregar.addEventListener('click', function () { nuevaLinea(0); });
  });
})();

// ---------------------------------------------------------------------
//  Casilla maestra de un grupo (Configuración → Roles)
//
//  La matriz de permisos tiene un módulo por bloque y sus submódulos
//  adentro. La casilla del título prende o apaga todo el bloque de una,
//  y refleja lo que hay marcado: llena si están todos, a medio marcar
//  (indeterminate) si hay algunos, vacía si no hay ninguno.
//
//  La maestra NO se envía: no lleva `name`. Lo que se guarda son las
//  casillas de los submódulos, que son las claves que acepta el POST.
// ---------------------------------------------------------------------
(function () {
  var maestras = document.querySelectorAll('[data-marca-todo]');
  if (!maestras.length) return;

  maestras.forEach(function (maestra) {
    var grupo = document.querySelector(maestra.getAttribute('data-marca-todo'));
    if (!grupo) return;
    var hijos = grupo.querySelectorAll('input[type=checkbox]');
    if (!hijos.length) return;

    function reflejar() {
      var n = 0;
      hijos.forEach(function (h) { if (h.checked) n++; });
      maestra.checked = (n === hijos.length);
      maestra.indeterminate = (n > 0 && n < hijos.length);
    }

    maestra.addEventListener('change', function () {
      // Al tocarla desde el estado a medio marcar, prende todo
      var poner = maestra.indeterminate ? true : maestra.checked;
      hijos.forEach(function (h) { if (!h.disabled) h.checked = poner; });
      reflejar();
    });
    grupo.addEventListener('change', reflejar);
    reflejar();
  });
})();

//  Canje y servicio van juntos: el canje NO reemplaza al servicio, lo acompaña.
//  Un servicio canjeado dura lo mismo, lo hace quien lo hace y necesita un hueco
//  libre igual; lo único que cambia es que no se cobra.
//
//  Por eso los dos sentidos se atan solos:
//   · marcar el canje marca su servicio —si no, el vale no se aplica y la
//     clienta pierde los puntos sin recibir nada—;
//   · **marcar el servicio marca su canje**, que es lo que faltaba: había que
//     acordarse de bajar y tildarlo, y quien no lo hacía pagaba un servicio que
//     ya tenía pago.
//
//  Se activa con `data-canjes="#bloque"` en el contenedor de servicios, y cada
//  canje declara su servicio en `data-servicio`.
(function () {
  var bloques = document.querySelectorAll('[data-canjes]');
  if (!bloques.length) { return; }

  bloques.forEach(function (cont) {
    var caja = document.querySelector(cont.dataset.canjes);
    if (!caja) { return; }

    function tildar(el, valor) {
      if (!el || el.checked === valor) { return; }
      el.checked = valor;
      // El evento se dispara a mano porque de él cuelga el recálculo de
      // horarios del selector de agenda.
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    caja.querySelectorAll('.spg-canje').forEach(function (f) {
      var chk = f.querySelector('input[type="checkbox"]');
      var srv = cont.querySelector('.srv[value="' + f.dataset.servicio + '"]');
      if (!chk || !srv) { return; }

      chk.addEventListener('change', function () {
        if (chk.checked) { tildar(srv, true); }
      });

      srv.addEventListener('change', function () {
        // Sólo se auto-marca lo que la clienta puede usar de verdad: un canje
        // escondido —de otra clienta— no se toca.
        if (f.hidden || f.closest('[hidden]')) { return; }
        tildar(chk, srv.checked);
      });

      // Si el servicio ya venía marcado —vuelta de un intento fallido—, el
      // canje tiene que reflejarlo desde el principio.
      if (srv.checked && !f.hidden) { chk.checked = true; }
    });
  });
})();
