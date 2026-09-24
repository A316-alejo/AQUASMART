/**
 * Gráficos SVG sin dependencias: se dibujan desde los datos que
 * cada vista Blade expone en un <script type="application/json">.
 */
window.AquaCharts = (function () {
  function line(el, series, labels, opts) {
    opts = opts || {};
    const w = 620, h = opts.h || 220, pl = 36, pr = 10, pt = 12, pb = 24;
    let all = [];
    series.forEach(s => { all = all.concat(s.data); });
    if (!all.length) { el.innerHTML = '<p style="color:var(--ink-3);font-size:13px">Sin lecturas en este periodo.</p>'; return; }

    let min = Math.min(...all), max = Math.max(...all);
    const pad = (max - min) * 0.15 || 1;
    min -= pad; max += pad;
    const n = labels.length;
    const X = i => pl + i * (w - pl - pr) / Math.max(n - 1, 1);
    const Y = v => pt + (1 - (v - min) / (max - min)) * (h - pt - pb);

    let g = '';
    for (let k = 0; k <= 3; k++) {
      const y = pt + k * (h - pt - pb) / 3;
      g += `<line x1="${pl}" y1="${y}" x2="${w - pr}" y2="${y}" stroke="var(--line)" stroke-width="1"/>`;
      g += `<text x="4" y="${y + 3.5}" font-size="10" fill="var(--ink-3)">${(max - (max - min) * k / 3).toFixed(1)}</text>`;
    }
    labels.forEach((l, i) => {
      if (n > 8 && i % Math.ceil(n / 8)) return;
      g += `<text x="${X(i)}" y="${h - 7}" font-size="10" fill="var(--ink-3)" text-anchor="middle">${l}</text>`;
    });
    series.forEach(s => {
      const d = s.data.map((v, i) => `${i ? 'L' : 'M'}${X(i).toFixed(1)} ${Y(v).toFixed(1)}`).join(' ');
      if (opts.area) g += `<path d="${d} L${X(n - 1)} ${h - pb} L${pl} ${h - pb} Z" fill="${s.color}" opacity=".10"/>`;
      g += `<path d="${d}" fill="none" stroke="${s.color}" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>`;
      g += `<circle cx="${X(n - 1)}" cy="${Y(s.data[n - 1])}" r="3.6" fill="${s.color}"/>`;
    });
    if (opts.threshold !== undefined) {
      g += `<line x1="${pl}" y1="${Y(opts.threshold)}" x2="${w - pr}" y2="${Y(opts.threshold)}" stroke="var(--ink-3)" stroke-width="1.4" stroke-dasharray="5 4"/>`;
    }
    el.innerHTML = `<svg class="chart" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" style="height:${h}px">${g}</svg>`;
  }

  function gauge(value, min, max, label, unit, state) {
    const pct = Math.max(0, Math.min(1, (value - min) / (max - min)));
    const R = 44, C = 2 * Math.PI * R * 0.75;
    const col = state === 'critical' ? 'var(--crit)' : state === 'warning' ? 'var(--warn)' : 'var(--ok)';
    return `<div class="gauge">
      <svg viewBox="0 0 112 112">
        <circle cx="56" cy="56" r="${R}" fill="none" stroke="var(--line)" stroke-width="9" stroke-linecap="round" stroke-dasharray="${C} 999" transform="rotate(135 56 56)"/>
        <circle cx="56" cy="56" r="${R}" fill="none" stroke="${col}" stroke-width="9" stroke-linecap="round" stroke-dasharray="${(C * pct).toFixed(1)} 999" transform="rotate(135 56 56)"/>
        <text x="56" y="54" text-anchor="middle" font-size="20" font-weight="600" fill="var(--ink)">${value ?? '—'}</text>
        <text x="56" y="70" text-anchor="middle" font-size="10" fill="var(--ink-3)">${unit}</text>
      </svg>
      <div class="g-lab">${label}</div>
      <div class="g-range">Rango ${min}–${max} ${unit}</div>
    </div>`;
  }

  /** Dibuja todo elemento [data-chart] leyendo su JSON embebido. */
  function boot() {
    document.querySelectorAll('[data-chart]').forEach(el => {
      const cfg = JSON.parse(document.getElementById(el.dataset.chart).textContent);
      line(el, cfg.series, cfg.labels, cfg.options || {});
    });
  }

  document.addEventListener('DOMContentLoaded', boot);
  return { line, gauge, boot };
})();
