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
    // Las exportaciones bajan un archivo y la página se queda donde está:
    // la barra se quedaría prendida para siempre.
    if (/[?&]export=csv\b/.test(a.href)) return false;

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
//  Reemplaza al campo de fecha y hora libre. Le pregunta al servidor qué
//  días y qué horas tiene realmente el profesional elegido, y solo ofrece
//  esos: ya no se puede pedir un horario en el que no hay nadie. Sin
//  profesional de preferencia, junta los huecos de todo el equipo.
//
//  El contenedor declara su endpoint:
//    <div data-agenda="citas/disponibilidad" data-base="...">
// ---------------------------------------------------------------------
(function () {
  'use strict';
  var cont = document.querySelector('[data-agenda]');
  if (!cont) return;

  var ruta      = cont.getAttribute('data-agenda');
  var base      = cont.getAttribute('data-base') || '';
  var selProf   = document.querySelector('[name="id_usuario"]');
  var campoFH   = document.querySelector('[name="fecha_hora"]');
  var cajaDias  = cont.querySelector('[data-agenda-dias]');
  var cajaHoras = cont.querySelector('[data-agenda-horas]');
  var aviso     = cont.querySelector('[data-agenda-aviso]');
  var diaElegido = null;

  // En Nueva cita los servicios son casillas; en la pantalla que se abre
  // desde el correo ya vienen fijos, en campos ocultos.
  function servicios() {
    var lista = document.querySelectorAll('input[name="servicios[]"]:checked');
    if (!lista.length) lista = document.querySelectorAll('input[type="hidden"][name="servicios[]"]');
    return Array.prototype.map.call(lista, function (c) { return c.value; });
  }
  function url(extra) {
    var p = servicios().map(function (s) { return 'servicios%5B%5D=' + encodeURIComponent(s); });
    p.push('id_usuario=' + encodeURIComponent(selProf ? (selProf.value || '0') : '0'));
    var tok = cont.getAttribute('data-token');
    if (tok) p.push('t=' + encodeURIComponent(tok));
    if (extra) p.push(extra);
    return base + 'index.php?r=' + ruta + '&' + p.join('&');
  }
  function mostrar(msg) {
    if (aviso) { aviso.textContent = msg || ''; aviso.style.display = msg ? '' : 'none'; }
  }
  function limpiar() {
    cajaDias.innerHTML = ''; cajaHoras.innerHTML = ''; diaElegido = null;
    if (campoFH) campoFH.value = '';
  }

  function cargarDias() {
    limpiar();
    if (!servicios().length) { mostrar('Elegí primero el o los servicios.'); return; }
    mostrar('Buscando días disponibles…');
    fetch(url()).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) { mostrar(d.motivo || 'No se pudo consultar la agenda.'); return; }
      if (!d.dias || !d.dias.length) {
        mostrar('No hay días disponibles con esa combinación. Probá con otro profesional o con menos servicios.');
        return;
      }
      mostrar('');
      var nombres = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
      d.dias.forEach(function (f) {
        var p = f.split('-');
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'spg-chip agenda-dia';
        b.textContent = nombres[new Date(+p[0], +p[1] - 1, +p[2]).getDay()] + ' ' + p[2] + '/' + p[1];
        b.addEventListener('click', function () { elegirDia(f, b); });
        cajaDias.appendChild(b);
      });
    }).catch(function () { mostrar('No se pudo consultar la agenda.'); });
  }

  function elegirDia(fecha, boton) {
    diaElegido = fecha;
    Array.prototype.forEach.call(cajaDias.children, function (c) { c.classList.remove('activo'); });
    boton.classList.add('activo');
    cajaHoras.innerHTML = '';
    if (campoFH) campoFH.value = '';
    fetch(url('fecha=' + fecha)).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok || !d.horas || !d.horas.length) { mostrar('Ese día se ocupó recién. Elegí otro.'); return; }
      mostrar('');
      d.horas.forEach(function (h) {
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'spg-chip agenda-hora';
        b.textContent = h.hora;
        b.addEventListener('click', function () {
          Array.prototype.forEach.call(cajaHoras.children, function (c) { c.classList.remove('activo'); });
          b.classList.add('activo');
          if (campoFH) campoFH.value = diaElegido + 'T' + h.hora;
        });
        cajaHoras.appendChild(b);
      });
    }).catch(function () { mostrar('No se pudo consultar la agenda.'); });
  }

  document.querySelectorAll('input[name="servicios[]"]').forEach(function (c) {
    c.addEventListener('change', cargarDias);
  });
  if (selProf) selProf.addEventListener('change', cargarDias);

  // No dejar enviar sin haber elegido un horario de los ofrecidos
  var form = cont.closest('form');
  if (form) form.addEventListener('submit', function (ev) {
    if (campoFH && !campoFH.value) {
      ev.preventDefault();
      mostrar('Elegí un día y una hora de los disponibles.');
    }
  });

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

    function ajustarExtras(linea) {
      var sel = linea.querySelector('.spg-cobro-metodo');
      var tipo = sel.options[sel.selectedIndex].getAttribute('data-tipo');
      linea.querySelector('.spg-extra-tarjeta').style.display = (tipo === 'TARJETA') ? '' : 'none';
      linea.querySelector('.spg-extra-banco').style.display   = (tipo === 'BANCO' || tipo === 'CHEQUE') ? '' : 'none';
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
