#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""Gráficos del informe. Lee _sim60/log/resumen.json y deja los PNG al lado."""

import json
import os
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.ticker import FuncFormatter

LOG = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'log')

ORO = '#C9A84C'
ORO_CLARO = '#E8CC80'
CARBON = '#3A3733'
VERDE = '#2F5D2F'
ROJO = '#993535'
GRIS = '#8C8880'
BORDE = '#D8D4CE'

plt.rcParams.update({
    'font.family': 'DejaVu Sans',
    'font.size': 9,
    'axes.edgecolor': BORDE,
    'axes.labelcolor': CARBON,
    'text.color': CARBON,
    'xtick.color': GRIS,
    'ytick.color': GRIS,
    'axes.grid': True,
    'grid.color': '#EDEAE5',
    'grid.linewidth': 0.8,
    'figure.facecolor': 'white',
    'axes.facecolor': 'white',
})

with open(os.path.join(LOG, 'resumen.json'), encoding='utf-8') as f:
    R = json.load(f)

S = R.get('series', {}) or {}


def limpiar(ax):
    for lado in ('top', 'right'):
        ax.spines[lado].set_visible(False)


def gs(x, _=None):
    return f'{x:,.0f}'.replace(',', '.')


def serie_ordenada(nombre):
    d = S.get(nombre, {}) or {}
    claves = sorted(d.keys())
    return claves, [d[k] for k in claves]


# ---------------------------------------------------------------------------
# 1. Actividad diaria: citas agendadas para cada día
# ---------------------------------------------------------------------------
dias, citas = serie_ordenada('citas_por_dia')
if dias:
    _, atend = serie_ordenada('citas_atendidas_por_dia')
    at = S.get('citas_atendidas_por_dia', {}) or {}
    can = S.get('citas_canceladas_por_dia', {}) or {}
    aus = S.get('citas_ausentes_por_dia', {}) or {}

    fig, ax = plt.subplots(figsize=(9.2, 3.1))
    x = range(len(dias))
    ax.bar(x, citas, color=ORO_CLARO, width=0.82, label='Agendadas')
    ax.bar(x, [at.get(d, 0) for d in dias], color=ORO, width=0.82, label='Atendidas')
    ax.plot(x, [can.get(d, 0) + aus.get(d, 0) for d in dias], color=ROJO,
            linewidth=1.4, label='Canceladas + ausentes')
    paso = max(1, len(dias) // 12)
    ax.set_xticks(list(x)[::paso])
    ax.set_xticklabels([dias[i][5:] for i in list(x)[::paso]], rotation=0)
    ax.set_ylabel('citas')
    ax.legend(frameon=False, ncol=3, fontsize=8, loc='upper left')
    limpiar(ax)
    fig.tight_layout()
    fig.savefig(os.path.join(LOG, 'graf_citas.png'), dpi=170)
    plt.close(fig)

# ---------------------------------------------------------------------------
# 2. Facturación y cobranza por día, con acumulado
# ---------------------------------------------------------------------------
dias, fact = serie_ordenada('facturado_por_dia')
if dias:
    cob = S.get('cobrado_por_dia', {}) or {}
    fig, ax = plt.subplots(figsize=(9.2, 3.1))
    x = range(len(dias))
    ax.bar(x, fact, color=ORO_CLARO, width=0.82, label='Facturado')
    ax.plot(x, [cob.get(d, 0) for d in dias], color=CARBON, linewidth=1.3, label='Cobrado')
    ax.yaxis.set_major_formatter(FuncFormatter(gs))
    paso = max(1, len(dias) // 12)
    ax.set_xticks(list(x)[::paso])
    ax.set_xticklabels([dias[i][5:] for i in list(x)[::paso]])
    ax.set_ylabel('Gs.')
    ax.legend(frameon=False, ncol=2, fontsize=8, loc='upper left')

    ax2 = ax.twinx()
    acum = []
    t = 0
    for v in fact:
        t += v
        acum.append(t)
    ax2.plot(x, acum, color=ORO, linewidth=1.8, linestyle='--', label='Acumulado')
    ax2.yaxis.set_major_formatter(FuncFormatter(gs))
    ax2.grid(False)
    ax2.legend(frameon=False, fontsize=8, loc='lower right')
    limpiar(ax)
    for lado in ('top',):
        ax2.spines[lado].set_visible(False)
    fig.tight_layout()
    fig.savefig(os.path.join(LOG, 'graf_facturacion.png'), dpi=170)
    plt.close(fig)

# ---------------------------------------------------------------------------
# 3. Cómo terminaron las citas
# ---------------------------------------------------------------------------
fin = R.get('estado_final', {}) or {}
estados = fin.get('citas_por_estado', {}) or {}
if estados:
    orden = sorted(estados.items(), key=lambda kv: -kv[1])
    etiquetas = [k for k, _ in orden]
    valores = [v for _, v in orden]
    colores = []
    for e in etiquetas:
        el = e.lower()
        if 'atend' in el:
            colores.append(VERDE)
        elif 'cancel' in el or 'ausent' in el:
            colores.append(ROJO)
        elif 'proceso' in el or 'atras' in el:
            colores.append(ORO)
        else:
            colores.append(GRIS)
    fig, ax = plt.subplots(figsize=(5.4, 3.0))
    b = ax.barh(etiquetas[::-1], valores[::-1], color=colores[::-1], height=0.66)
    total = sum(valores) or 1
    for r, v in zip(b, valores[::-1]):
        ax.text(r.get_width() + total * 0.012, r.get_y() + r.get_height() / 2,
                f'{v}  ({100*v/total:.0f}%)', va='center', fontsize=8, color=CARBON)
    ax.set_xlim(0, max(valores) * 1.28)
    ax.set_xticks([])
    limpiar(ax)
    ax.spines['bottom'].set_visible(False)
    ax.grid(False)
    fig.tight_layout()
    fig.savefig(os.path.join(LOG, 'graf_estados.png'), dpi=170)
    plt.close(fig)

# ---------------------------------------------------------------------------
# 4. Arqueo: saldo de cada caja del período
# ---------------------------------------------------------------------------
cajas = S.get('cajas', []) or []
if cajas:
    fig, ax = plt.subplots(figsize=(9.2, 2.8))
    x = range(len(cajas))
    ax.bar(x, [c['saldo'] for c in cajas], color=ORO, width=0.8, label='Saldo al cierre')
    ax.plot(x, [c['inicial'] for c in cajas], color=GRIS, linewidth=1.2,
            linestyle=':', label='Monto inicial')
    ax.axhline(0, color=ROJO, linewidth=1.0)
    ax.yaxis.set_major_formatter(FuncFormatter(gs))
    paso = max(1, len(cajas) // 12)
    ax.set_xticks(list(x)[::paso])
    ax.set_xticklabels([cajas[i]['dia'][5:] for i in list(x)[::paso]])
    ax.set_ylabel('Gs. en el cajón')
    ax.legend(frameon=False, ncol=2, fontsize=8, loc='upper left')
    limpiar(ax)
    fig.tight_layout()
    fig.savefig(os.path.join(LOG, 'graf_caja.png'), dpi=170)
    plt.close(fig)

# ---------------------------------------------------------------------------
# 5. Inventario: teórico contra lo que dice el sistema
# ---------------------------------------------------------------------------
stock = S.get('stock_final', []) or []
if stock:
    stock = sorted(stock, key=lambda p: -p['salidas'])[:12]
    fig, ax = plt.subplots(figsize=(9.2, 3.4))
    y = range(len(stock))
    nombres = [p['nombre'][:30] for p in stock]
    ax.barh([i + 0.19 for i in y], [p['teorico'] for p in stock],
            height=0.36, color=ORO_CLARO, label='Entradas − salidas (recalculado)')
    ax.barh([i - 0.19 for i in y], [p['sistema'] for p in stock],
            height=0.36, color=CARBON, label='fn_producto_stock (sistema)')
    ax.set_yticks(list(y))
    ax.set_yticklabels(nombres, fontsize=8)
    ax.invert_yaxis()
    ax.axvline(0, color=ROJO, linewidth=1.0)
    ax.set_xlabel('unidades de compra')
    ax.legend(frameon=False, fontsize=8, loc='lower right')
    limpiar(ax)
    fig.tight_layout()
    fig.savefig(os.path.join(LOG, 'graf_stock.png'), dpi=170)
    plt.close(fig)

# ---------------------------------------------------------------------------
# 5 bis. Clientes que se van sumando y movimiento de inventario
# ---------------------------------------------------------------------------
dias, altas = serie_ordenada('clientes_acumulados')
if dias:
    mov = S.get('movimientos_inventario_por_dia', {}) or {}
    acum = []
    tot = 0
    for v in altas:
        tot += v
        acum.append(tot)

    fig, ax = plt.subplots(figsize=(9.2, 2.8))
    x = range(len(dias))
    ax.fill_between(x, acum, color=ORO_CLARO, alpha=0.55)
    ax.plot(x, acum, color=ORO, linewidth=1.8, label='Clientes acumulados')
    ax.set_ylabel('clientes')
    paso = max(1, len(dias) // 12)
    ax.set_xticks(list(x)[::paso])
    ax.set_xticklabels([dias[i][5:] for i in list(x)[::paso]])
    ax.legend(frameon=False, fontsize=8, loc='upper left')

    ax2 = ax.twinx()
    ax2.bar(x, [mov.get(d, 0) for d in dias], color=CARBON, alpha=0.30, width=0.8,
            label='Movimientos de inventario')
    ax2.set_ylabel('movimientos')
    ax2.grid(False)
    ax2.legend(frameon=False, fontsize=8, loc='upper right')
    limpiar(ax)
    ax2.spines['top'].set_visible(False)
    fig.tight_layout()
    fig.savefig(os.path.join(LOG, 'graf_clientes.png'), dpi=170)
    plt.close(fig)

# ---------------------------------------------------------------------------
# 6. Hallazgos por severidad
# ---------------------------------------------------------------------------
sev = R.get('severidades', {}) or {}
if sev:
    orden = ['CRITICO', 'ALTO', 'MEDIO', 'BAJO']
    etiquetas = [s.capitalize() for s in orden]
    valores = [sev.get(s, 0) for s in orden]
    colores = [ROJO, '#C06A2E', ORO, GRIS]
    fig, ax = plt.subplots(figsize=(5.4, 2.5))
    b = ax.bar(etiquetas, valores, color=colores, width=0.6)
    for r, v in zip(b, valores):
        ax.text(r.get_x() + r.get_width() / 2, r.get_height() + max(valores + [1]) * 0.04,
                str(v), ha='center', fontsize=10, color=CARBON)
    ax.set_ylim(0, max(valores + [1]) * 1.28)
    ax.set_yticks([])
    limpiar(ax)
    ax.spines['left'].set_visible(False)
    ax.grid(False)
    fig.tight_layout()
    fig.savefig(os.path.join(LOG, 'graf_severidad.png'), dpi=170)
    plt.close(fig)

# ---------------------------------------------------------------------------
# 7. Equipo: generado contra comisión
# ---------------------------------------------------------------------------
eq = [e for e in (S.get('equipo', []) or []) if e['servicios'] > 0]
if eq:
    eq = sorted(eq, key=lambda e: -e['generado'])
    fig, ax = plt.subplots(figsize=(9.2, 2.9))
    x = range(len(eq))
    ax.bar([i - 0.19 for i in x], [e['generado'] for e in eq], width=0.36,
           color=ORO_CLARO, label='Generado para el salón')
    ax.bar([i + 0.19 for i in x], [e['comision'] for e in eq], width=0.36,
           color=ORO, label='Comisión que le toca')
    ax.set_xticks(list(x))
    ax.set_xticklabels([e['nombre'].split(' ')[0] for e in eq], fontsize=8)
    ax.yaxis.set_major_formatter(FuncFormatter(gs))
    ax.set_ylabel('Gs.')
    ax.legend(frameon=False, ncol=2, fontsize=8)
    limpiar(ax)
    fig.tight_layout()
    fig.savefig(os.path.join(LOG, 'graf_equipo.png'), dpi=170)
    plt.close(fig)

print('gráficos generados en', LOG)
for f in sorted(os.listdir(LOG)):
    if f.startswith('graf_'):
        print('  ', f)
