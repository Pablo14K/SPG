#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Convierte el registro crudo de la simulación en los datos del informe.

    python _sim60/analizar.py

Lee  _sim60/log/ops.jsonl, estado_inicial.json, estado_final.json y series.json
Deja _sim60/log/resumen.json  y los gráficos en _sim60/log/graf_*.png
"""

import json
import os
import collections

LOG = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'log')


def leer_jsonl(nombre):
    filas = []
    ruta = os.path.join(LOG, nombre)
    if not os.path.exists(ruta):
        return filas
    with open(ruta, encoding='utf-8') as f:
        for linea in f:
            linea = linea.strip()
            if not linea:
                continue
            try:
                filas.append(json.loads(linea))
            except Exception:
                pass
    return filas


def leer_json(nombre, defecto=None):
    ruta = os.path.join(LOG, nombre)
    if not os.path.exists(ruta):
        return defecto
    with open(ruta, encoding='utf-8') as f:
        return json.load(f)


ops = leer_jsonl('ops.jsonl')
inicial = leer_json('estado_inicial.json', {})
final = leer_json('estado_final.json', {})
series = leer_json('series.json', {})

# ---------------------------------------------------------------------------
#  Clasificación
# ---------------------------------------------------------------------------
SEV_FAIL = ('CRITICO', 'ALTO')

incidentes = [o for o in ops if o.get('tipo') == 'INCIDENTE']
checks_ok = [o for o in ops if o.get('tipo') in ('CHECK_OK', 'PERM_OK', 'ANOM_OK')]
peticiones = [o for o in ops if o.get('tipo') == 'REQ']

# Un incidente puede repetirse muchas veces (el mismo defecto, varios días).
# Para el informe se agrupa por código: un defecto es UNO, con N ocurrencias.
por_codigo = collections.OrderedDict()
for i in incidentes:
    cod = i.get('cod', '?')
    if cod not in por_codigo:
        por_codigo[cod] = {
            'codigo': cod,
            'severidad': i.get('sev', 'ALTO'),
            'ocurrencias': 0,
            'primer_detalle': i.get('det', ''),
            'detalles': [],
            'primera_vez': i.get('t', ''),
        }
    e = por_codigo[cod]
    e['ocurrencias'] += 1
    # se guarda la severidad más grave vista
    orden = {'CRITICO': 4, 'ALTO': 3, 'MEDIO': 2, 'BAJO': 1}
    if orden.get(i.get('sev', 'ALTO'), 0) > orden.get(e['severidad'], 0):
        e['severidad'] = i.get('sev')
    if len(e['detalles']) < 4 and i.get('det') not in e['detalles']:
        e['detalles'].append(i.get('det', ''))

# ---------------------------------------------------------------------------
#  Verificación dirigida: lo que la evidencia confirmó o descartó
# ---------------------------------------------------------------------------
# Un detector automático puede equivocarse, y equivocarse en las dos
# direcciones. Todo lo que la simulación levantó se contrastó a mano contra el
# sistema antes de darlo por bueno: lo que no se sostuvo se descarta acá (con
# el motivo, que va al informe) y lo que se confirmó a mano se suma.
HALL = leer_json(os.path.join('..', 'hallazgos.json'), None)
if HALL is None:
    ruta_h = os.path.join(os.path.dirname(LOG), 'hallazgos.json')
    HALL = json.load(open(ruta_h, encoding='utf-8')) if os.path.exists(ruta_h) else {}

descartados = HALL.get('descartados', {}) or {}
for cod, motivo in descartados.items():
    if cod in por_codigo:
        por_codigo[cod]['descartado'] = motivo
        del por_codigo[cod]

for h in (HALL.get('verificados', []) or []) + (HALL.get('observaciones', []) or []):
    por_codigo[h['codigo']] = {
        'codigo': h['codigo'],
        'severidad': h['severidad'],
        'ocurrencias': 1,
        'primer_detalle': h.get('detalle', ''),
        'detalles': [],
        'primera_vez': '',
        'verificado': True,
        'titulo': h.get('titulo', ''),
        'modulo': h.get('modulo', ''),
        'reproduccion': h.get('reproduccion', ''),
        'evidencia': h.get('evidencia', ''),
        'impacto': h.get('impacto', ''),
        'sugerencia': h.get('sugerencia', ''),
    }

fallos = [e for e in por_codigo.values() if e['severidad'] in SEV_FAIL]
avisos = [e for e in por_codigo.values() if e['severidad'] not in SEV_FAIL]

# ---------------------------------------------------------------------------
#  HTTP
# ---------------------------------------------------------------------------
estados = collections.Counter(o.get('st') for o in peticiones)
err500 = [o for o in peticiones if isinstance(o.get('st'), int) and o['st'] >= 500]
por_usuario = collections.Counter(o.get('quien', '-') for o in peticiones)

# ---------------------------------------------------------------------------
#  Concurrencia
# ---------------------------------------------------------------------------
conc = [o for o in ops if o.get('tipo') == 'CONC']
conc_resumen = []
for c in conc:
    salidas = c.get('salidas', []) or []
    aceptadas = 0
    for s in salidas:
        txt = ' '.join(s.get('flash', []) or [])
        if 'success' in txt:
            aceptadas += 1
    conc_resumen.append({
        'caso': c.get('caso', '?'),
        'procesos': len(salidas),
        'aceptadas': aceptadas,
        'datos': {k: v for k, v in c.items()
                  if k not in ('tipo', 'caso', 'salidas', 't')},
    })

# ---------------------------------------------------------------------------
#  Cobertura por módulo, deducida de las URI realmente pedidas
# ---------------------------------------------------------------------------
MODULOS = [
    ('Citas y agenda', ('/citas',)),
    ('Clientes y fidelización', ('/clientes',)),
    ('Servicios y descuentos', ('/servicios',)),
    ('Inventario', ('/inventario',)),
    ('Tesorería (facturación y caja)', ('/facturacion',)),
    ('Reportes', ('/reportes',)),
    ('Seguridad', ('/seguridad',)),
    ('Portal de la clienta', ('/portal', '/registro', '/verificar', '/mi-cita')),
    ('Acceso y cuenta', ('/entrar', '/salir', '/cuenta', '/huella', '/recuperar')),
    ('Panel', ('/panel',)),
]


def modulo_de(uri):
    u = (uri or '').split('?')[0]
    for nombre, prefijos in MODULOS:
        for p in prefijos:
            if u == p or u.startswith(p + '/') or u == p.rstrip('/'):
                return nombre
    return 'Otros'


cobertura = collections.defaultdict(lambda: {
    'rutas': set(), 'peticiones': 0, 'pass': 0, 'fail': 0, 'warn': 0, 'http5xx': 0})

for o in peticiones:
    m = modulo_de(o.get('uri'))
    c = cobertura[m]
    c['rutas'].add((o.get('uri') or '').split('?')[0])
    c['peticiones'] += 1
    st = o.get('st')
    if isinstance(st, int) and st >= 500:
        c['http5xx'] += 1

# Los incidentes se reparten por el módulo que nombran, si se puede deducir
PISTAS = {
    'CITA': 'Citas y agenda', 'AGENDA': 'Citas y agenda', 'REASIGN': 'Citas y agenda',
    'CANJE': 'Clientes y fidelización', 'CLIENTE': 'Clientes y fidelización',
    'PUNTOS': 'Clientes y fidelización', 'FIDELIZACION': 'Clientes y fidelización',
    'STOCK': 'Inventario', 'INVENTARIO': 'Inventario', 'CONSUMO': 'Inventario',
    'FACTURA': 'Tesorería (facturación y caja)', 'COBRO': 'Tesorería (facturación y caja)',
    'CAJA': 'Tesorería (facturación y caja)', 'NC_': 'Tesorería (facturación y caja)',
    'CORRELATIVO': 'Tesorería (facturación y caja)', 'LIQUIDACION': 'Tesorería (facturación y caja)',
    'PAGO': 'Tesorería (facturación y caja)', 'SENA': 'Tesorería (facturación y caja)',
    'REPORTE': 'Reportes', 'PERM': 'Seguridad', 'ANON': 'Seguridad', 'IDOR': 'Seguridad',
    'LOGIN': 'Seguridad', 'THROTTLE': 'Seguridad', 'AUDITORIA': 'Seguridad',
    'PORTAL': 'Portal de la clienta', 'NOTIF': 'Notificaciones',
    'RECORDATORIO': 'Notificaciones', 'PANEL': 'Panel', 'BAJA': 'Seguridad',
    'SIFEN': 'Tesorería (facturación y caja)', 'PRODUCTO': 'Inventario',
    'HUERFANO': 'Integridad transversal', 'TEMPORAL': 'Integridad transversal',
    'REGRESION': 'Integridad transversal', 'SOLAPE': 'Citas y agenda',
    'ATENDIDA': 'Citas y agenda', 'REPARTO': 'Citas y agenda',
    'A1_': 'Citas y agenda', 'A2_': 'Citas y agenda', 'A3_': 'Citas y agenda',
    'A4_': 'Citas y agenda', 'A5_': 'Citas y agenda', 'A6_': 'Citas y agenda',
    'A7_': 'Citas y agenda', 'A8_': 'Citas y agenda', 'A9_': 'Citas y agenda',
    'A10': 'Citas y agenda', 'A11': 'Citas y agenda',
    'B1_': 'Citas y agenda', 'B2_': 'Citas y agenda', 'B3_': 'Citas y agenda',
    'B4_': 'Citas y agenda', 'B5_': 'Citas y agenda', 'B6_': 'Citas y agenda',
    'CONC_A': 'Citas y agenda', 'CONC_B': 'Tesorería (facturación y caja)',
    'CONC_C': 'Tesorería (facturación y caja)', 'CONC_D': 'Inventario',
    'CONC_E': 'Clientes y fidelización', 'CONC_F': 'Tesorería (facturación y caja)',
    'CONC_G': 'Citas y agenda', 'CONC_H': 'Tesorería (facturación y caja)',
    'INIT_': 'Otros', 'CRON': 'Notificaciones',
}

# Rótulo corto para los defectos que salen del registro y no traen título
TITULOS = {
    'CAJA_SIN_MOVIMIENTO_MANUAL': 'No hay pantalla para un movimiento manual de caja',
    'AUD_SIN_VENTA_DE_PRODUCTOS': 'La venta de productos no tiene pantalla (fuera de alcance)',
}


def modulo_incidente(cod):
    for pista, mod in PISTAS.items():
        if pista in cod:
            return mod
    return 'Otros'


for e in por_codigo.values():
    m = e.get('modulo') or modulo_incidente(e['codigo'])
    e.setdefault('modulo', m)
    if not e.get('titulo'):
        e['titulo'] = TITULOS.get(e['codigo'], '')
    if e['severidad'] in SEV_FAIL:
        cobertura[m]['fail'] += 1
    else:
        cobertura[m]['warn'] += 1

for o in checks_ok:
    cod = o.get('cod', '')
    cobertura[modulo_incidente(cod)]['pass'] += 1

cobertura_lista = []
for m, c in sorted(cobertura.items()):
    cobertura_lista.append({
        'modulo': m,
        'rutas': len(c['rutas']),
        'peticiones': c['peticiones'],
        'pass': c['pass'],
        'fail': c['fail'],
        'warn': c['warn'],
        'http5xx': c['http5xx'],
    })

# ---------------------------------------------------------------------------
#  Resumen
# ---------------------------------------------------------------------------
total_checks = len(checks_ok) + len(incidentes)
resumen = {
    'peticiones_http': len(peticiones),
    'operaciones_por_usuario': dict(por_usuario.most_common()),
    'http_por_estado': {str(k): v for k, v in sorted(estados.items(), key=lambda x: str(x[0]))},
    'http_5xx': len(err500),
    'comprobaciones_totales': total_checks,
    'comprobaciones_ok': len(checks_ok),
    'incidencias_registradas': len(incidentes),
    'defectos_distintos': len(por_codigo),
    'fallos': sorted(fallos, key=lambda e: (0 if e['severidad'] == 'CRITICO' else 1, -e['ocurrencias'])),
    'avisos': sorted(avisos, key=lambda e: (0 if e['severidad'] == 'MEDIO' else 1, -e['ocurrencias'])),
    'severidades': dict(collections.Counter(e['severidad'] for e in por_codigo.values())),
    'concurrencia': conc_resumen,
    'cobertura': cobertura_lista,
    'estado_inicial': inicial,
    'estado_final': final,
    'series': series,
    'auditoria_final': next((o.get('r', {}) for o in reversed(ops)
                             if o.get('tipo') == 'AUDITORIA_FINAL'), {}),
    'fidelizacion': [o for o in ops if o.get('tipo') == 'FIDELIZACION'],
    'reservas_por_dia': [o for o in ops if o.get('tipo') == 'RESERVAS'],
    'throttle': [o for o in ops if o.get('tipo') == 'THROTTLE'],
    'descartados': descartados,
    'no_verificado': HALL.get('no_verificado', []) or [],
    'verificacion': leer_json('verificacion.json', {}),
    'verificacion2': leer_json('verificacion2.json', {}),
}

if total_checks:
    resumen['porcentaje_exito'] = round(100.0 * len(checks_ok) / total_checks, 2)
else:
    resumen['porcentaje_exito'] = 0.0

with open(os.path.join(LOG, 'resumen.json'), 'w', encoding='utf-8') as f:
    json.dump(resumen, f, ensure_ascii=False, indent=2, default=str)

print('peticiones      :', resumen['peticiones_http'])
print('comprobaciones  :', total_checks, '(ok', len(checks_ok), ')')
print('defectos        :', len(por_codigo), resumen['severidades'])
print('http 5xx        :', len(err500))
print('concurrencia    :', len(conc_resumen), 'escenarios')
print('resumen.json guardado')
