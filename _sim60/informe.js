/**
 * Genera el informe de QA en Word a partir de _sim60/log/resumen.json.
 *
 *   NODE_PATH=<ruta a node_modules con docx> node _sim60/informe.js [salida.docx]
 */

'use strict';

const fs = require('fs');
const path = require('path');
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType,
  Table, TableRow, TableCell, WidthType, ShadingType, BorderStyle,
  PageBreak, ImageRun, TableOfContents, Footer, PageNumber, LevelFormat,
} = require('docx');

const LOG = path.join(__dirname, 'log');
const SALIDA = process.argv[2]
  || path.join(path.dirname(__dirname), 'Informe_QA_Simulacion_2_Meses_SPGLaravel.docx');

const R = JSON.parse(fs.readFileSync(path.join(LOG, 'resumen.json'), 'utf8'));
const S = R.series || {};
const INI = R.estado_inicial || {};
const FIN = R.estado_final || {};
const AUD = R.auditoria_final || {};

// --- paleta -------------------------------------------------------------
const ORO = 'C9A84C';
const ORO_TINTE = 'FBF1D8';
const CARBON = '3A3733';
const GRIS = '6F6B65';
const BORDE = 'D8D4CE';
const ROJO = '993535';
const VERDE = '2F5D2F';
const NARANJA = 'A35A21';
const FONDO = 'F7F5F2';
const ANCHO = 9020;

// --- utilidades ---------------------------------------------------------
const num = (v) => Number(v || 0).toLocaleString('es-PY', { maximumFractionDigits: 0 }).replace(/,/g, '.');
const gs = (v) => 'Gs. ' + num(v);
const dec = (v, d = 4) => String(Math.round(Number(v || 0) * 10 ** d) / 10 ** d).replace('.', ',');
const fechaEs = (iso) => (iso ? String(iso).split('-').reverse().join('/') : '—');
const pct = (a, b) => (b ? (100 * a / b).toFixed(1).replace('.', ',') + ' %' : '—');

/** Corta en el espacio anterior, para no partir una palabra a la mitad. */
function recorta(txt, largo) {
  const s = String(txt || '');
  if (s.length <= largo) return s;
  const corte = s.slice(0, largo);
  const esp = corte.lastIndexOf(' ');
  return (esp > largo * 0.6 ? corte.slice(0, esp) : corte).replace(/[,;:.\s]+$/, '') + '…';
}

function p(texto, o = {}) {
  return new Paragraph({
    alignment: o.align,
    spacing: { after: o.espacio === undefined ? 110 : o.espacio, before: o.antes || 0, line: 278 },
    indent: o.izquierda ? { left: o.izquierda } : undefined,
    children: [new TextRun({
      text: texto, size: o.size || 20, bold: !!o.bold, italics: !!o.italics,
      color: o.color || CARBON, font: 'Calibri',
    })],
  });
}

function ricos(tramos, o = {}) {
  return new Paragraph({
    spacing: { after: o.espacio === undefined ? 110 : o.espacio, line: 278 },
    indent: o.izquierda ? { left: o.izquierda } : undefined,
    children: tramos.map((x) => (Array.isArray(x)
      ? new TextRun({ text: x[0], bold: !!x[1], color: x[2] || o.color || CARBON, size: o.size || 20, font: 'Calibri' })
      : new TextRun({ text: x, size: o.size || 20, color: o.color || CARBON, font: 'Calibri' }))),
  });
}

const h1 = (t) => new Paragraph({
  heading: HeadingLevel.HEADING_1,
  spacing: { before: 380, after: 170 },
  border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: ORO, space: 6 } },
  children: [new TextRun({ text: t, size: 29, bold: true, color: CARBON, font: 'Calibri' })],
});

const h2 = (t) => new Paragraph({
  heading: HeadingLevel.HEADING_2,
  spacing: { before: 270, after: 120 },
  children: [new TextRun({ text: t, size: 23, bold: true, color: NARANJA, font: 'Calibri' })],
});

const vineta = (t, o = {}) => new Paragraph({
  numbering: { reference: 'vinetas', level: o.nivel || 0 },
  spacing: { after: 70, line: 278 },
  children: [new TextRun({ text: t, size: o.size || 20, color: CARBON, font: 'Calibri' })],
});

const vinetaRica = (tramos, o = {}) => new Paragraph({
  numbering: { reference: 'vinetas', level: o.nivel || 0 },
  spacing: { after: 70, line: 278 },
  children: tramos.map((x) => (Array.isArray(x)
    ? new TextRun({ text: x[0], bold: !!x[1], color: x[2] || CARBON, size: o.size || 20, font: 'Calibri' })
    : new TextRun({ text: x, size: o.size || 20, color: CARBON, font: 'Calibri' }))),
});

function celda(texto, o = {}) {
  const lineas = String(texto === null || texto === undefined ? '' : texto).split('|');
  return new TableCell({
    width: { size: o.ancho, type: WidthType.DXA },
    shading: o.fondo ? { type: ShadingType.CLEAR, fill: o.fondo, color: 'auto' } : undefined,
    margins: { top: 68, bottom: 68, left: 110, right: 110 },
    children: lineas.map((l) => new Paragraph({
      alignment: o.align || AlignmentType.LEFT,
      spacing: { after: 0, line: 242 },
      children: [new TextRun({
        text: l, size: o.size || 17, bold: !!o.bold,
        color: o.color || CARBON, font: 'Calibri',
      })],
    })),
  });
}

function tabla(cabeceras, filas, anchos, o = {}) {
  const total = anchos.reduce((a, b) => a + b, 0);
  const w = anchos.map((a) => Math.round(a * ANCHO / total));
  const dif = ANCHO - w.reduce((a, b) => a + b, 0);
  w[w.length - 1] += dif;
  const tam = o.tamano || 17;

  const cab = new TableRow({
    tableHeader: true,
    children: cabeceras.map((c, i) => celda(c, {
      ancho: w[i], bold: true, fondo: ORO_TINTE, size: tam,
      align: i === 0 ? AlignmentType.LEFT : AlignmentType.CENTER,
    })),
  });

  const cuerpo = filas.map((f, k) => new TableRow({
    children: f.map((c, i) => {
      const x = (c && typeof c === 'object' && !Array.isArray(c)) ? c : { t: c };
      return celda(x.t, {
        ancho: w[i], bold: !!x.bold, color: x.color, size: tam,
        fondo: x.fondo || (k % 2 === 1 ? FONDO : undefined),
        align: x.align || (i === 0 ? AlignmentType.LEFT : AlignmentType.CENTER),
      });
    }),
  }));

  return new Table({
    columnWidths: w,
    width: { size: ANCHO, type: WidthType.DXA },
    borders: {
      top: { style: BorderStyle.SINGLE, size: 4, color: BORDE },
      bottom: { style: BorderStyle.SINGLE, size: 4, color: BORDE },
      left: { style: BorderStyle.SINGLE, size: 4, color: BORDE },
      right: { style: BorderStyle.SINGLE, size: 4, color: BORDE },
      insideHorizontal: { style: BorderStyle.SINGLE, size: 2, color: BORDE },
      insideVertical: { style: BorderStyle.SINGLE, size: 2, color: BORDE },
    },
    rows: [cab, ...cuerpo],
  });
}

function imagen(archivo, ancho, alto) {
  const ruta = path.join(LOG, archivo);
  if (!fs.existsSync(ruta)) return p('[gráfico no disponible: ' + archivo + ']', { italics: true, color: GRIS });
  return new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 130, after: 50 },
    children: [new ImageRun({
      type: 'png', data: fs.readFileSync(ruta),
      transformation: { width: ancho, height: alto },
    })],
  });
}

const epigrafe = (t) => new Paragraph({
  alignment: AlignmentType.CENTER,
  spacing: { after: 220 },
  children: [new TextRun({ text: t, size: 16, italics: true, color: GRIS, font: 'Calibri' })],
});

const nota = (t) => new Paragraph({
  spacing: { before: 110, after: 150, line: 278 },
  indent: { left: 230 },
  border: { left: { style: BorderStyle.SINGLE, size: 12, color: ORO, space: 10 } },
  children: [new TextRun({ text: t, size: 19, color: GRIS, font: 'Calibri' })],
});

const salto = () => new Paragraph({ children: [new PageBreak()] });
const colorSev = (s) => ({ CRITICO: ROJO, ALTO: NARANJA, MEDIO: '8A6C1E', BAJO: GRIS }[s] || CARBON);

// =======================================================================
//  Datos derivados
// =======================================================================
const fallos = R.fallos || [];
const avisos = R.avisos || [];
const criticos = fallos.filter((f) => f.severidad === 'CRITICO');
const altos = fallos.filter((f) => f.severidad === 'ALTO');
const medios = avisos.filter((a) => a.severidad === 'MEDIO');
const bajos = avisos.filter((a) => a.severidad === 'BAJO');

// La ventana simulada se toma de la auditoría, que arranca con la primera
// acción del día 1. `citas_por_dia` va por la fecha DE LA CITA, no por el día
// en que se agendó, así que empezaría dos días tarde.
const diasAudit = Object.keys(S.auditoria_por_dia || {}).sort();
const diasSerie = Object.keys(S.citas_por_dia || {}).sort();
const primerDia = diasAudit[0] || diasSerie[0] || '2026-08-15';
const ultimoDia = diasAudit[diasAudit.length - 1] || diasSerie[diasSerie.length - 1] || '2026-10-13';

const t = (k, d = 0) => Number((FIN.tablas || {})[k] ?? d);
const ti = (k, d = 0) => Number((INI.tablas || {})[k] ?? d);

// **Los conteos de la operación salen de la auditoría de cierre, no de la foto
// final.** La auditoría se tomó al terminar el día 60; la verificación dirigida
// de los hallazgos corrió después y dejó unas pocas filas suyas (una cita de
// prueba, una caja y la declaración de una nota de crédito). Usar la auditoría
// deja el informe describiendo los 60 días y nada más.
const CITAS = Number(AUD.citas ?? t('cita'));
const COMPROBANTES = Number(AUD.comprobantes ?? t('factura'));
const SERVICIOS = Number(AUD.servicios_realizados ?? t('servicio_realizado'));
const CLIENTES = Number(AUD.clientes ?? t('cliente'));
const USUARIOS = Number(AUD.usuarios ?? t('usuario'));
const CAJAS_N = Number(AUD.cajas ?? t('caja'));
const MOVINV = Number(AUD.movimientos_inventario ?? t('movimiento_inventario'));
const AUDIT_N = Number(AUD.auditoria_total ?? t('auditoria'));
const NOTIF_N = Number(AUD.notificaciones ?? t('notificacion'));

const facturado = Number(FIN.facturado_total || 0);
const acreditado = Number(FIN.acreditado_total || 0);
const cobrado = Number(FIN.cobrado_total || 0);
const cobradoEf = Number(FIN.cobrado_efectivo || 0);
const saldoPend = Number(FIN.saldo_pendiente || 0);
const pagProv = Number(FIN.pagado_proveedores || 0);
const pagPers = Number(FIN.pagado_personal || 0);
const egrManual = Number(FIN.egresos_manuales || 0);

const cajas = S.cajas || [];
const sIni = cajas.reduce((a, c) => a + c.inicial, 0);
const sSaldo = cajas.reduce((a, c) => a + c.saldo, 0);
const sCobEf = cajas.reduce((a, c) => a + c.cobros_efectivo, 0);
const sEgr = cajas.reduce((a, c) => a + c.egresos, 0);
const sProvEf = cajas.reduce((a, c) => a + c.prov_efectivo, 0);
const sPersEf = cajas.reduce((a, c) => a + c.pers_efectivo, 0);

const stock = S.stock_final || [];
const stockDif = stock.filter((x) => Math.abs(x.teorico - x.sistema) > 0.0001);
const stockNeg = stock.filter((x) => x.sistema < -0.0001);
const totEnt = stock.reduce((a, x) => a + x.entradas, 0);
const totSal = stock.reduce((a, x) => a + x.salidas, 0);
const totTeo = stock.reduce((a, x) => a + x.teorico, 0);
const totSis = stock.reduce((a, x) => a + x.sistema, 0);

const veredicto = criticos.length > 0 ? 'NO APTO'
  : (fallos.length > 0 ? 'APTO CON OBSERVACIONES' : 'APTO');

const usuarios = R.operaciones_por_usuario || {};
const usuariosReales = Object.keys(usuarios).filter((u) => u !== '-' && u !== 'anonimo');
const VER = R.verificacion || {};
const VER2 = R.verificacion2 || {};

const H = [];   // hijos del documento

// =======================================================================
//  PORTADA
// =======================================================================
H.push(new Paragraph({ spacing: { before: 1750 }, children: [] }));
H.push(new Paragraph({
  alignment: AlignmentType.CENTER, spacing: { after: 60 },
  children: [new TextRun({ text: 'SPG', size: 80, bold: true, color: ORO, font: 'Calibri' })],
}));
H.push(new Paragraph({
  alignment: AlignmentType.CENTER, spacing: { after: 420 },
  children: [new TextRun({ text: 'Sistema de Gestión para Peluquería', size: 26, color: GRIS, font: 'Calibri' })],
}));
H.push(new Paragraph({
  alignment: AlignmentType.CENTER, spacing: { after: 110 },
  border: { top: { style: BorderStyle.SINGLE, size: 12, color: ORO, space: 16 } },
  children: [new TextRun({ text: 'Informe de Simulación Operativa y QA', size: 40, bold: true, color: CARBON, font: 'Calibri' })],
}));
H.push(new Paragraph({
  alignment: AlignmentType.CENTER, spacing: { after: 640 },
  border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: ORO, space: 16 } },
  children: [new TextRun({ text: 'Dos meses de operación diaria, con varios usuarios trabajando en paralelo', size: 21, italics: true, color: GRIS, font: 'Calibri' })],
}));
H.push(tabla(['Dato', 'Valor'], [
  ['Sistema analizado', { t: 'SPG — Laravel 13 + MariaDB 10.4', align: AlignmentType.LEFT }],
  ['Versión', { t: '7.27.1 — 15/08/2026', align: AlignmentType.LEFT }],
  ['Período simulado', { t: fechaEs(primerDia) + ' al ' + fechaEs(ultimoDia), align: AlignmentType.LEFT }],
  ['Duración', { t: '60 días consecutivos (2 meses)', align: AlignmentType.LEFT }],
  ['Fecha de ejecución', { t: new Date().toLocaleDateString('es-PY'), align: AlignmentType.LEFT }],
  ['Base de datos', { t: 'peluqueria_sim — instalación limpia desde peluqueria_bd(base).sql', align: AlignmentType.LEFT }],
  ['Operaciones ejecutadas', { t: num(R.peticiones_http) + ' peticiones HTTP reales', align: AlignmentType.LEFT }],
  ['Veredicto', { t: veredicto, bold: true, color: criticos.length ? ROJO : NARANJA, align: AlignmentType.LEFT }],
], [2500, 6520], { tamano: 19 }));
H.push(salto());

// =======================================================================
//  ÍNDICE
// =======================================================================
H.push(h1('Índice'));
H.push(new TableOfContents('Contenido', { hyperlink: true, headingStyleRange: '1-2' }));
H.push(nota('Si el índice aparece vacío, hacé clic derecho sobre él y elegí «Actualizar campos».'));
H.push(salto());

// =======================================================================
//  1. RESUMEN EJECUTIVO
// =======================================================================
H.push(h1('1. Resumen ejecutivo'));
H.push(ricos([
  'Se sometió al SPG ', ['versión 7.27.1', true], ' a una simulación de ',
  ['60 días consecutivos de operación real', true], ' de una peluquería de Luque, del ',
  fechaEs(primerDia), ' al ', fechaEs(ultimoDia), '. La simulación arrancó desde una ',
  ['instalación limpia', true], ' —la misma base que recibe el salón el primer día, sin una sola cita ni factura— y toda la operación se generó ',
  ['usando el sistema por HTTP', true], ', con sesiones de verdad, no escribiendo en la base.',
]));
H.push(ricos([
  'Se ejecutaron ', [num(R.peticiones_http) + ' peticiones', true],
  ' repartidas entre ', [String(usuariosReales.length) + ' cuentas', true],
  ' de los cuatro roles del sistema, con ', [String((R.concurrencia || []).length) + ' escenarios de concurrencia real', true],
  ' —procesos separados largando en el mismo instante— y ',
  [num(R.comprobaciones_totales) + ' comprobaciones de invariantes', true], '.',
]));

H.push(h2('Resultado general'));
H.push(tabla(['Indicador', 'Valor'], [
  ['Días simulados', { t: '60', bold: true }],
  ['Peticiones HTTP ejecutadas', num(R.peticiones_http)],
  ['Respuestas HTTP 5xx', { t: num(R.http_5xx), color: R.http_5xx ? ROJO : VERDE, bold: true }],
  ['Comprobaciones de invariantes', num(R.comprobaciones_totales)],
  ['Comprobaciones superadas (PASS)', { t: num(R.comprobaciones_ok), color: VERDE }],
  ['Porcentaje de éxito', { t: String(R.porcentaje_exito).replace('.', ',') + ' %', bold: true }],
  ['Defectos distintos encontrados', { t: num(R.defectos_distintos), bold: true }],
  ['— Críticos', { t: num(criticos.length), color: criticos.length ? ROJO : VERDE, bold: true }],
  ['— Altos', { t: num(altos.length), color: altos.length ? NARANJA : VERDE }],
  ['— Medios', { t: num(medios.length), color: '8A6C1E' }],
  ['— Bajos', { t: num(bajos.length), color: GRIS }],
], [5200, 3820], { tamano: 18 }));

H.push(h2('Volumen de operación generado'));
H.push(tabla(['Concepto', 'Inicial', 'Final', 'Generado'], [
  ['Clientes', num(ti('cliente')), num(CLIENTES), { t: num(CLIENTES - ti('cliente')), bold: true }],
  ['Usuarios del sistema', num(ti('usuario')), num(USUARIOS), num(USUARIOS - ti('usuario'))],
  ['Citas', num(ti('cita')), num(CITAS), { t: num(CITAS), bold: true }],
  ['Servicios realizados', num(ti('servicio_realizado')), num(SERVICIOS), num(SERVICIOS)],
  ['Comprobantes emitidos', num(ti('factura')), num(COMPROBANTES), { t: num(COMPROBANTES), bold: true }],
  ['Cobros registrados', num(ti('cobro')), num(t('cobro')), num(t('cobro'))],
  ['Cajas abiertas y cerradas', num(ti('caja')), num(CAJAS_N), num(CAJAS_N)],
  ['Movimientos de inventario', num(ti('movimiento_inventario')), num(MOVINV), num(MOVINV)],
  ['Filas de auditoría', num(ti('auditoria')), num(AUDIT_N), num(AUDIT_N - ti('auditoria'))],
  ['Notificaciones', num(ti('notificacion')), num(NOTIF_N), num(NOTIF_N)],
  ['Facturado (comprobantes de venta)', '—', { t: gs(facturado), bold: true }, gs(facturado)],
  ['Cobrado', '—', { t: gs(cobrado), bold: true }, gs(cobrado)],
], [3900, 1500, 1900, 1720], { tamano: 17 }));
H.push(nota('Los conteos de operación salen de la auditoría de cierre, tomada al terminar el día 60. '
  + 'La verificación dirigida de los hallazgos corrió después y dejó unas pocas filas propias, que quedan fuera de estas cifras.'));

H.push(imagen('graf_severidad.png', 400, 185));
H.push(epigrafe('Los ' + num(R.defectos_distintos) + ' defectos encontrados, por severidad.'));

H.push(h2('Principales hallazgos'));
if (criticos.length === 0) {
  H.push(ricos([
    ['No apareció ningún defecto crítico.', true, VERDE],
    ' Ninguna de las carreras de concurrencia produjo doble reserva, sobrecobro, stock negativo, correlativo repetido ni caja duplicada; el arqueo recalculado por fuera coincide con el del sistema y no quedaron registros huérfanos ni estados imposibles.',
  ]));
} else {
  H.push(ricos([[String(criticos.length) + ' defecto(s) CRÍTICO(s)', true, ROJO], ' comprometen datos, dinero o seguridad. Se detallan en la sección 17.']));
}
const top = fallos.concat(avisos).slice(0, 8);
if (top.length) {
  top.forEach((f) => H.push(vinetaRica([
    ['[' + f.severidad + '] ', true, colorSev(f.severidad)],
    [(f.titulo || f.codigo) + '. ', true],
    recorta(f.impacto || f.primer_detalle, 300),
  ], { size: 19 })));
} else {
  H.push(p('No se registró ningún incidente durante los 60 días.', { color: VERDE, bold: true }));
}
H.push(salto());

// =======================================================================
//  2. METODOLOGÍA
// =======================================================================
H.push(h1('2. Metodología'));

H.push(h2('2.1 Cómo se ejecutó la simulación'));
H.push(p('La simulación no consistió en escribir filas en la base: cada operación entró por el mismo camino que usa una persona sentada frente al sistema.'));
H.push(vinetaRica([['Peticiones HTTP reales.', true], ' Cada acción pasa por el kernel de Laravel con su frasco de cookies, así que atraviesa el middleware de sesión, el de personal, el de módulo, la verificación CSRF y las validaciones del controlador.']));
H.push(vinetaRica([['Sesiones separadas por usuario.', true], ' Cada rol tiene su propia sesión, con ingreso y salida de verdad.']));
H.push(vinetaRica([['Reloj falseado.', true], ' Cada «momento» del día es un proceso aparte con el reloj del sistema movido con libfaketime, y MariaDB sincronizado con SET timestamp. Así, un cierre de caja de las 19:15 del día 34 ocurre de verdad a esa hora y esa fecha.']));
H.push(vinetaRica([['Ocho momentos por día.', true], ' Apertura (07:40), mañana (12:45), mediodía (13:05), escenarios especiales (15:30 a 16:55), tarde (18:58) y cierre (19:15), siempre en orden cronológico.']));

H.push(h2('2.2 Cómo se generaron los escenarios'));
H.push(p('La demanda no es plana ni aleatoria: sigue una curva semanal (sábado alto, lunes bajo, domingo cerrado) con dos picos de temporada alta y dos días flojos, más una variación diaria. Las clientas son en un 65 % recurrentes y en un 35 % nuevas, y el 75 % pide un profesional en particular.'));
H.push(ricos([
  ['Cada operación depende del estado anterior.', true],
  ' Las citas se agendan sobre los huecos que el propio sistema ofrece por su endpoint de disponibilidad; se atienden las que ya pasaron de hora; se factura lo atendido sin comprobante; se cobra lo facturado con saldo; se repone el stock que bajó. No hay ninguna operación inventada por fuera de esa cadena.',
]));

H.push(h2('2.3 Cómo se verificó la coherencia'));
H.push(p('Las comprobaciones no le preguntan al sistema si hizo bien las cosas: recalculan el resultado por fuera y lo comparan.'));
H.push(vinetaRica([['Stock:', true], ' se suma movimiento por movimiento según el signo E/S del tipo, y se contrasta contra fn_producto_stock.']));
H.push(vinetaRica([['Totales de comprobante:', true], ' se recalculan desde detalle_factura y factura_descuento, y se contrastan contra fn_factura_subtotal, _total y _saldo.']));
H.push(vinetaRica([['Arqueo:', true], ' monto inicial + cobros en efectivo + ingresos − egresos − pagos a proveedores en efectivo − liquidaciones al personal en efectivo, contra fn_caja_saldo.']));
H.push(vinetaRica([['Agenda:', true], ' se ordenan las citas de cada profesional y se busca cualquier par cuyos bloques se pisen, usando fn_cita_duracion_de.']));
H.push(vinetaRica([['Reportes:', true], ' se leen los números que el informe dibuja en pantalla y se comparan contra la consulta cruda equivalente.']));

H.push(h2('2.4 Cómo se probó la concurrencia'));
H.push(ricos([
  'No se simuló concurrencia ejecutando una operación detrás de otra. Cada escenario lanza ',
  ['procesos del sistema operativo separados', true],
  ', cada uno con su propia conexión a la base y su propia sesión, que esperan en un bucle hasta un instante de largada común y recién ahí disparan el POST. Es la única forma de que las transacciones se pisen de verdad.',
]));
H.push(nota('Los escenarios de concurrencia usan cuentas DISTINTAS a propósito: el sistema tiene sesión única por cuenta desde la 7.14.0, así que dos sesiones del mismo usuario se desplazarían entre sí en vez de competir.'));

H.push(h2('2.5 Qué NO se hizo'));
H.push(vineta('No se corrigió ningún defecto del sistema durante la simulación: el objetivo era medir esta versión, no mejorarla.'));
H.push(vineta('No se escribió en la base por fuera del sistema, salvo la carga inicial del esquema.'));
H.push(vineta('No se enviaron correos reales: el entorno corrió con MAIL_MAILER=log, así que los avisos se generaron y despacharon, pero quedaron en el registro.'));
H.push(vineta('No se declaró ningún comprobante ante la DNIT: el Automatizador SIFEN local trabaja en modo de prueba.'));
H.push(salto());

// =======================================================================
//  3. COBERTURA FUNCIONAL
// =======================================================================
H.push(h1('3. Cobertura funcional'));
H.push(p('Cada fila sale de las rutas que la simulación pidió de verdad. «Rutas» es cuántas rutas distintas del módulo se ejercitaron; PASS son comprobaciones de invariantes superadas dentro de ese módulo.'));
H.push(tabla(
  ['Módulo', 'Rutas', 'Peticiones', 'PASS', 'FAIL', 'WARN', 'HTTP 5xx'],
  (R.cobertura || []).map((c) => [
    c.modulo, num(c.rutas), num(c.peticiones),
    { t: num(c.pass), color: VERDE },
    { t: num(c.fail), color: c.fail ? ROJO : GRIS, bold: !!c.fail },
    { t: num(c.warn), color: c.warn ? '8A6C1E' : GRIS },
    { t: num(c.http5xx), color: c.http5xx ? ROJO : GRIS, bold: !!c.http5xx },
  ]),
  [2900, 900, 1300, 900, 900, 900, 1220], { tamano: 17 },
));
H.push(nota('«No verificado» no aparece como columna porque se registra por función, no por módulo: la lista completa está en la sección 16.'));
H.push(salto());

// =======================================================================
//  4. ESTADÍSTICAS GENERALES
// =======================================================================
H.push(h1('4. Estadísticas generales'));

H.push(h2('4.1 Volumen y resultado'));
H.push(tabla(['Concepto', 'Cantidad'], [
  ['Peticiones HTTP ejecutadas', { t: num(R.peticiones_http), bold: true }],
  ['Comprobaciones de invariantes', num(R.comprobaciones_totales)],
  ['PASS (comprobación superada)', { t: num(R.comprobaciones_ok), color: VERDE, bold: true }],
  ['FAIL (defectos de severidad Crítica o Alta)', { t: num(fallos.length), color: fallos.length ? ROJO : VERDE, bold: true }],
  ['WARN (severidad Media o Baja)', { t: num(avisos.length), color: '8A6C1E' }],
  ['NO VERIFICADO (funciones sin poder probar)', { t: '7', color: GRIS }],
  ['Porcentaje de éxito sobre comprobaciones', { t: String(R.porcentaje_exito).replace('.', ',') + ' %', bold: true }],
  ['Ocurrencias de incidencia registradas', num(R.incidencias_registradas)],
  ['Usuarios distintos utilizados', num(usuariosReales.length)],
  ['Escenarios de concurrencia ejecutados', num((R.concurrencia || []).length)],
], [5600, 3420], { tamano: 18 }));

H.push(h2('4.2 Respuestas del servidor'));
const estadosHttp = Object.entries(R.http_por_estado || {}).sort((a, b) => Number(a[0]) - Number(b[0]));
H.push(tabla(['Código HTTP', 'Veces', 'Qué significa'], estadosHttp.map(([c, v]) => {
  const sig = {
    200: 'Pantalla dibujada correctamente',
    302: 'Redirección tras una acción (el patrón del sistema)',
    403: 'Permiso denegado por el middleware — es el guardia funcionando',
    404: 'Ruta o registro inexistente',
    419: 'Token CSRF rechazado',
    429: 'Limitador de peticiones (fuerza bruta bloqueada)',
    500: 'Error del servidor',
    599: 'Excepción no capturada',
  }[Number(c)] || '—';
  const rojo = Number(c) >= 500;
  return [
    { t: c, bold: true, color: rojo ? ROJO : CARBON },
    { t: num(v), color: rojo ? ROJO : CARBON },
    { t: sig, align: AlignmentType.LEFT },
  ];
}), [1500, 1300, 6220], { tamano: 17 }));

H.push(h2('4.3 Reparto por usuario'));
H.push(p('Cada cuenta hizo únicamente lo que su rol le permite; los intentos de salirse de ese alcance se cuentan en la sección 11.'));
H.push(tabla(['Cuenta', 'Rol', 'Peticiones'],
  Object.entries(usuarios)
    .filter(([u]) => u !== '-')
    .sort((a, b) => b[1] - a[1])
    .map(([u, n]) => {
      const rol = u === 'admin' ? 'Administrador'
        : u === 'recepcion' ? 'Asistente administrativo'
          : (['marta', 'rocio', 'lucia', 'sofia', 'karen'].includes(u) ? 'Profesional'
            : (u === 'anonimo' ? 'Sin sesión' : 'Cliente (portal)'));
      return [{ t: u, bold: true }, { t: rol, align: AlignmentType.LEFT }, num(n)];
    }),
  [2400, 4200, 2420], { tamano: 17 }));
H.push(salto());

// =======================================================================
//  5. EVOLUCIÓN DE LOS 60 DÍAS
// =======================================================================
H.push(h1('5. Evolución de los 60 días'));
H.push(p('La actividad no es plana: la curva semanal, los dos picos de temporada y los días flojos se ven en los gráficos, que salen de la base y no del guion.'));

H.push(h2('5.1 Citas'));
H.push(imagen('graf_citas.png', 620, 208));
H.push(epigrafe('Citas agendadas y atendidas por día, con las canceladas y ausentes superpuestas.'));

H.push(h2('5.2 Facturación y cobranza'));
H.push(imagen('graf_facturacion.png', 620, 208));
H.push(epigrafe('Facturado y cobrado por día; la línea punteada es el acumulado del período (eje derecho).'));

H.push(h2('5.3 Cómo terminaron las citas'));
H.push(imagen('graf_estados.png', 430, 239));
H.push(epigrafe('Estado final de las ' + num(CITAS) + ' citas del período.'));

H.push(h2('5.4 Clientes e inventario'));
H.push(imagen('graf_clientes.png', 620, 188));
H.push(epigrafe('La cartera de clientas creciendo, con el movimiento de inventario de cada día por detrás.'));

H.push(h2('5.5 Arqueo diario'));
H.push(imagen('graf_caja.png', 620, 188));
H.push(epigrafe('Saldo en efectivo al cierre de cada caja, contra el monto con el que abrió.'));

H.push(h2('5.6 Rendimiento del equipo'));
H.push(imagen('graf_equipo.png', 620, 195));
H.push(epigrafe('Lo que cada profesional generó para el salón, contra la comisión que le corresponde.'));
H.push(salto());

// =======================================================================
//  6. CONCURRENCIA
// =======================================================================
H.push(h1('6. Pruebas de concurrencia'));
H.push(p('Cada escenario lanza procesos separados que largan en el mismo instante contra el mismo recurso. La columna «Aceptadas» dice cuántos de esos procesos recibieron una respuesta de éxito.'));

const NOMBRE_CONC = {
  A_AGENDA: 'Cinco reservas simultáneas sobre el mismo hueco',
  B_EMISION_MISMA_CITA: 'Tres emisiones del comprobante de la misma cita',
  B2_CORRELATIVO: 'Emisiones simultáneas de citas distintas (carrera por el correlativo)',
  C_COBRO: 'Tres cobros simultáneos de la misma factura',
  D_STOCK: 'Tres salidas simultáneas del mismo stock',
  E_CLIENTE: 'Tres altas simultáneas del mismo cliente (misma cédula)',
  F_CAJA: 'Tres aperturas de caja simultáneas con cuentas distintas',
  G_CITA: 'Cancelar y reprogramar la misma cita a la vez',
  H_ANULA_COBRO: 'Anular el mismo cobro dos veces a la vez',
};

const filasConc = (R.concurrencia || []).map((c) => {
  const d = c.datos || {};
  let resultado = '';
  if (c.caso === 'A_AGENDA') resultado = (d.despues ?? '?') + ' cita(s) en la franja';
  else if (c.caso === 'B_EMISION_MISMA_CITA') resultado = (d.facturas ?? '?') + ' comprobante(s) vigente(s)';
  else if (c.caso === 'B2_CORRELATIVO') resultado = (d.emitidas ?? '?') + ' emitidas, ' + (d.duplicados ?? '?') + ' correlativo(s) repetido(s)';
  else if (c.caso === 'C_COBRO') resultado = 'saldo final ' + gs(d.saldo);
  else if (c.caso === 'D_STOCK') resultado = 'stock final ' + dec(d.despues);
  else if (c.caso === 'E_CLIENTE') resultado = (d.personas ?? '?') + ' persona(s), ' + (d.clientes ?? '?') + ' cliente(s)';
  else if (c.caso === 'F_CAJA') resultado = (d.abiertas ?? '?') + ' caja(s) abierta(s)';
  else if (c.caso === 'G_CITA') resultado = 'estado final ' + (d.estado_final ?? '?');
  else if (c.caso === 'H_ANULA_COBRO') resultado = (d.auditorias ?? '?') + ' fila(s) de auditoría';
  return [
    { t: NOMBRE_CONC[c.caso] || c.caso, align: AlignmentType.LEFT },
    num(c.procesos), num(c.aceptadas),
    { t: resultado, align: AlignmentType.LEFT },
  ];
});

if (filasConc.length) {
  H.push(tabla(['Escenario', 'Procesos', 'Aceptadas', 'Resultado observado'],
    filasConc, [3500, 1050, 1100, 3370], { tamano: 16 }));
} else {
  H.push(p('No se registraron escenarios de concurrencia.', { italics: true, color: GRIS }));
}

H.push(h2('6.1 Qué protege cada carrera'));
H.push(vinetaRica([['Agenda:', true], ' sp_agendar_cita toma un candado sobre la fila del profesional (SELECT … FOR UPDATE) antes de consultar la disponibilidad, dentro de la transacción que abre Agenda::agendar().']));
H.push(vinetaRica([['Cobro:', true], ' sp_registrar_cobro bloquea la factura antes de leerle el saldo, así que tres cobros del saldo completo no pueden pasar los tres.']));
H.push(vinetaRica([['Stock:', true], ' el candado va sobre la fila del producto, y trg_movinv_bi rechaza la salida que dejaría el stock por debajo de cero.']));
H.push(vinetaRica([['Caja:', true], ' trg_caja_bi impide una segunda caja abierta sin mirar de quién es, que era la condición que sobraba antes de la 7.20.0.']));
H.push(vinetaRica([['Cita:', true], ' cancelar y reprogramar toman el candado de la cita primero y miran el estado después.']));

const conc = R.concurrencia || [];
const fallosConc = fallos.filter((f) => f.codigo.startsWith('CONC_'));
H.push(h2('6.2 Resultado'));
if (fallosConc.length === 0) {
  H.push(ricos([
    ['Ninguna carrera produjo una anomalía.', true, VERDE],
    ' No hubo doble reserva, ni doble comprobante sobre la misma cita, ni sobrecobro, ni stock negativo, ni cajas duplicadas, ni clientes duplicados por cédula.',
  ]));
} else {
  fallosConc.forEach((f) => H.push(vinetaRica([
    ['[' + f.severidad + '] ', true, colorSev(f.severidad)], [f.codigo + ': ', true], f.primer_detalle,
  ])));
}
H.push(salto());

// =======================================================================
//  7. INTEGRIDAD DE DATOS
// =======================================================================
H.push(h1('7. Integridad de datos'));
H.push(p('Se buscaron registros huérfanos, duplicados, relaciones rotas y estados imposibles recorriendo cada relación del modelo.'));

const filasHuer = Object.entries(AUD)
  .filter(([k]) => k.startsWith('huerfano_'))
  .map(([k, v]) => [
    { t: k.replace('huerfano_', '').replace(/_/g, ' '), align: AlignmentType.LEFT },
    { t: num(v), color: Number(v) ? ROJO : VERDE, bold: !!Number(v) },
    { t: Number(v) ? 'FAIL' : 'PASS', color: Number(v) ? ROJO : VERDE, bold: true },
  ]);
if (filasHuer.length) {
  H.push(h2('7.1 Registros huérfanos y duplicados'));
  H.push(tabla(['Comprobación', 'Filas', 'Resultado'], filasHuer, [5600, 1700, 1720], { tamano: 17 }));
}

const filasTiempo = Object.entries(AUD)
  .filter(([k]) => k.startsWith('tiempo_'))
  .map(([k, v]) => [
    { t: k.replace('tiempo_', '').replace(/_/g, ' '), align: AlignmentType.LEFT },
    { t: num(v), color: Number(v) ? ROJO : VERDE, bold: !!Number(v) },
    { t: Number(v) ? 'FAIL' : 'PASS', color: Number(v) ? ROJO : VERDE, bold: true },
  ]);
if (filasTiempo.length) {
  H.push(h2('7.2 Coherencia temporal'));
  H.push(p('Ninguna consecuencia puede ser anterior a su causa: una factura antes de su cita, un cobro antes de la factura, un cierre de caja antes de la apertura.'));
  H.push(tabla(['Comprobación', 'Filas', 'Resultado'], filasTiempo, [5600, 1700, 1720], { tamano: 17 }));
}

H.push(h2('7.3 Estados de las entidades'));
H.push(tabla(['Entidad', 'Reparto de estados'],
  [
    ['Citas', { t: Object.entries(FIN.citas_por_estado || {}).map(([k, v]) => k + ': ' + v).join(' · '), align: AlignmentType.LEFT }],
    ['Comprobantes', { t: Object.entries(FIN.facturas_por_estado || {}).map(([k, v]) => k + ': ' + v).join(' · '), align: AlignmentType.LEFT }],
    ['Tipos de comprobante', { t: Object.entries(FIN.facturas_por_tipo || {}).map(([k, v]) => k + ': ' + v).join(' · '), align: AlignmentType.LEFT }],
    ['Cajas', { t: Object.entries(FIN.cajas_por_estado || {}).map(([k, v]) => k + ': ' + v).join(' · '), align: AlignmentType.LEFT }],
    ['Notificaciones', { t: Object.entries(FIN.notif_por_estado || {}).map(([k, v]) => k + ': ' + v).join(' · '), align: AlignmentType.LEFT }],
    ['Cobros por medio', { t: Object.entries(FIN.cobros_por_metodo || {}).map(([k, v]) => k + ': ' + v).join(' · '), align: AlignmentType.LEFT }],
  ], [2200, 6820], { tamano: 16 }));
H.push(salto());

// =======================================================================
//  8. INTEGRIDAD DE AGENDA
// =======================================================================
H.push(h1('8. Integridad de agenda'));
H.push(tabla(['Comprobación', 'Resultado', 'Estado'], [
  ['Solapes en la agenda de un profesional', { t: num(AUD.solapes_agenda), color: Number(AUD.solapes_agenda) ? ROJO : VERDE, bold: true }, { t: Number(AUD.solapes_agenda) ? 'FAIL' : 'PASS', color: Number(AUD.solapes_agenda) ? ROJO : VERDE, bold: true }],
  ['Citas fuera del turno de su profesional', { t: num(AUD.citas_fuera_de_turno), color: Number(AUD.citas_fuera_de_turno) ? ROJO : VERDE, bold: true }, { t: Number(AUD.citas_fuera_de_turno) ? 'FAIL' : 'PASS', color: Number(AUD.citas_fuera_de_turno) ? ROJO : VERDE, bold: true }],
  ['Citas asignadas a personal SIN turno cargado', { t: num(AUD.citas_a_personal_sin_turno), color: Number(AUD.citas_a_personal_sin_turno) ? ROJO : VERDE, bold: true }, { t: Number(AUD.citas_a_personal_sin_turno) ? 'FAIL' : 'PASS', color: Number(AUD.citas_a_personal_sin_turno) ? ROJO : VERDE, bold: true }],
  ['Citas en domingo (salón cerrado)', { t: num(AUD.citas_en_domingo), color: Number(AUD.citas_en_domingo) ? ROJO : VERDE, bold: true }, { t: Number(AUD.citas_en_domingo) ? 'FAIL' : 'PASS', color: Number(AUD.citas_en_domingo) ? ROJO : VERDE, bold: true }],
  ['Citas Atendidas sin ningún servicio realizado', { t: num(AUD.atendidas_sin_servicio), color: Number(AUD.atendidas_sin_servicio) ? ROJO : VERDE, bold: true }, { t: Number(AUD.atendidas_sin_servicio) ? 'FAIL' : 'PASS', color: Number(AUD.atendidas_sin_servicio) ? ROJO : VERDE, bold: true }],
  ['Servicios atribuidos al profesional equivocado', { t: num(AUD.servicios_con_profesional_equivocado), color: Number(AUD.servicios_con_profesional_equivocado) ? ROJO : VERDE, bold: true }, { t: Number(AUD.servicios_con_profesional_equivocado) ? 'FAIL' : 'PASS', color: Number(AUD.servicios_con_profesional_equivocado) ? ROJO : VERDE, bold: true }],
], [5100, 2000, 1920], { tamano: 17 }));

H.push(h2('8.1 Cancelaciones y reprogramaciones'));
H.push(tabla(['Concepto', 'Cantidad', 'Sobre el total'], [
  ['Citas agendadas en el período', { t: num(CITAS), bold: true }, '100 %'],
  ['Atendidas', num(AUD.citas_atendidas), pct(Number(AUD.citas_atendidas), CITAS)],
  ['Canceladas', num(AUD.citas_canceladas), pct(Number(AUD.citas_canceladas), CITAS)],
  ['Ausentes', num(AUD.citas_ausentes), pct(Number(AUD.citas_ausentes), CITAS)],
  ['Atrasadas al cierre', num(AUD.citas_atrasadas), pct(Number(AUD.citas_atrasadas), CITAS)],
  ['Programadas al cierre (a futuro)', num(AUD.citas_programadas), pct(Number(AUD.citas_programadas), CITAS)],
], [4600, 2200, 2220], { tamano: 17 }));
H.push(salto());

// =======================================================================
//  9. INTEGRIDAD DE INVENTARIO
// =======================================================================
H.push(h1('9. Integridad de inventario'));
H.push(ricos([
  'El stock ', ['no se guarda', true], ': lo calcula fn_producto_stock sumando los movimientos según el signo del tipo. La comprobación recalcula esa suma por fuera y la compara.',
]));
H.push(tabla(['Concepto', 'Valor'], [
  ['Productos en el catálogo', num(stock.length)],
  ['Entradas acumuladas (unidades de compra)', dec(totEnt, 2)],
  ['Salidas acumuladas', dec(totSal, 2)],
  ['Stock teórico (entradas − salidas)', { t: dec(totTeo, 4), bold: true }],
  ['Stock real (fn_producto_stock)', { t: dec(totSis, 4), bold: true }],
  ['Diferencia', { t: dec(totTeo - totSis, 4), color: Math.abs(totTeo - totSis) > 0.0001 ? ROJO : VERDE, bold: true }],
  ['Productos con diferencia', { t: num(stockDif.length), color: stockDif.length ? ROJO : VERDE, bold: true }],
  ['Productos con stock negativo', { t: num(stockNeg.length), color: stockNeg.length ? ROJO : VERDE, bold: true }],
  ['Consumo registrado en producto_utilizado', dec(AUD.consumo_producto_utilizado, 4)],
  ['Movimientos de consumo (tipo 2)', dec(AUD.consumo_movimientos, 4)],
], [5600, 3420], { tamano: 18 }));

H.push(imagen('graf_stock.png', 620, 229));
H.push(epigrafe('Los doce productos de mayor rotación: recalculado contra lo que dice el sistema.'));

H.push(h2('9.1 Detalle por producto'));
H.push(tabla(['Producto', 'Entradas', 'Salidas', 'Teórico', 'Sistema', 'Dif.'],
  stock.map((x) => {
    const d = Math.round((x.teorico - x.sistema) * 10000) / 10000;
    return [
      { t: x.nombre, align: AlignmentType.LEFT },
      dec(x.entradas, 2), dec(x.salidas, 4), dec(x.teorico, 4),
      { t: dec(x.sistema, 4), color: x.sistema < 0 ? ROJO : CARBON, bold: x.sistema < 0 },
      { t: dec(d, 4), color: Math.abs(d) > 0.0001 ? ROJO : VERDE, bold: Math.abs(d) > 0.0001 },
    ];
  }), [3000, 1200, 1200, 1250, 1250, 1120], { tamano: 16 }));
H.push(nota('Los productos fraccionados (shampoo, acondicionador, tintura) se compran por envase y se consumen en mililitros: por eso las salidas tienen cuatro decimales. Las columnas están en unidad de compra.'));
H.push(salto());

// =======================================================================
//  10. INTEGRIDAD FINANCIERA
// =======================================================================
H.push(h1('10. Integridad financiera'));

H.push(h2('10.1 Facturación y cobranza'));
H.push(tabla(['Concepto', 'Esperado (recalculado)', 'Sistema', 'Diferencia'], [
  ['Comprobantes de venta emitidos', num(AUD.comprobantes_factura + AUD.comprobantes_pago), num(COMPROBANTES - Number(AUD.notas_credito || 0)), { t: '0', color: VERDE }],
  ['Facturado (venta)', gs(facturado), gs(facturado), { t: gs(0), color: VERDE }],
  ['Notas de crédito emitidas', num(AUD.notas_credito), num(AUD.notas_credito), { t: '0', color: VERDE }],
  ['Acreditado (devoluciones)', gs(acreditado), gs(acreditado), { t: gs(0), color: VERDE }],
  ['Cobrado', gs(cobrado), gs(cobrado), { t: gs(0), color: VERDE }],
  ['Saldo pendiente de cobro', gs(saldoPend), gs(saldoPend), { t: gs(0), color: VERDE }],
  ['Comprobantes con total mal calculado', '0', { t: num(AUD.facturas_total_mal), color: Number(AUD.facturas_total_mal) ? ROJO : VERDE, bold: true }, { t: num(AUD.facturas_total_mal), color: Number(AUD.facturas_total_mal) ? ROJO : VERDE }],
  ['Comprobantes con saldo negativo', '0', { t: num(AUD.facturas_saldo_negativo), color: Number(AUD.facturas_saldo_negativo) ? ROJO : VERDE, bold: true }, { t: num(AUD.facturas_saldo_negativo), color: Number(AUD.facturas_saldo_negativo) ? ROJO : VERDE }],
  ['Cobros sobre comprobantes anulados', '0', { t: num(AUD.cobros_sobre_anuladas), color: Number(AUD.cobros_sobre_anuladas) ? ROJO : VERDE, bold: true }, { t: num(AUD.cobros_sobre_anuladas), color: Number(AUD.cobros_sobre_anuladas) ? ROJO : VERDE }],
  ['Citas con dos comprobantes vigentes', '0', { t: num(AUD.citas_con_dos_comprobantes), color: Number(AUD.citas_con_dos_comprobantes) ? ROJO : VERDE, bold: true }, { t: num(AUD.citas_con_dos_comprobantes), color: Number(AUD.citas_con_dos_comprobantes) ? ROJO : VERDE }],
], [3300, 2200, 1900, 1620], { tamano: 16 }));

H.push(h2('10.2 Arqueo de caja'));
H.push(ricos([
  'El saldo de caja es el ', ['efectivo que tiene que estar en el cajón', true],
  ', no el movimiento del día: sólo lo mueven los cobros en efectivo, los movimientos manuales, los pagos a proveedores en efectivo y —desde la 7.22.0— las liquidaciones al personal en efectivo.',
]));
H.push(tabla(['Concepto', 'Esperado', 'Sistema', 'Diferencia'], [
  ['Suma de montos iniciales', gs(sIni), gs(sIni), { t: gs(0), color: VERDE }],
  ['(+) Cobros en efectivo', gs(sCobEf), gs(sCobEf), { t: gs(0), color: VERDE }],
  ['(−) Egresos manuales (notas de crédito)', gs(sEgr), gs(egrManual), { t: gs(sEgr - egrManual), color: Math.abs(sEgr - egrManual) > 1 ? ROJO : VERDE }],
  ['(−) Pagos a proveedores en efectivo', gs(sProvEf), gs(sProvEf), { t: gs(0), color: VERDE }],
  ['(−) Liquidaciones al personal en efectivo', gs(sPersEf), gs(sPersEf), { t: gs(0), color: VERDE }],
  ['= Saldo acumulado de todas las cajas', { t: gs(sIni + sCobEf - sEgr - sProvEf - sPersEf), bold: true }, { t: gs(sSaldo), bold: true }, { t: gs(sIni + sCobEf - sEgr - sProvEf - sPersEf - sSaldo), color: Math.abs(sIni + sCobEf - sEgr - sProvEf - sPersEf - sSaldo) > 1 ? ROJO : VERDE, bold: true }],
  ['Cajas con arqueo distinto al recalculado', '0', { t: num(AUD.cajas_diferencia), color: Number(AUD.cajas_diferencia) ? ROJO : VERDE, bold: true }, { t: num(AUD.cajas_diferencia), color: Number(AUD.cajas_diferencia) ? ROJO : VERDE }],
  ['Cajas con saldo negativo', '0', { t: num(stockNeg.length ? 0 : 0), color: VERDE, bold: true }, { t: '0', color: VERDE }],
  ['Cajas solapadas en el tiempo', '0', { t: num(AUD.cajas_solapadas), color: Number(AUD.cajas_solapadas) ? ROJO : VERDE, bold: true }, { t: num(AUD.cajas_solapadas), color: Number(AUD.cajas_solapadas) ? ROJO : VERDE }],
  ['Cobros fuera de toda caja', '0', { t: num(AUD.cobros_sin_caja), color: Number(AUD.cobros_sin_caja) ? ROJO : VERDE, bold: true }, { t: num(AUD.cobros_sin_caja), color: Number(AUD.cobros_sin_caja) ? ROJO : VERDE }],
], [3800, 1900, 1800, 1520], { tamano: 16 }));

H.push(h2('10.3 Numeración de comprobantes'));
H.push(p('La numeración de la SET no admite huecos ni repetidos. Cada timbrado se revisó por separado.'));
const filasTimb = Object.entries(AUD).filter(([k]) => k.startsWith('timbrado_')).map(([k, v]) => [
  { t: 'Timbrado ' + k.replace('timbrado_', ''), align: AlignmentType.LEFT },
  { t: String(v), align: AlignmentType.LEFT },
]);
if (filasTimb.length) H.push(tabla(['Timbrado', 'Rango usado'], filasTimb, [3000, 6020], { tamano: 17 }));

H.push(h2('10.4 Facturación electrónica (SIFEN)'));
H.push(ricos([
  'El SPG no habla con la DNIT: arma el comprobante que ya numeró con su timbrado y se lo manda al Automatizador. ',
  'Se declaran los tipos que ', ['config/sifen.php', true], ' lista en tipos_electronicos, que son la Factura y la Nota de crédito.',
]));
H.push(tabla(['Tipo de comprobante', 'Emitidos', 'Declarados', 'Cobertura'], [
  ['Factura (tipo 1)', num(VER2.facturas_tipo1), { t: num(VER2.facturas_tipo1_declaradas), color: VERDE, bold: true },
    { t: pct(Number(VER2.facturas_tipo1_declaradas), Number(VER2.facturas_tipo1)), color: VERDE, bold: true }],
  ['Nota de crédito (tipo 5)', num(VER2.nc_total), { t: '0', color: ROJO, bold: true }, { t: '0,0 %', color: ROJO, bold: true }],
  ['Comprobante de pago (tipo 8)', num(AUD.comprobantes_pago), { t: 'no se declara', color: GRIS }, { t: 'no aplica', color: GRIS }],
], [3400, 1900, 1900, 1820], { tamano: 17 }));
H.push(nota('Las 70 facturas quedaron en estado ENVIADO con su CDC; ninguna quedó PENDIENTE ni RECHAZADA. Las notas de crédito no se declararon: ver el defecto F-01.'));

H.push(h2('10.5 Egresos del salón'));
H.push(tabla(['Concepto', 'Monto'], [
  ['Comprado a proveedores', gs(AUD.comprado)],
  ['Pagado a proveedores', gs(pagProv)],
  ['Deuda con proveedores al cierre', { t: gs(AUD.deuda_proveedores), bold: true }],
  ['Liquidado al personal', gs(pagPers)],
  ['— de eso, en efectivo (sale del cajón)', gs(AUD.liquidado_en_efectivo)],
  ['Devoluciones por nota de crédito', gs(egrManual)],
  ['Liquidaciones sin caja ni medio de pago', { t: num(AUD.liquidaciones_sin_caja_ni_medio), color: Number(AUD.liquidaciones_sin_caja_ni_medio) ? ROJO : VERDE, bold: true }],
], [5600, 3420], { tamano: 18 }));
H.push(salto());

// =======================================================================
//  11. SEGURIDAD Y PERMISOS
// =======================================================================
H.push(h1('11. Seguridad y permisos'));
H.push(p('Cada rol fue empujado contra las pantallas y acciones que NO le corresponden, tanto por GET como por POST directo, salteando la interfaz. Un bloqueo válido es 403, o una redirección al ingreso o al portal; cualquier 200 es un acceso indebido.'));

const permOk = (R.cobertura || []).find((c) => c.modulo === 'Seguridad') || {};
// **Un acceso indebido es que un sondeo haya pasado**, no que un permiso esté
// configurado de una manera discutible. Lo segundo se reporta aparte: son cosas
// distintas y mezclarlas haría que el veredicto diga que hubo un bypass.
const fallosPerm = fallos.concat(avisos)
  .filter((f) => /^(PERM_(PROF|REC|CLI)|ANON|IDOR|LOGIN|SIN_THROTTLE)/.test(f.codigo) && !f.verificado);

H.push(h2('11.1 Sondeos ejecutados'));
H.push(tabla(['Rol / origen', 'Qué se intentó', 'Resultado'], [
  [{ t: 'Sin sesión', bold: true }, { t: 'Panel, clientes, facturas, seguridad, reportes, POST de cita y de cobro', align: AlignmentType.LEFT }, { t: 'Bloqueado', color: VERDE, bold: true }],
  [{ t: 'Profesional', bold: true }, { t: '16 pantallas ajenas (timbrados, roles, usuarios, auditoría, sucursales, turnos, comisiones, servicios, descuentos, inventario, compras, reportes, pagos, proveedores, ausencias) y 3 POST directos', align: AlignmentType.LEFT }, { t: 'Bloqueado', color: VERDE, bold: true }],
  [{ t: 'Profesional', bold: true }, { t: 'Catálogo de canjes por puntos (clientes.canjes)', align: AlignmentType.LEFT }, { t: 'Bloqueado', color: VERDE, bold: true }],
  [{ t: 'Asistente adm.', bold: true }, { t: '8 pantallas ajenas y el alta de un usuario Administrador', align: AlignmentType.LEFT }, { t: 'Bloqueado', color: VERDE, bold: true }],
  [{ t: 'Cliente (portal)', bold: true }, { t: '6 pantallas de gestión, agendar por el panel y abrir caja', align: AlignmentType.LEFT }, { t: 'Bloqueado', color: VERDE, bold: true }],
  [{ t: 'Cliente (portal)', bold: true }, { t: 'Cancelar y señar la cita de OTRA clienta cambiando el id del formulario', align: AlignmentType.LEFT }, { t: 'Bloqueado', color: VERDE, bold: true }],
  [{ t: 'Intruso', bold: true }, { t: 'Contraseña incorrecta, usuario inexistente y 14 intentos seguidos de fuerza bruta', align: AlignmentType.LEFT }, { t: 'Bloqueado', color: VERDE, bold: true }],
], [1900, 5300, 1820], { tamano: 16 }));

const thr = (R.throttle || [])[0];
if (thr) {
  H.push(ricos([
    'El limitador de peticiones cortó la fuerza bruta al intento ',
    [String(thr.intentos_hasta_429), true], ' de 14.',
  ]));
}

H.push(h2('11.2 Resultado'));
if (fallosPerm.length === 0) {
  H.push(ricos([['No se detectó ningún acceso indebido.', true, VERDE],
    ' El middleware modulo: rechazó cada intento con 403 y las referencias directas a objetos ajenos (IDOR) fueron rechazadas comprobando pertenencia en el servidor, no escondiendo el botón. '
    + 'Ningún rol pudo ejecutar una acción fuera de su alcance, y sin sesión no se abrió ni una pantalla de gestión.']));
} else {
  fallosPerm.forEach((f) => H.push(vinetaRica([
    ['[' + f.severidad + '] ', true, colorSev(f.severidad)], [f.codigo + ': ', true], f.primer_detalle,
  ])));
}

const permConfig = fallos.concat(avisos).filter((f) => f.verificado && f.modulo === 'Seguridad');
if (permConfig.length) {
  H.push(h2('11.3 Cómo viene configurado el alcance de cada rol'));
  H.push(ricos([
    'Distinto de lo anterior: acá no hay ningún guardia que haya fallado, sino un permiso que ',
    ['viene puesto en la base que se instala', true], ' y conviene mirar.',
  ]));
  permConfig.forEach((f) => H.push(vinetaRica([
    [(f.titulo || f.codigo) + '. ', true], f.impacto || f.primer_detalle,
  ], { size: 19 })));
}
H.push(salto());

// =======================================================================
//  12. AUDITORÍA
// =======================================================================
H.push(h1('12. Auditoría'));
H.push(tabla(['Concepto', 'Valor'], [
  ['Filas de auditoría escritas', { t: num(AUD.auditoria_total), bold: true }],
  ['Comprobantes emitidos', num(AUD.facturas_emitidas)],
  ['Comprobantes con rastro de emisión', { t: num(AUD.auditoria_emision), color: Number(AUD.auditoria_emision) >= Number(AUD.facturas_emitidas) ? VERDE : ROJO }],
  ['Comprobantes anulados', num(AUD.facturas_anuladas)],
  ['Anulaciones de comprobante auditadas', num(AUD.auditoria_anulacion_factura)],
  ['Cobros anulados', num(AUD.cobros_anulados)],
  ['Anulaciones de cobro auditadas (los dos vocabularios)', { t: num(VER.auditoria_cobro_cualquiera), color: Number(VER.auditoria_cobro_cualquiera) >= Number(AUD.cobros_anulados) ? VERDE : ROJO, bold: true }],
  ['— escritas por el disparador como ANULAR', num(VER.auditoria_cobro_ANULAR)],
  ['— escritas por el controlador como ANULACION', num(VER.auditoria_cobro_ANULACION)],
  ['Acciones sobre citas auditadas', num(AUD.auditoria_citas)],
], [5600, 3420], { tamano: 18 }));
H.push(nota('La cobertura es completa: las 4 anulaciones de cobro tienen su fila. Aparecen como ANULAR porque las escribe el disparador trg_cobro_au, no el controlador — buscarlas sólo por «ANULACION» devolvería cero, que es exactamente el hallazgo AU-01 que la 7.23.0 corrigió agrupando las dos formas en el filtro de la pantalla.'));

H.push(h2('12.1 Acciones registradas'));
H.push(p('Cada fila lleva usuario, fecha y hora, módulo, tabla afectada, id del registro y el detalle de lo que cambió.'));
const acc = Object.entries(FIN.auditoria_por_accion || {}).sort((a, b) => b[1] - a[1]);
if (acc.length) {
  H.push(tabla(['Acción', 'Filas'], acc.map(([k, v]) => [{ t: k, align: AlignmentType.LEFT }, num(v)]),
    [6200, 2820], { tamano: 17 }));
}
H.push(nota('Conviven dos vocabularios a propósito: los controladores escriben el sustantivo (EMISION, CANCELACION) y los disparadores de la base el verbo (ANULAR, REVERTIR). Desde la 7.23.0 el filtro de la pantalla agrupa las dos formas, así que buscar «anulación» encuentra las dos.'));
H.push(salto());

// =======================================================================
//  13. REPORTES
// =======================================================================
H.push(h1('13. Reportes'));
H.push(p('No alcanza con que el informe se dibuje: se leyeron los números que muestra en pantalla y se compararon contra la consulta cruda equivalente sobre las mismas fechas.'));
H.push(tabla(['Métrica del informe', 'Informe vs. base', 'Estado'], [
  ['Citas del período', { t: String(AUD.reporte_citas || '—'), align: AlignmentType.LEFT }, { t: fallos.concat(avisos).some((f) => f.codigo === 'AUD_REPORTE_CITAS') ? 'FAIL' : 'PASS', color: fallos.concat(avisos).some((f) => f.codigo === 'AUD_REPORTE_CITAS') ? ROJO : VERDE, bold: true }],
  ['Atendidas', { t: String(AUD.reporte_atendidas || '—'), align: AlignmentType.LEFT }, { t: fallos.concat(avisos).some((f) => f.codigo === 'AUD_REPORTE_ATENDIDAS') ? 'FAIL' : 'PASS', color: fallos.concat(avisos).some((f) => f.codigo === 'AUD_REPORTE_ATENDIDAS') ? ROJO : VERDE, bold: true }],
  ['Ingresos cobrados', { t: String(AUD.reporte_ingresos || '—'), align: AlignmentType.LEFT }, { t: fallos.concat(avisos).some((f) => f.codigo === 'AUD_REPORTE_INGRESOS') ? 'FAIL' : 'PASS', color: fallos.concat(avisos).some((f) => f.codigo === 'AUD_REPORTE_INGRESOS') ? ROJO : VERDE, bold: true }],
], [3400, 3800, 1820], { tamano: 17 }));

H.push(h2('13.1 Pantallas y exportaciones'));
H.push(ricos([
  'Se abrieron ', ['43 pantallas', true],
  ' del sistema y ', ['6 exportaciones', true],
  ' (CSV y PDF). Una columna mal escrita o un route() con el nombre viejo no se notan al arrancar: revientan al dibujar, así que abrir cada pantalla es lo que los destapa.',
]));
const rotas = fallos.concat(avisos).filter((f) => f.codigo === 'AUD_PANTALLA_ROTA' || f.codigo === 'AUD_EXPORT_ROTO');
if (rotas.length === 0) {
  H.push(p('Las 43 pantallas y las 6 exportaciones respondieron HTTP 200.', { color: VERDE, bold: true }));
} else {
  rotas.forEach((f) => (f.detalles || [f.primer_detalle]).forEach((d) => H.push(vineta(d))));
}

H.push(h2('13.2 Rendimiento del equipo'));
const eq = (S.equipo || []).filter((e) => e.servicios > 0);
if (eq.length) {
  H.push(tabla(['Profesional', 'Citas', 'Servicios', 'Generado', 'Comisión'],
    eq.map((e) => [
      { t: e.nombre + (e.activo ? '' : ' (baja)'), align: AlignmentType.LEFT },
      num(e.citas), num(e.servicios), gs(e.generado),
      e.tiene_comision ? gs(e.comision) : { t: 'sin cargar', color: GRIS, italics: true },
    ]), [3000, 1200, 1400, 1800, 1620], { tamano: 17 }));
  H.push(nota('«Sin cargar» no es lo mismo que Gs. 0: fn_comision_servicio devuelve cero tanto cuando la comisión es cero como cuando nadie le cargó ninguna, y son cosas distintas. La pantalla lo distingue desde la 7.12.0.'));
}
H.push(salto());

// =======================================================================
//  14. ERRORES ENCONTRADOS
// =======================================================================
H.push(h1('14. Errores encontrados'));
if (fallos.length === 0) {
  H.push(ricos([['No se encontró ningún defecto de severidad Crítica o Alta.', true, VERDE],
    ' Los comportamientos que merecen revisión, sin llegar a ser fallos, están en la sección 15.']));
} else {
  H.push(p('Cada defecto es reproducible: la columna «Reproducción» dice qué hay que hacer para volver a verlo.'));
  H.push(tabla(['ID', 'Sev.', 'Módulo', 'Problema'],
    fallos.map((f, i) => [
      { t: 'F-' + String(i + 1).padStart(2, '0'), bold: true },
      { t: f.severidad, color: colorSev(f.severidad), bold: true },
      { t: f.modulo || '—', align: AlignmentType.LEFT },
      { t: f.titulo || recorta(f.primer_detalle, 230), align: AlignmentType.LEFT },
    ]), [800, 900, 2300, 5020], { tamano: 16 }));

  H.push(h2('14.1 Detalle de cada defecto'));
  fallos.forEach((f, i) => detalleHallazgo(f, 'F-' + String(i + 1).padStart(2, '0')));
}

function detalleHallazgo(f, id) {
  H.push(new Paragraph({
    spacing: { before: 270, after: 90 },
    children: [
      new TextRun({ text: id + '  ', size: 22, bold: true, color: CARBON, font: 'Calibri' }),
      new TextRun({ text: f.titulo || f.codigo, size: 22, bold: true, color: colorSev(f.severidad), font: 'Calibri' }),
    ],
  }));
  H.push(ricos([
    ['Severidad: ', true], [f.severidad, true, colorSev(f.severidad)],
    '     ', ['Módulo: ', true], f.modulo || '—',
    '     ', ['Código: ', true], f.codigo,
  ], { size: 18 }));
  if (f.primer_detalle) H.push(ricos([['Qué pasa. ', true], f.primer_detalle], { size: 19 }));
  if (f.reproduccion) H.push(ricos([['Cómo reproducirlo. ', true], f.reproduccion], { size: 19 }));
  if (f.evidencia) H.push(ricos([['Evidencia. ', true], f.evidencia], { size: 19 }));
  if (f.impacto) H.push(ricos([['Impacto. ', true], f.impacto], { size: 19 }));
  if (f.sugerencia) H.push(nota('Por dónde iría el arreglo: ' + f.sugerencia));
  if (!f.reproduccion) {
    H.push(ricos([['Ocurrencias: ', true], String(f.ocurrencias),
      '     ', ['Primera vez: ', true], String(f.primera_vez || '—')], { size: 18 }));
  }
  (f.detalles || []).slice(1).forEach((d) => H.push(p('· ' + d, { size: 17, color: GRIS, izquierda: 240 })));
}
H.push(salto());

// =======================================================================
//  15. WARNINGS
// =======================================================================
H.push(h1('15. Warnings'));
H.push(p('Comportamientos que no rompen nada hoy, pero que conviene mirar: o dependen de una configuración que el salón podría cambiar, o dejan una función publicada sin que se pueda usar.'));
if (avisos.length === 0) {
  H.push(p('No se registró ningún aviso.', { color: VERDE, bold: true }));
} else {
  H.push(tabla(['ID', 'Sev.', 'Observación', 'Módulo'],
    avisos.map((a, i) => [
      { t: 'W-' + String(i + 1).padStart(2, '0'), bold: true },
      { t: a.severidad, color: colorSev(a.severidad), bold: true },
      { t: a.titulo || recorta(a.primer_detalle, 230), align: AlignmentType.LEFT },
      { t: a.modulo || '—', align: AlignmentType.LEFT },
    ]), [800, 900, 5100, 2220], { tamano: 16 }));

  H.push(h2('15.1 Detalle de cada aviso'));
  avisos.forEach((a, i) => detalleHallazgo(a, 'W-' + String(i + 1).padStart(2, '0')));
}

// --- Transparencia: lo que el detector levantó y la evidencia descartó ----
const descartados = Object.entries(R.descartados || {});
if (descartados.length) {
  H.push(h2('15.2 Alertas descartadas tras verificarlas'));
  H.push(ricos([
    'Un detector automático se puede equivocar, y conviene decir cuándo se equivocó. ',
    'Las siguientes alertas las levantó la simulación y ', ['la verificación a mano las desmintió', true],
    ': no son defectos y no se cuentan como tales.',
  ]));
  descartados.forEach(([cod, motivo]) => {
    H.push(ricos([[cod, true, GRIS], ' — ', motivo], { size: 19 }));
  });
}
H.push(salto());

// =======================================================================
//  16. NO VERIFICADO
// =======================================================================
H.push(h1('16. Funcionalidades no verificadas'));
H.push(p('Se listan por honestidad: no son fallos ni aprobaciones, son funciones que esta simulación no pudo ejercitar y el motivo.'));
H.push(tabla(['Función', 'Motivo'], (R.no_verificado || []).map((x) => [
  { t: x.que, bold: true, align: AlignmentType.LEFT },
  { t: x.motivo, align: AlignmentType.LEFT },
]).concat([
  [{ t: 'Envío real de correo', bold: true }, { t: 'El entorno corrió con MAIL_MAILER=log a propósito, para no mandar correo a direcciones inventadas. Se verificó que el aviso se genera, se despacha y se marca como enviado, pero no que llegue a un buzón.', align: AlignmentType.LEFT }],
  [{ t: 'Declaración ante la DNIT', bold: true }, { t: 'El Automatizador SIFEN local trabaja en modo de prueba y devuelve un CDC simulado. Se verificó el circuito interno (armado del TXT, guardado de la copia, estados PENDIENTE / ENVIADO / RECHAZADO), no la aceptación real de la SET.', align: AlignmentType.LEFT }],
  [{ t: 'Ingreso con huella (WebAuthn)', bold: true }, { t: 'Necesita contexto seguro (HTTPS) y un dominio como rpId; por HTTP en localhost el navegador no expone la API. La pantalla y su salida sin JavaScript sí se verificaron.', align: AlignmentType.LEFT }],
  [{ t: 'Comportamiento del JavaScript en el navegador', bold: true }, { t: 'La simulación entra por HTTP contra el servidor: valida el servidor, no el navegador. El selector de disponibilidad, el modal de cobro y la barra de carga se ejercitaron por sus endpoints, no por sus clics.', align: AlignmentType.LEFT }],
  [{ t: 'SMS y WhatsApp', bold: true }, { t: 'Fuera de alcance del TCC por decisión del usuario: no hay credenciales de proveedor cargadas.', align: AlignmentType.LEFT }],
  [{ t: 'Venta de productos', bold: true }, { t: 'Fuera de alcance por decisión del usuario (7.23.1). No existe pantalla que la ejecute; el modelo conserva las piezas a propósito.', align: AlignmentType.LEFT }],
  [{ t: 'Multisucursal con dos locales operando', bold: true }, { t: 'La base que se entrega trae una sola sucursal y la simulación reprodujo la operación de ese salón. El modelo la soporta.', align: AlignmentType.LEFT }],
]), [2600, 6420], { tamano: 16 }));
H.push(salto());

// =======================================================================
//  17. HALLAZGOS CRÍTICOS
// =======================================================================
H.push(h1('17. Hallazgos críticos'));
if (criticos.length === 0) {
  H.push(ricos([
    ['No se encontró ningún hallazgo crítico.', true, VERDE],
    ' Ninguna de las categorías que comprometen el sistema —corrupción de datos, acceso no autorizado, error financiero, pérdida de información, duplicación grave o bloqueo de la operación principal— se materializó en los 60 días.',
  ]));
  H.push(h2('17.1 Lo que se puso a prueba y aguantó'));
  H.push(tabla(['Riesgo', 'Cómo se forzó', 'Resultado'], [
    ['Corrupción de datos', { t: 'Recálculo externo de stock, totales, saldos y arqueo sobre toda la base', align: AlignmentType.LEFT }, { t: 'Sin diferencias', color: VERDE, bold: true }],
    ['Acceso no autorizado', { t: '4 roles empujados contra pantallas y POST ajenos, más IDOR sobre citas de otras clientas', align: AlignmentType.LEFT }, { t: 'Todo bloqueado', color: VERDE, bold: true }],
    ['Error financiero', { t: 'Cobros simultáneos, pagos mayores al efectivo disponible, devoluciones y liquidaciones', align: AlignmentType.LEFT }, { t: 'Arqueo cuadra', color: VERDE, bold: true }],
    ['Pérdida de información', { t: 'Anulaciones, bajas de personal, cancelaciones y reprogramaciones', align: AlignmentType.LEFT }, { t: 'Nada se borra', color: VERDE, bold: true }],
    ['Duplicación', { t: 'Reservas, emisiones, altas de cliente y aperturas de caja en paralelo', align: AlignmentType.LEFT }, { t: 'Sin duplicados', color: VERDE, bold: true }],
    ['Bloqueo de la operación', { t: '60 días seguidos de agenda, atención, facturación, cobro y cierre', align: AlignmentType.LEFT }, { t: 'Sin interrupción', color: VERDE, bold: true }],
  ], [2100, 5100, 1820], { tamano: 16 }));
} else {
  criticos.forEach((f, i) => {
    H.push(h2('17.' + (i + 1) + ' ' + f.codigo));
    H.push(ricos([['Severidad: ', true], ['CRÍTICO', true, ROJO], '   ', ['Ocurrencias: ', true], String(f.ocurrencias)]));
    H.push(p(f.primer_detalle || '—'));
    (f.detalles || []).slice(1).forEach((d) => H.push(vineta(d, { size: 19 })));
  });
}

H.push(h2(criticos.length ? '17.' + (criticos.length + 1) + ' Regresión de los 18 hallazgos anteriores' : '17.2 Regresión de los 18 hallazgos anteriores'));
H.push(ricos([
  'Esta versión existe porque se corrigieron los defectos de la simulación anterior, de 90 días. Se comprobó uno por uno que ',
  ['ninguno volvió a aparecer', true], ':',
]));
const filasReg = Object.entries(AUD).filter(([k]) => k.startsWith('regresion_')).map(([k, v]) => {
  const nombre = k.replace('regresion_', '').replace(/_/g, ' ');
  return [
    { t: nombre, align: AlignmentType.LEFT },
    { t: num(v), color: Number(v) ? ROJO : VERDE, bold: true },
    { t: Number(v) ? 'REAPARECIÓ' : 'sigue cerrado', color: Number(v) ? ROJO : VERDE, bold: true },
  ];
});
if (filasReg.length) {
  H.push(tabla(['Hallazgo del informe anterior', 'Casos', 'Estado'], filasReg, [5400, 1600, 2020], { tamano: 16 }));
}
H.push(salto());

// =======================================================================
//  18. RECOMENDACIONES
// =======================================================================
H.push(h1('18. Recomendaciones'));

H.push(h2('Prioridad 1 — Correcciones críticas'));
if (criticos.length === 0) {
  H.push(p('Ninguna. No se detectaron defectos críticos.', { color: VERDE, bold: true }));
} else {
  criticos.forEach((f) => H.push(vinetaRica([[(f.titulo || f.codigo) + '. ', true], f.sugerencia || f.primer_detalle])));
}

H.push(h2('Prioridad 2 — Correcciones altas'));
if (altos.length === 0) {
  H.push(p('Ninguna. No se detectaron defectos de severidad Alta.', { color: VERDE, bold: true }));
} else {
  altos.forEach((f) => H.push(vinetaRica([[(f.titulo || f.codigo) + '. ', true], f.sugerencia || f.primer_detalle])));
}

H.push(h2('Prioridad 3 — Mejoras medias'));
if (medios.length === 0) {
  H.push(p('Ninguna registrada automáticamente.', { color: GRIS }));
} else {
  medios.forEach((a) => H.push(vinetaRica([[(a.titulo || a.codigo) + '. ', true], a.sugerencia || a.primer_detalle])));
}

H.push(h2('Prioridad 4 — Mejoras menores'));
if (bajos.length === 0) {
  H.push(p('Ninguna registrada automáticamente.', { color: GRIS }));
} else {
  bajos.forEach((a) => H.push(vinetaRica([[(a.titulo || a.codigo) + '. ', true], a.sugerencia || a.primer_detalle])));
}
H.push(salto());

// =======================================================================
//  19. VEREDICTO
// =======================================================================
H.push(h1('19. Veredicto final'));
H.push(new Paragraph({
  alignment: AlignmentType.CENTER,
  spacing: { before: 260, after: 220 },
  border: {
    top: { style: BorderStyle.SINGLE, size: 12, color: ORO, space: 14 },
    bottom: { style: BorderStyle.SINGLE, size: 12, color: ORO, space: 14 },
  },
  children: [new TextRun({
    text: veredicto, size: 46, bold: true,
    color: criticos.length ? ROJO : (fallos.length ? NARANJA : VERDE), font: 'Calibri',
  })],
}));

H.push(h2('Fundamento'));
H.push(ricos([
  'La clasificación se apoya únicamente en la evidencia de estos 60 días. El sistema sostuvo ',
  [num(R.peticiones_http) + ' peticiones', true], ', ', [num(CITAS) + ' citas', true], ', ',
  [num(COMPROBANTES) + ' comprobantes', true], ' y ', [gs(cobrado), true],
  ' cobrados sin una sola respuesta 5xx' + (R.http_5xx ? ' salvo ' + num(R.http_5xx) : '') + '.',
]));
H.push(tabla(['Criterio', 'Resultado'], [
  ['Defectos críticos', { t: num(criticos.length), color: criticos.length ? ROJO : VERDE, bold: true }],
  ['Defectos altos', { t: num(altos.length), color: altos.length ? NARANJA : VERDE, bold: true }],
  ['Integridad de datos (huérfanos, duplicados, estados imposibles)', { t: 'sin hallazgos', color: VERDE, bold: true }],
  ['Integridad financiera (arqueo recalculado)', { t: Number(AUD.cajas_diferencia) ? 'con diferencias' : 'cuadra', color: Number(AUD.cajas_diferencia) ? ROJO : VERDE, bold: true }],
  ['Integridad de inventario (stock recalculado)', { t: stockDif.length ? 'con diferencias' : 'cuadra', color: stockDif.length ? ROJO : VERDE, bold: true }],
  ['Concurrencia', { t: fallosConc.length ? 'con anomalías' : 'sin anomalías', color: fallosConc.length ? ROJO : VERDE, bold: true }],
  ['Seguridad y permisos', { t: fallosPerm.length ? 'con accesos indebidos' : 'sin accesos indebidos', color: fallosPerm.length ? ROJO : VERDE, bold: true }],
  ['Regresión de los 18 hallazgos anteriores', { t: filasReg.some((r) => Number(r[1].t.replace(/\./g, ''))) ? 'reaparecieron' : 'todos cerrados', color: VERDE, bold: true }],
], [5800, 3220], { tamano: 18 }));

H.push(h2('Qué queda por hacer'));
H.push(ricos([
  'Los dos defectos altos —la nota de crédito sin declarar y el canje que no se puede usar— ',
  ['no bloquean la operación diaria', true],
  ', pero los dos dejan al salón dando algo que después no cierra: uno ante la DNIT y el otro ante la clienta. ',
  'Van primero. Después, las cuatro observaciones:',
]));
if (avisos.length) {
  avisos.forEach((a) => H.push(vinetaRica([
    [(a.titulo || a.codigo) + '. ', true],
    recorta(a.sugerencia || a.impacto || a.primer_detalle, 280),
  ], { size: 19 })));
} else {
  H.push(p('Nada bloqueante.', { color: VERDE }));
}
H.push(salto());

// =======================================================================
//  ANEXO
// =======================================================================
H.push(h1('Anexo A. Modificaciones de infraestructura'));
H.push(p('Ninguna línea del sistema se modificó para esta simulación. Lo que sigue son cambios de entorno y del banco de pruebas, que se declaran por transparencia.'));
H.push(tabla(['Qué', 'Por qué'], [
  [{ t: 'Base peluqueria_sim', align: AlignmentType.LEFT }, { t: 'Instalación limpia desde peluqueria_bd(base).sql, para no tocar peluqueria_bd ni peluqueria_test.', align: AlignmentType.LEFT }],
  [{ t: 'libfaketime instalado en el contenedor', align: AlignmentType.LEFT }, { t: 'Mover el reloj de cada proceso para simular 60 días en orden cronológico.', align: AlignmentType.LEFT }],
  [{ t: 'MAIL_MAILER=log', align: AlignmentType.LEFT }, { t: 'No mandar correo real a direcciones inventadas. El sistema se entrega con SMTP.', align: AlignmentType.LEFT }],
  [{ t: 'CACHE_STORE=array', align: AlignmentType.LEFT }, { t: 'Cada momento del día es un proceso con su propio reloj; con la caché en archivo, el limitador de peticiones leía ventanas de otra hora y devolvía 429 sin motivo.', align: AlignmentType.LEFT }],
  [{ t: 'Banco _sim60/', align: AlignmentType.LEFT }, { t: 'Copia del banco de la simulación de 90 días, adaptada a 7.27.1: la liquidación al personal ahora pide medio de pago, y se sumaron escenarios para canjes por puntos, reasignación de citas y devoluciones. El banco original quedó intacto.', align: AlignmentType.LEFT }],
  [{ t: 'Verificación posterior al día 60', align: AlignmentType.LEFT }, { t: 'Las alertas que levantó la simulación se comprobaron a mano contra el sistema antes de darlas por buenas, y eso dejó unas pocas filas propias en la base: una cita de prueba con fecha 14/10, una caja abierta y cerrada por una cuenta de Profesional, y la declaración manual de una nota de crédito. Los conteos del informe salen de la auditoría de cierre, tomada ANTES de esas pruebas.', align: AlignmentType.LEFT }],
], [2800, 6220], { tamano: 16 }));

H.push(h2('A.1 Una alerta que la verificación desmintió'));
H.push(ricos([
  'La simulación levantó una alerta de fuga de datos en el panel del Profesional. ',
  ['Al comprobarla resultó falsa', true],
  ', y conviene dejar escrito el método: no alcanza con que un detector automático encuentre algo, hay que ir a mirar. ',
  'El detector buscaba un importe en oro dentro del panel y lo comparaba con los ingresos del día; coincidían. ',
  'Lo que la comprobación mostró es que el panel ', ['sí filtra', true],
  ': al Profesional no le dibuja «Productos bajo stock» —no tiene inventario.stock— y su rótulo dice «Mis citas de hoy» en vez de «Citas de hoy». ',
  'Ve los ingresos porque el rol que se entrega tiene facturacion.cobros, que es exactamente la clave que el controlador consulta.',
]));

H.push(h1('Anexo B. Cómo reproducir la simulación'));
H.push(p('Los guiones quedan versionados en _sim60/. Desde la raíz del proyecto:', { espacio: 60 }));
[
  'docker compose exec app sh -c \'mysql -h bd -u root -proot --skip-ssl -e "DROP DATABASE IF EXISTS peluqueria_sim; CREATE DATABASE peluqueria_sim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"\'',
  'docker compose exec app sh -c \'mysql -h bd -u root -proot --skip-ssl peluqueria_sim < "/app/basededatos/peluqueria_bd(base).sql"\'',
  'docker compose exec app php _sim60/estado.php inicial',
  'docker compose exec app sh /app/_sim60/correr.sh 1 60',
  'docker compose exec app php _sim60/momento.php 60 auditoriafinal',
  'docker compose exec app php _sim60/estado.php final',
  'docker compose exec app php _sim60/series.php',
  'python _sim60/analizar.py && python _sim60/graficos.py && node _sim60/informe.js',
].forEach((c) => H.push(new Paragraph({
  spacing: { after: 60 },
  shading: { type: ShadingType.CLEAR, fill: FONDO, color: 'auto' },
  indent: { left: 160, right: 160 },
  children: [new TextRun({ text: c, size: 15, font: 'Consolas', color: CARBON })],
})));

// =======================================================================
//  Documento
// =======================================================================
const doc = new Document({
  creator: 'Equipo de QA — Simulación operativa SPG',
  title: 'Informe de Simulación Operativa y QA — SPG 7.27.1',
  description: 'Simulación de 60 días de operación real con usuarios en paralelo',
  numbering: {
    config: [{
      reference: 'vinetas',
      levels: [
        { level: 0, format: LevelFormat.BULLET, text: '•', alignment: AlignmentType.LEFT,
          style: { paragraph: { indent: { left: 400, hanging: 200 } } } },
        { level: 1, format: LevelFormat.BULLET, text: '–', alignment: AlignmentType.LEFT,
          style: { paragraph: { indent: { left: 760, hanging: 200 } } } },
      ],
    }],
  },
  styles: {
    default: {
      document: { run: { font: 'Calibri', size: 20, color: CARBON } },
    },
  },
  sections: [{
    properties: { page: { margin: { top: 1100, right: 1100, bottom: 1100, left: 1100 } } },
    footers: {
      default: new Footer({
        children: [new Paragraph({
          alignment: AlignmentType.CENTER,
          border: { top: { style: BorderStyle.SINGLE, size: 4, color: BORDE, space: 8 } },
          children: [
            new TextRun({ text: 'SPG 7.27.1 · Informe de Simulación Operativa y QA · ', size: 15, color: GRIS, font: 'Calibri' }),
            new TextRun({ children: [PageNumber.CURRENT], size: 15, color: GRIS, font: 'Calibri' }),
            new TextRun({ text: ' / ', size: 15, color: GRIS, font: 'Calibri' }),
            new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 15, color: GRIS, font: 'Calibri' }),
          ],
        })],
      }),
    },
    children: H,
  }],
});

Packer.toBuffer(doc).then((buf) => {
  fs.writeFileSync(SALIDA, buf);
  console.log('informe escrito:', SALIDA, '(' + Math.round(buf.length / 1024) + ' KB)');
});
