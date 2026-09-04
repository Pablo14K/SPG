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
  // **Puede haber VARIOS en la misma pantalla.** Antes se tomaba el primero y
  // listo, que alcanzaba para reservar —hay uno solo—; el portal de la clienta
  // dibuja además un modal por cita para cambiar el dia, y ahi son tantos como
  // citas tenga. Con `querySelector` los demas quedaban sin selector y su campo
  // de fecha era una caja vacia donde habia que adivinar el horario.
  document.querySelectorAll('[data-agenda]').forEach(iniciarAgenda);

  function iniciarAgenda(cont) {
  var url     = cont.getAttribute('data-agenda');
  var sujeto  = cont.getAttribute('data-agenda-sujeto') || 'La cita';
  var selBtn  = cont.getAttribute('data-agenda-boton');
  var aviso   = cont.querySelector('[data-agenda-aviso]');
  var diasEl  = cont.querySelector('[data-agenda-dias]');
  var horasEl = cont.querySelector('[data-agenda-horas]');
  // **El campo se busca dentro del formulario del contenedor**, no en toda la
  // pagina: con varios modales abiertos en el DOM, `document.querySelector`
  // devolvia siempre el del primero y todos escribian ahi.
  var ambito = cont.closest('form') || document;
  var campo   = ambito.querySelector('[name="fecha_hora"]');
  var btn     = selBtn ? ambito.querySelector(selBtn) || document.querySelector(selBtn) : null;

  // Lo que este selector tiene FIJO. Reservar los toma de la pantalla —la
  // clienta va marcando servicios—; reprogramar no pregunta nada de eso: la
  // cita ya tiene sus servicios y su profesional, y lo unico que se elige es
  // cuando. Declarados acá, no hace falta que existan las casillas.
  var fijos = {
    servicios: (cont.getAttribute('data-agenda-servicios') || '').split(',').filter(Boolean),
    profesional: cont.getAttribute('data-agenda-profesional') || '',
    sucursal: cont.getAttribute('data-agenda-sucursal') || ''
  };
  var diaElegido = null;
  // Lo que ya venia elegido, para devolverlo marcado tras un rechazo. Se
  // guarda antes de que nada lo pise.
  var previo = campo && campo.value ? String(campo.value) : '';

  // Si app.js se cargó a medias, reservar tiene que seguir andando igual: la
  // señal de carga es un adorno, no parte del funcionamiento.
  var SPGCarga = window.SPGCarga || { envolver: function (p) { return p; } };

  function elegidos() {
    if (fijos.servicios.length) { return fijos.servicios; }

    return Array.prototype.slice.call(document.querySelectorAll('.srv:checked'))
      .map(function (c) { return c.value; });
  }

  function profesional() {
    if (fijos.profesional) { return fijos.profesional; }

    // Un combo de profesional para toda la cita, si la pantalla lo tiene:
    // ese manda y es el que le bloquea el bloque más largo en la agenda.
    // **Nueva cita ya no lo tiene** desde la 7.67.0 —preguntaba lo mismo que
    // el de cada servicio— así que cae en la rama de abajo, la del portal.
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
    if (fijos.sucursal) { p.append('sucursal', fijos.sucursal); }
    else if (suc && suc.value) { p.append('sucursal', suc.value); }

    // El turno elegido —a mano o deducido del profesional pedido— acota los
    // dias y las horas a esa franja. Sin el, se ofrece todo.
    var turno = document.querySelector('[name="id_turno"]');
    if (turno && turno.value && turno.value !== '0') { p.append('turno', turno.value); }
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

  // **Cada consulta lleva su número de orden.** Marcar dos servicios seguidos
  // dispara dos búsquedas, y las respuestas no vuelven necesariamente en el
  // mismo orden: la vieja llegaba después del `limpiar()` de la nueva y
  // dibujaba SU rótulo, así que quedaban dos «1. Elegí el día» y dos listas de
  // días — una de ellas con los días de la consulta anterior, que es peor que
  // el renglón repetido.
  var consulta = 0;

  function cargarDias() {
    var mia = ++consulta;
    limpiar();
    if (!elegidos().length) {
      aviso.textContent = 'Elegí primero los servicios para ver los horarios disponibles.';
      return;
    }
    // El cálculo mira turnos, citas y ausencias de 60 días: con la agenda
    // cargada tarda, y sin señal parece que el sistema se quedó.
    cargando(aviso, 'Buscando días con lugar…');

    pedir(null, diasEl).then(function (d) {
      if (mia !== consulta) { return; }   // llegó tarde: ya hay otra en curso
      // Se limpia otra vez ANTES de dibujar: `pedir()` deja su spinner adentro
      // del bloque, y el rótulo se agregaba encima en vez de reemplazarlo.
      diasEl.innerHTML = '';
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

    var mia = consulta;
    pedir({ fecha: f }, horasEl).then(function (d) {
      if (mia !== consulta) { return; }   // cambiaron los servicios mientras tanto
      horasEl.innerHTML = '';
      if (!d.ok || !d.horas || !d.horas.length) {
        horasEl.textContent = 'Ese día ya no tiene horarios libres.';
        return;
      }
      var p = f.split('-');
      rotulo(horasEl, '2. Elegí la hora del ' + p[2] + '/' + p[1]);
      d.horas.forEach(function (h) {
        var ch = chip(h.hora, function (b) {
          marcarUno(horasEl, b);
          if (campo) campo.value = diaElegido + ' ' + h.hora + ':00';
          if (btn) btn.disabled = false;
        }, 'spg-chip-hora');
        horasEl.appendChild(ch);

        // La hora que ya venia elegida vuelve marcada, igual que el dia: tras
        // un rechazo el formulario conserva todo menos esto, y desde afuera se
        // lee como que el sistema borro lo que se habia cargado.
        if (previo && previo.slice(11, 16) === h.hora && previo.slice(0, 10) === diaElegido) {
          marcarUno(horasEl, ch);
          if (campo) campo.value = diaElegido + ' ' + h.hora + ':00';
          if (btn) btn.disabled = false;
          previo = '';
        }
      });
    }).catch(function () { horasEl.textContent = 'No se pudo consultar la agenda.'; });
  }

  // Cambiar de servicio o de profesional cambia los huecos posibles, así que
  // en los dos casos se vuelve a pedir la agenda. Los selectores por servicio
  // solo se escuchan cuando NO hay combo: con combo no cambian la consulta, y
  // escucharlos sería un viaje al servidor para el mismo resultado.
  // Con todo fijo no hay nada que escuchar: los servicios y el profesional no
  // se eligen en esta pantalla, así que la agenda se pide una sola vez.
  if (!fijos.servicios.length) {
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
  }

  cargarDias();
  }
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

  //  **El cartel de confirmación es del sistema, no del navegador.**
  //
  //  `window.confirm()` dibuja un cuadro que dice «localhost:8000 dice», con
  //  los botones del sistema operativo y sin una palabra de la identidad del
  //  salón. Para una acción que anula un comprobante o borra un registro, ese
  //  cartel se lee como un error del navegador y no como una pregunta del
  //  sistema — que es justo lo contrario de lo que tiene que transmitir.
  //
  //  Se dibuja con Bootstrap, que ya está cargado, y **cae de vuelta en
  //  `window.confirm()` si no lo estuviera**: una confirmación que no se puede
  //  mostrar no puede convertirse en «seguí adelante sin preguntar».
  function confirmar(texto, alAceptar) {
    if (!window.bootstrap || !window.bootstrap.Modal) {
      if (window.confirm(texto)) { alAceptar(); }
      return;
    }

    var caja = document.getElementById('spgConfirmar');
    if (!caja) {
      caja = document.createElement('div');
      caja.id = 'spgConfirmar';
      caja.className = 'modal fade';
      caja.tabIndex = -1;
      caja.setAttribute('aria-hidden', 'true');
      caja.innerHTML =
        '<div class="modal-dialog modal-dialog-centered modal-sm">'
        + '<div class="modal-content">'
        + '<div class="modal-header"><h5 class="modal-title" style="font-size:1rem">'
        + '<i class="bi bi-question-circle"></i> Confirmá</h5>'
        + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>'
        + '<div class="modal-body" id="spgConfirmarTxt" style="font-size:.9rem"></div>'
        + '<div class="modal-footer">'
        + '<button type="button" class="btn btn-outline-neutro" data-bs-dismiss="modal">Cancelar</button>'
        + '<button type="button" class="btn btn-oro" id="spgConfirmarSi">Sí, seguir</button>'
        + '</div></div></div>';
      document.body.appendChild(caja);
    }

    // **Con id y no con `data-*`.** El modal lo dibuja este script, así que un
    // `data-algo` no aparece en ninguna vista y `AndamiajeTest` lo marca como
    // JS apuntando a un marcado que no existe — que es justo lo que esa prueba
    // tiene que detectar, y no conviene enseñarle a mirar para otro lado.
    caja.querySelector('#spgConfirmarTxt').textContent = texto;
    var modal = window.bootstrap.Modal.getOrCreateInstance(caja);

    // El botón se reemplaza para no acumular escuchas de confirmaciones
    // anteriores: si no, el segundo «sí» dispararía también la primera acción.
    var si = caja.querySelector('#spgConfirmarSi');
    var nuevo = si.cloneNode(true);
    si.parentNode.replaceChild(nuevo, si);
    nuevo.addEventListener('click', function () { modal.hide(); alAceptar(); });

    modal.show();
  }
  window.SPGConfirmar = confirmar;

  // ---------------------------------------------------------------------
  // Los avisos que se dibujan como ventana (`flash($msg, 'modal')`)
  // ---------------------------------------------------------------------
  // Se abren solos: es lo que los distingue de la franja, que se cierra sin
  // leerse. Si Bootstrap no cargó no pasa nada — el marcado deja una franja de
  // respaldo con el mismo texto, así que el aviso nunca desaparece.
  document.querySelectorAll('[data-spg-abrir]').forEach(function (caja) {
    if (!window.bootstrap || !window.bootstrap.Modal) return;
    window.bootstrap.Modal.getOrCreateInstance(caja).show();

    // Recién ahora se saca la franja de respaldo: si la ventana no se pudo
    // abrir, el texto tiene que seguir estando en algún lado.
    var respaldo = document.querySelector('[data-spg-respaldo="' + caja.id + '"]');
    if (respaldo) respaldo.remove();
  });

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (!validarMiles(form)) { ev.preventDefault(); return; }

    var enviado = ev.submitter;
    var pregunta = (enviado && enviado.getAttribute('data-confirmar')) || form.getAttribute('data-confirmar');
    if (pregunta && form.dataset.confirmado !== '1') {
      ev.preventDefault();
      confirmar(pregunta, function () {
        // Se marca antes de reenviar para no volver a preguntar, y se
        // desmarca después: si el servidor rechaza y la persona vuelve a
        // apretar, la pregunta tiene que aparecer de nuevo.
        form.dataset.confirmado = '1';
        if (enviado && enviado.name) {
          // El `submitter` desaparece al enviar por código, y con él el valor
          // del botón que se apretó — que en varias pantallas es el que dice
          // QUÉ se está haciendo.
          var oculto = document.createElement('input');
          oculto.type = 'hidden';
          oculto.name = enviado.name;
          oculto.value = enviado.value;
          form.appendChild(oculto);
        }
        form.requestSubmit ? form.requestSubmit() : form.submit();
        setTimeout(function () { form.dataset.confirmado = ''; }, 100);
      });

      return;
    }

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
    if (!a || a.dataset.confirmado === '1') { return; }
    ev.preventDefault();
    confirmar(a.getAttribute('data-confirmar'), function () {
      a.dataset.confirmado = '1';
      a.click();
      setTimeout(function () { a.dataset.confirmado = ''; }, 100);
    });
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
    // Lo que viene propuesto en la primera linea. Casi siempre es todo lo
    // que falta, pero confirmando una sena es el monto de LA SENA: proponer
    // el total de la cita hacia cobrar de mas con un clic.
    var sugerido = parseFloat(caja.getAttribute('data-sugerido') || '') || saldo;
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
      else if (typeof calcularVuelto === 'function') { calcularVuelto(); }
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

      // **Sólo las líneas en EFECTIVO.** Antes se sumaba el total del cobro,
      // así que un pago partido —100.000 por transferencia y 20.000 en
      // efectivo— comparaba el billete de 50.000 contra los 120.000 y
      // contestaba «falta 70.000», cuando en realidad sobran 30.000 de
      // vuelto. La transferencia no se paga con billetes: no hay cambio que
      // dar por esa parte.
      var aCobrar = 0;
      cont.querySelectorAll('.spg-cobro-linea').forEach(function (l) {
        var sel = l.querySelector('.spg-cobro-metodo');
        var op = sel && sel.options[sel.selectedIndex];
        if (op && op.getAttribute('data-tipo') === 'EFECTIVO') {
          aCobrar += aNumero(l.querySelector('.spg-cobro-monto').value);
        }
      });

      if (dado <= 0 || aCobrar <= 0) { vueltoRes.textContent = ''; vueltoRes.className = 'spg-vuelto-res mt-2'; return; }
      var v = dado - aCobrar;
      if (v < -0.5) {
        vueltoRes.className = 'spg-vuelto-res mt-2 txt-no';
        vueltoRes.textContent = 'Falta ' + miles(-v) + ' para cubrir los ' + miles(aCobrar) + ' en efectivo.';
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
    nuevaLinea(sugerido);
    // El vuelto depende del medio elegido, y el primero ya esta puesto: se
    // ajusta aca, cuando `recibido` y `vueltoRes` ya existen. Llamado solo
    // desde `ajustarExtras` quedaba corriendo antes de que se declararan.
    ajustarVuelto();
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

//  El filtro de bloques de Reportes salió con las pestañas: ahora cada informe
//  es su propia pantalla, así que no hay nada que esconder. Lo detectó
//  `AndamiajeTest`, que es exactamente para esto — un `data-*` que ninguna
//  vista dibuja es JS que no ocurre y nadie se entera.

//  El respaldo de un movimiento de caja se pide sólo cuando la clase elegida lo
//  exige: un retiro de la propietaria no tiene comprobante que adjuntar, y
//  pedírselo sería inventar un papel. Sin este script el bloque se ve siempre,
//  que es el lado seguro — el servidor valida igual.
(function () {
  var sel = document.querySelector('[data-exige]');
  if (!sel) { return; }
  var caja = document.querySelector(sel.getAttribute('data-exige'));
  if (!caja) { return; }

  function ajustar() {
    var op = sel.options[sel.selectedIndex];
    var pide = !!(op && op.getAttribute('data-doc') === '1');
    caja.hidden = !pide;
    caja.querySelectorAll('input').forEach(function (i) {
      if (i.type !== 'file') { i.required = pide; }
      if (!pide) { i.value = ''; }
    });
  }

  sel.addEventListener('change', ajustar);
  ajustar();
})();

//  Al elegir «Devolución al cliente» se elige la NOTA, y el monto sale de ella:
//  el documento manda. Emitir la nota y devolver la plata son dos actos, y si el
//  monto se pudiera tipear volverían a poder quedar dos números distintos para
//  la misma devolución.
(function () {
  var sel = document.querySelector('[data-nota]');
  if (!sel) { return; }
  var caja = document.querySelector(sel.getAttribute('data-nota'));
  var nc = caja && caja.querySelector('select');
  var monto = document.querySelector('#mc_monto');
  if (!caja || !nc || !monto) { return; }

  function ajustar() {
    var op = sel.options[sel.selectedIndex];
    var esDev = !!(op && /Devoluci/.test(op.textContent));
    caja.hidden = !esDev;
    nc.required = esDev;
    monto.readOnly = esDev;
    if (!esDev) { nc.value = ''; }
    ponerMonto();
  }

  function ponerMonto() {
    if (caja.hidden) { return; }
    var op = nc.options[nc.selectedIndex];
    var v = op ? op.getAttribute('data-monto') : '';
    monto.value = v ? Number(v).toLocaleString('es-PY', { maximumFractionDigits: 0 }) : '';
  }

  sel.addEventListener('change', ajustar);
  nc.addEventListener('change', ponerMonto);
  ajustar();
})();

// ---------------------------------------------------------------------
//  El campo Ciudad: combo, con la salida de «Otra».
//
//  El texto libre se esconde salvo que el combo esté en «Otra ciudad…».
//  **Arranca visible en el HTML a propósito**: si este archivo no cargó se
//  ven los dos campos y el formulario sigue siendo usable, que es la regla
//  de siempre — un adorno tiene que poder faltar.
// ---------------------------------------------------------------------
(function () {
  document.querySelectorAll('select.spg-ciudad[data-otra]').forEach(function (sel) {
    var otra = document.querySelector(sel.getAttribute('data-otra'));
    if (!otra) return;

    function reflejar() {
      var libre = sel.value === '__otra';
      otra.style.display = libre ? '' : 'none';
      if (libre) { var i = otra.querySelector('input'); if (i) i.focus(); }
    }
    sel.addEventListener('change', reflejar);
    // Sin `focus` en la primera pasada: robaría el cursor al abrir la pantalla.
    otra.style.display = sel.value === '__otra' ? '' : 'none';
  });
})();

// ---------------------------------------------------------------------
//  Buscador de la pantalla de elegir sucursal.
//
//  Con dos locales sobra; con quince, recorrer la lista a ojo es el trabajo
//  que la pantalla tendría que ahorrar. Filtra sobre el texto ya dibujado,
//  así que **sin JavaScript se ven todas**, que es como estaba antes.
// ---------------------------------------------------------------------
(function () {
  var caja = document.querySelector('[data-filtra-sucursales]');
  if (!caja) return;
  var lista = document.querySelector(caja.getAttribute('data-filtra-sucursales'));
  if (!lista) return;

  var items = Array.prototype.slice.call(lista.children);
  var vacio = document.querySelector('[data-sin-sucursal]');

  caja.addEventListener('input', function () {
    var q = caja.value.trim().toLowerCase();
    var n = 0;
    items.forEach(function (it) {
      var ok = q === '' || it.textContent.toLowerCase().indexOf(q) !== -1;
      it.style.display = ok ? '' : 'none';
      if (ok) n++;
    });
    if (vacio) vacio.style.display = n ? 'none' : '';
  });
})();

// ---------------------------------------------------------------------
//  El combo del profesional aparece con su servicio.
//
//  Con quince servicios en pantalla había quince combos de «quien me
//  atienda» colgando de servicios que la clienta no pidió. Es ruido que
//  compite con lo único que hay que hacer ahí —marcar— y que además
//  sugiere una decisión sobre algo que todavía no se eligió.
//
//  **Arranca visible en el HTML y lo esconde este archivo.** Si `app.js`
//  no cargó se ven todos y la reserva sigue funcionando entera, elegir
//  profesional incluido: misma regla que la salida de la huella y que el
//  texto libre del combo de ciudad.
// ---------------------------------------------------------------------
(function () {
  var combos = document.querySelectorAll('[data-prof-de]');
  if (!combos.length) return;

  combos.forEach(function (sel) {
    var chk = document.querySelector(sel.getAttribute('data-prof-de'));
    if (!chk) return;

    function reflejar() {
      // `display` y no el atributo `hidden`: Bootstrap le pone
      // `display:block` a `.form-select` y le gana al estilo del navegador.
      sel.style.display = chk.checked ? '' : 'none';
    }

    // **El canje marca su servicio solo y despacha `change`**, así que el
    // combo también aparece cuando lo marcó el sistema y no la persona.
    // El valor elegido se conserva al desmarcar: si vuelve a marcarlo,
    // vuelve con su profesional puesto.
    chk.addEventListener('change', reflejar);
    reflejar();
  });
})();

// ---------------------------------------------------------------------
//  El arqueo: la diferencia se ve mientras se cuenta.
//
//  Quien cuenta el cajón tiene que poder ver si cuadra ANTES de confirmar,
//  no enterarse por el aviso de después. Es una cuenta de mostrador y no
//  se guarda: **la diferencia que vale es la que calcula la base**
//  (`fn_caja_diferencia`), con el saldo del momento del cierre.
//
//  Sin `app.js` el campo sigue andando: se escribe el monto y se cierra
//  igual, sólo que sin el adelanto.
// ---------------------------------------------------------------------
(function () {
  var campos = document.querySelectorAll('[data-arqueo]');
  if (!campos.length) return;

  campos.forEach(function (campo) {
    var ref = document.querySelector(campo.getAttribute('data-arqueo'));
    var out = document.querySelector(campo.getAttribute('data-arqueo-salida'));
    if (!ref || !out) return;

    var esperado = parseFloat(ref.getAttribute('data-valor') || '0');

    function reflejar() {
      // Los campos de dinero se muestran con separador de miles, así que se
      // limpian igual que lo hace `num()` en el servidor.
      var txt = campo.value.replace(/\./g, '').replace(',', '.').trim();
      if (txt === '') { out.textContent = ''; out.className = ''; return; }

      var dif = parseFloat(txt) - esperado;
      if (isNaN(dif)) { out.textContent = ''; out.className = ''; return; }

      var abs = Math.abs(dif).toLocaleString('es-PY', { maximumFractionDigits: 0 });
      if (Math.abs(dif) < 0.01) {
        out.textContent = '✓ La caja cuadra.';
        out.className = 'txt-ok';
      } else if (dif > 0) {
        out.textContent = 'Sobran Gs. ' + abs + ' respecto de lo esperado.';
        out.className = 'txt-oro';
      } else {
        out.textContent = 'Faltan Gs. ' + abs + ' respecto de lo esperado.';
        out.className = 'txt-no';
      }
    }

    campo.addEventListener('input', reflejar);
    reflejar();
  });
})();

// ---------------------------------------------------------------------
//  Campos que sólo admiten números: se filtra AL ESCRIBIR.
//
//  El servidor ya rechazaba una cédula con letras —`Persona::error()` lo
//  hace desde la 6.4.0—, pero enterarse después de apretar Guardar, con
//  el formulario entero cargado, es la peor forma de saberlo. Acá el
//  carácter que no corresponde simplemente no entra.
//
//  **La pantalla NO puede ser más estricta que el servidor**, o la
//  persona no podría escribir algo que el sistema sí acepta. Cada juego
//  de caracteres es el de su regla en `Persona::error()`:
//
//    numeros    dígitos pelados      · puntos, cuotas, días, códigos
//    documento  dígitos . espacio -  · cédula        /^[0-9][0-9\.\s-]{2,19}$/
//    ruc        lo anterior + k K    · RUC           …-?[0-9kK]?$/
//    telefono   dígitos + ( ) . - y espacio          /^[+()0-9\.\s-]+$/
//
//  Es una comodidad, no el control: `data-solo` se puede sacar con las
//  herramientas del navegador y el POST igual pasa por el servidor.
// ---------------------------------------------------------------------
(function () {
  var JUEGOS = {
    numeros:   /[^0-9]/g,
    documento: /[^0-9.\s-]/g,
    ruc:       /[^0-9.\s\-kK]/g,
    telefono:  /[^0-9+().\s-]/g
  };

  function filtrar(campo, juego) {
    var malos = JUEGOS[juego];
    if (!malos) return;

    campo.addEventListener('input', function () {
      var limpio = campo.value.replace(malos, '');
      if (limpio === campo.value) return;

      // Se conserva la posición del cursor: sin esto, corregir una letra en
      // el medio de un número tirado el cursor al final en cada tecla.
      var pos = campo.selectionStart;
      var quitados = campo.value.slice(0, pos).replace(malos, '').length;
      campo.value = limpio;
      try { campo.setSelectionRange(quitados, quitados); } catch (e) { /* no todos lo admiten */ }
    });
  }

  document.querySelectorAll('[data-solo]').forEach(function (campo) {
    filtrar(campo, campo.getAttribute('data-solo'));
  });

  // -------------------------------------------------------------------
  //  El alias de transferencia cambia de forma con su tipo.
  //
  //  En Paraguay el alias es un identificador que la persona ya tiene
  //  —cédula, RUC, celular o correo— así que el ejemplo y los caracteres
  //  que se dejan escribir dependen de cuál eligió. Ver «Datos de pago».
  //
  //  **Es una comodidad, no el control**: el servidor valida igual, y sin
  //  `app.js` el campo se sigue pudiendo llenar.
  // -------------------------------------------------------------------
  document.querySelectorAll('[data-alias-tipo]').forEach(function (combo) {
    var campo = document.querySelector(combo.getAttribute('data-alias-tipo'));
    if (!campo) return;

    var base = campo.getAttribute('placeholder') || '';
    var actual = null;

    function aplicar() {
      var op = combo.options[combo.selectedIndex],
          ej = op ? op.getAttribute('data-ph') : '',
          juego = op ? op.getAttribute('data-solo') : '';

      campo.setAttribute('placeholder', ej ? 'Ej: ' + ej : base);
      campo.disabled = combo.value === '';

      // El filtro se engancha una sola vez por juego: `filtrar()` agrega un
      // listener, así que reengancharlo en cada cambio los acumularía.
      if (juego && juego !== actual) {
        filtrar(campo, juego);
        actual = juego;
      }
    }

    combo.addEventListener('change', function () {
      // Al cambiar de tipo lo escrito ya no aplica: un RUC no es un correo.
      if (campo.value !== '') { campo.value = ''; }
      aplicar();
    });
    aplicar();
  });
})();

// El motivo de la diferencia aparece cuando hay diferencia: pedirlo siempre
// haría escribir «ok» todos los días y con eso deja de significar algo.
(function () {
  var dif = document.getElementById('arqueoDif'),
      bloque = document.getElementById('bloqueMotivo');
  if (!dif || !bloque) return;

  new MutationObserver(function () {
    var hay = /Sobran|Faltan/.test(dif.textContent || '');
    bloque.style.display = hay ? '' : 'none';
    var campo = bloque.querySelector('input');
    if (campo) { campo.required = hay; if (!hay) { campo.value = ''; } }
  }).observe(dif, { childList: true, characterData: true, subtree: true });
})();

// «¿Para quién?» aparece con la casilla de «la cita es para otra persona»:
// preguntarlo siempre sería pedir un dato que casi nunca hace falta.
(function () {
  var chk = document.getElementById('paraOtro'),
      bloque = document.getElementById('bloqueParaQuien');
  if (!chk || !bloque) return;

  function reflejar() {
    bloque.style.display = chk.checked ? '' : 'none';
    var campo = bloque.querySelector('input');
    if (campo) { campo.required = chk.checked; if (!chk.checked) { campo.value = ''; } }
  }
  chk.addEventListener('change', reflejar);
  reflejar();
})();

// Cuánta seña pide lo que la clienta va marcando. Se avisa ANTES de reservar:
// es plata que hay que adelantar, y enterarse al final cambia la decisión.
(function () {
  var aviso = document.getElementById('avisoSena');
  if (!aviso) return;
  var monto = document.getElementById('montoSena');

  function reflejar() {
    var t = 0;
    document.querySelectorAll('.srv:checked').forEach(function (c) {
      var b = c.closest('div').querySelector('.badge-estado.e-warn');
      if (!b) return;
      var n = parseFloat((b.textContent || '').replace(/[^0-9]/g, '')) || 0;
      t += n;
    });
    aviso.style.display = t > 0 ? '' : 'none';
    if (monto) { monto.textContent = 'Gs. ' + t.toLocaleString('es-PY', { maximumFractionDigits: 0 }); }
  }

  document.querySelectorAll('.srv').forEach(function (c) { c.addEventListener('change', reflejar); });
  reflejar();
})();

/* ------------------------------------------------------------------
   Registrar atención: cuánto va sumando

   La pantalla mostraba el precio de cada servicio y no sumaba ninguno,
   así que al agregar uno en el sillón no había un número que lo
   reflejara. Con seña la cuenta es otra —lo que se cobra al final es el
   total menos lo que la clienta ya dejó— y por eso se muestran las dos
   cosas: si sólo se mostrara el total, agregar un servicio parecería
   cobrar de más.

   Los precios vienen en `data-precio` de cada casilla, así que no hace
   falta volver a preguntarle al servidor. Si `app.js` no cargó, el
   bloque igual muestra lo que el servidor calculó al dibujar.
   ------------------------------------------------------------------ */
(function () {
  var caja = document.getElementById('sumaAtencion');
  if (!caja) return;

  var sena = parseFloat(caja.getAttribute('data-sena')) || 0;
  var elTotal = caja.querySelector('[data-suma="total"]');
  var elCobrar = caja.querySelector('[data-suma="cobrar"]');
  var casillas = document.querySelectorAll('.srvAt');

  function gs(n) {
    return 'Gs. ' + Math.round(n).toLocaleString('es-PY', { maximumFractionDigits: 0 });
  }

  function sumar() {
    var t = 0;
    casillas.forEach(function (c) {
      if (c.checked) { t += parseFloat(c.getAttribute('data-precio')) || 0; }
    });
    if (elTotal) { elTotal.textContent = gs(t); }
    if (elCobrar) { elCobrar.textContent = gs(Math.max(0, t - sena)); }
  }

  casillas.forEach(function (c) { c.addEventListener('change', sumar); });
  sumar();
})();

/* ------------------------------------------------------------------
   Ayuda contextual: los globos de `<x-ayuda>`.

   Los popovers de Bootstrap son opt-in —hay que instanciarlos— así que
   sin esto el ícono se dibuja y no abre nada. Se hace una sola vez, al
   cargar, sobre todo lo que declare el atributo.

   `trigger: focus` es lo que da el comportamiento pedido: abre al tocar
   el ícono y **cierra al tocar afuera**, sin necesidad de volver a
   tocarlo. Con `click` quedaría abierto hasta el segundo toque.

   Si Bootstrap no cargó no se hace nada y no se rompe nada: el texto
   sigue estando en el `title` del botón, así que el navegador lo muestra
   al pasar el mouse. Es la regla de siempre — lo que adorna puede faltar.
   ------------------------------------------------------------------ */
(function () {
  if (!window.bootstrap || !bootstrap.Popover) { return; }
  document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
    // El `title` está para cuando no hay Bootstrap; con Bootstrap sobra,
    // y si se deja aparece el tooltip nativo ENCIMA del globo.
    el.removeAttribute('title');
    new bootstrap.Popover(el, { container: 'body' });
  });
})();

/* ------------------------------------------------------------------
   Quiénes vienen con la clienta.

   `¿Cuántas personas van?` decía cuántas y nada más, así que el salón
   sabía que llegaban tres y no a quiénes esperar. Al escribir el número
   aparecen los campos de nombre y apellido de cada acompañante.

   **La primera no se pide**: es la clienta que está reservando, y su
   nombre ya lo tiene el sistema. Por eso con 1 no se dibuja nada y los
   campos arrancan en el 2.

   Lo que ya estaba cargado se conserva: tras un rechazo el formulario
   vuelve con los nombres puestos, y subir o bajar el número no borra los
   que ya se habían escrito.

     <input id="personas" name="personas" data-acomp="#bloqueAcomp">
     <div id="bloqueAcomp"></div>
   ------------------------------------------------------------------ */
(function () {
  'use strict';
  document.querySelectorAll('[data-acomp]').forEach(function (campo) {
    var caja = document.querySelector(campo.getAttribute('data-acomp'));
    if (!caja) return;

    var previos = {};
    try { previos = JSON.parse(caja.getAttribute('data-acomp-previos') || '{}'); } catch (e) { previos = {}; }

    function dibujar() {
      var n = parseInt(campo.value, 10);
      if (isNaN(n) || n < 2) { n = 1; }
      if (n > 20) { n = 20; }

      // Lo escrito hasta ahora no se pierde al mover el número.
      caja.querySelectorAll('[data-acomp-orden]').forEach(function (f) {
        var o = f.getAttribute('data-acomp-orden');
        previos[o] = {
          nombre: f.querySelector('[name^="acomp_nombre"]').value,
          apellido: f.querySelector('[name^="acomp_apellido"]').value
        };
      });

      caja.innerHTML = '';
      if (n < 2) { return; }

      var titulo = document.createElement('div');
      titulo.className = 'form-label';
      titulo.textContent = n === 2 ? '¿Quién viene con vos?' : '¿Quiénes vienen con vos?';
      caja.appendChild(titulo);

      for (var i = 2; i <= n; i++) {
        var p = previos[i] || { nombre: '', apellido: '' };
        var fila = document.createElement('div');
        fila.className = 'row g-2 mb-2';
        fila.setAttribute('data-acomp-orden', i);
        fila.innerHTML =
          '<div class="col-6"><input class="form-control form-control-sm" maxlength="60"' +
          ' name="acomp_nombre[' + i + ']" placeholder="Nombre" value=""></div>' +
          '<div class="col-6"><input class="form-control form-control-sm" maxlength="60"' +
          ' name="acomp_apellido[' + i + ']" placeholder="Apellido" value=""></div>';
        fila.querySelector('[name^="acomp_nombre"]').value = p.nombre || '';
        fila.querySelector('[name^="acomp_apellido"]').value = p.apellido || '';
        caja.appendChild(fila);
      }
    }

    campo.addEventListener('input', dibujar);
    campo.addEventListener('change', dibujar);
    dibujar();
  });
})();

/* ------------------------------------------------------------------
   El turno, y el filtro silencioso que evita el choque de horarios.

   El problema: pidiendo a alguien de la mañana para un servicio y a
   alguien de la tarde para otro no hay ningún horario donde las dos
   estén, y la clienta lo descubría recién al buscar día — sin saber
   cuál de sus decisiones fallaba. Explicarlo con un aviso ayuda;
   impedirlo es mejor.

   Cómo queda el turno elegido, en este orden:

     1. Lo que la clienta apretó en los botones. Manda siempre.
     2. Si no apretó nada, el turno del PRIMER profesional que pidió —
        que es la misma decisión tomada de otra forma.
     3. Si no pidió a nadie, ninguno: se ofrece todo.

   Con un turno activo, los combos esconden a quien no trabaja en esa
   franja y la agenda recorta los días y las horas. Volviendo todo a
   «quien me atienda», el filtro se suelta solo: sin nadie pedido no hay
   turno que deducir, y no corresponde esconder nada.

   **Esconder no es el control.** El servidor vuelve a comprobar turno y
   servicio al guardar; esto es para que la clienta no pueda armar una
   combinación que después se le rechace.
   ------------------------------------------------------------------ */
(function () {
  'use strict';
  var caja = document.querySelector('[data-turnos-caja]');
  if (!caja) return;

  var campo   = document.getElementById('idTurno');
  var botones = caja.querySelectorAll('[data-turno]');
  var combos  = function () { return document.querySelectorAll('[name^="prof_servicio["]'); };
  var elegido = '0';        // lo que se apretó a mano
  var deducido = '0';       // lo que sale del profesional pedido

  function turnosDe(opcion) {
    return String(opcion.getAttribute('data-turnos') || '').split(',').filter(Boolean);
  }

  function activo() { return elegido !== '0' ? elegido : deducido; }

  // El turno del primer profesional pedido. Si trabaja en dos, no deduce
  // nada: no hay una respuesta y adivinar escondería opciones válidas.
  function deducir() {
    deducido = '0';
    Array.prototype.some.call(combos(), function (sel) {
      if (!sel.value || sel.value === '0') return false;
      var op = sel.options[sel.selectedIndex];
      var t = turnosDe(op);
      if (t.length === 1) { deducido = t[0]; return true; }

      return false;
    });
  }

  function pintar() {
    var a = activo();
    Array.prototype.forEach.call(botones, function (b) {
      b.classList.toggle('activo', b.getAttribute('data-turno') === a);
    });
  }

  function filtrar() {
    var a = activo();
    Array.prototype.forEach.call(combos(), function (sel) {
      Array.prototype.forEach.call(sel.options, function (op) {
        if (!op.value || op.value === '0') { op.hidden = false; return; }
        var t = turnosDe(op);
        // Sin turnos cargados no se esconde: es el criterio permisivo de
        // siempre — quien no tiene nada cargado no queda fuera por eso.
        op.hidden = (a !== '0' && t.length > 0 && t.indexOf(a) === -1);
      });
      // Si lo que estaba elegido quedó escondido, se suelta: dejarlo
      // seleccionado mandaría al servidor justo lo que se quiso evitar.
      if (sel.selectedIndex >= 0 && sel.options[sel.selectedIndex].hidden) {
        sel.value = '0';
      }
    });
  }

  function refrescar(volverAPedir) {
    deducir();
    if (campo) { campo.value = activo(); }
    pintar();
    filtrar();
    // La agenda depende del turno, así que se vuelve a pedir. El selector
    // escucha los combos por su cuenta; acá se fuerza cuando cambió el
    // turno sin que ningún combo se haya tocado.
    if (volverAPedir) {
      var srv = document.querySelector('.srv:checked');
      if (srv) { srv.dispatchEvent(new Event('change')); }
    }
  }

  Array.prototype.forEach.call(botones, function (b) {
    b.addEventListener('click', function () {
      elegido = b.getAttribute('data-turno');
      refrescar(true);
    });
  });

  document.addEventListener('change', function (e) {
    if (e.target && e.target.name && e.target.name.indexOf('prof_servicio[') === 0) {
      refrescar(false);
    }
  });

  refrescar(false);
})();
