/*
 * AquaSecure Gestion (layouts/back/app et back/auth) : JS extrait de templates-source/aquasecure-back.html.
 * Sidebar mobile et menus déroulants de la topbar sont gérés par Alpine dans les partials ; ce fichier garde
 * les toasts, la modale de suppression, les graphiques, la matrice des rôles et la carte de localisation.
 */
import './app';
import { $, fill, on } from './lib/dom';

/* ---------------------------------------------------------------- Toasts */

const toastClass = 'min-w-[260px] max-w-[360px] rounded-[9px] border border-l-4 border-line bg-surface px-4 py-3 text-[.88rem] shadow-toast';

function toast(message, error = false) {
    const box = $('toasts');
    if (!box) return;
    const t = document.createElement('div');
    t.className = toastClass + (error ? ' border-l-crit' : ' border-l-teal');
    t.textContent = (error ? '✕ ' : '✓ ') + message;
    box.appendChild(t);
    setTimeout(() => t.remove(), 3200);
}

/* Messages flash rendus par Laravel (session status / error) : retirés comme les autres toasts */
document.querySelectorAll('#toasts > div').forEach((t) => setTimeout(() => t.remove(), 3200));

/* ---------------------------------------------------------------- Modale de suppression (<x-confirm-delete />) */

/* Un bouton [data-del] ouvre la modale ; avec data-action, « Supprimer » envoie le formulaire DELETE #mForm */
const modal = $('modal');
let pending = '';
let pendingAction = '';

document.addEventListener('click', (e) => {
    const del = e.target.closest('[data-del]');
    if (del && modal) {
        pending = del.dataset.del;
        pendingAction = del.dataset.action || '';
        const text = $('mText');
        if (text) text.textContent = `« ${pending} » sera supprimé définitivement, ainsi que les données qui lui sont rattachées.`;
        modal.hidden = false;
        const ok = $('mOk');
        if (ok) ok.focus();
    }

    const t = e.target.closest('[data-toast]');
    if (t) toast(t.dataset.toast);
});

on('mCancel', 'click', () => (modal.hidden = true));
if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) modal.hidden = true; });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal) modal.hidden = true; });
on('mOk', 'click', () => {
    const form = $('mForm');
    if (pendingAction && form) {
        form.action = pendingAction;
        form.submit();
        return;
    }
    modal.hidden = true;
    toast(`« ${pending} » a été supprimé.`);
});

/* ---------------------------------------------------------------- Sparklines (<x-kpi-card :spark="[...]">) */

const spark = (values, color) => {
    const max = Math.max(...values);
    const min = Math.min(...values);
    const w = 160;
    const h = 30;
    const points = values.map((v, i) => [i * (w / (values.length - 1)), h - 3 - ((v - min) / (max - min || 1)) * (h - 6)]);
    const d = points.map((q, i) => (i ? 'L' : 'M') + q[0].toFixed(1) + ' ' + q[1].toFixed(1)).join(' ');
    const last = points[points.length - 1];
    return `<svg class="h-[30px] w-full" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none"><path d="${d} L${w} ${h} L0 ${h}Z" fill="${color}" opacity=".12"/><path d="${d}" fill="none" stroke="${color}" stroke-width="1.8"/><circle cx="${last[0]}" cy="${last[1]}" r="2.8" fill="${color}"/></svg>`;
};

document.querySelectorAll('[data-spark]').forEach((el) => el.insertAdjacentHTML('beforeend', spark(el.dataset.spark.split(',').map(Number), el.dataset.color)));

/* ---------------------------------------------------------------- Graphiques du tableau de bord */

/* Barres : incidents signalés et résolus par mois (data-series envoyé par DashboardController) */
(function () {
    const el = $('barChart');
    if (!el || !el.dataset.series) return;
    const d = JSON.parse(el.dataset.series);
    const months = d.labels;
    const reported = d.reported;
    const resolved = d.resolved;
    const W = 560, H = 240, L = 36, B = 26, T = 10;
    const max = Math.max(4, Math.ceil(Math.max(...reported, ...resolved) / 4) * 4);
    const sx = (W - L - 10) / months.length;
    let s = '';

    for (let v = 0; v <= max; v += max / 4) {
        const y = T + (H - T - B) * (1 - v / max);
        s += `<line class="grid-l" x1="${L}" x2="${W - 6}" y1="${y}" y2="${y}"/><text x="${L - 8}" y="${y + 4}" text-anchor="end">${v}</text>`;
    }

    months.forEach((month, i) => {
        const x = L + i * sx + sx * 0.18;
        const bw = sx * 0.3;
        [[reported[i], 'var(--color-water)', 0], [resolved[i], 'var(--color-teal)', bw + 3]].forEach(([v, c, o]) => {
            const h = (H - T - B) * v / max;
            s += `<rect x="${x + o}" y="${H - B - h}" width="${bw}" height="${h}" rx="3" fill="${c}"><title>${month} : ${v}</title></rect>`;
        });
        s += `<text x="${x + bw + 1.5}" y="${H - 8}" text-anchor="middle">${month}</text>`;
    });

    fill('barChart', s);
})();

/* Donut : incidents ouverts par type (data-series : [libellé, nombre, couleur]) */
(function () {
    const el = $('donut');
    if (!el || !el.dataset.series) return;
    const d = JSON.parse(el.dataset.series);
    const total = d.reduce((t, x) => t + x[1], 0);
    let offset = 25;
    let s = '<circle cx="21" cy="21" r="15.9" fill="none" stroke="var(--color-surface-2)" stroke-width="6"/>';

    d.forEach((x) => {
        if (!x[1]) return;
        const p = x[1] / total * 100;
        s += `<circle cx="21" cy="21" r="15.9" fill="none" stroke="${x[2]}" stroke-width="6" stroke-dasharray="${p - 0.8} ${100 - p + 0.8}" stroke-dashoffset="${offset}"/>`;
        offset -= p;
    });

    s += `<text x="21" y="21" text-anchor="middle" font-family="Manrope,sans-serif" font-weight="800" font-size="8" fill="var(--color-ink)">${total}</text><text x="21" y="27" text-anchor="middle" font-size="3.2" fill="var(--color-muted)" font-family="IBM Plex Mono,monospace">OUVERTS</text>`;

    fill('donut', s);
    fill('donutLegend', d.map((x) => `<li><span><i class="mr-2 inline-block size-2.5 rounded-[3px]" style="background:${x[2]}"></i>${x[0]}</span><span class="font-mono">${x[1]} · ${total ? Math.round(x[1] / total * 100) : 0} %</span></li>`).join(''));
})();

/* ---------------------------------------------------------------- Rôles et permissions (lecture seule) */

const modules = [['Infrastructures', [1, 1, 1, 0]], ['Incidents', [1, 1, 1, 1]], ['Interventions', [1, 1, 1, 0]], ['Projets', [1, 1, 1, 0]], ['Financements', [1, 1, 0, 0]], ['Analyses IA', [1, 0, 0, 0]], ['Utilisateurs', [1, 0, 0, 0]]];
const abilities = ['view', 'create', 'update', 'delete'];
const abilityLabels = ['voir', 'créer', 'modifier', 'supprimer'];

fill('matrix', modules.map((m, i) => `<tr><td><strong>${m[0]}</strong></td>${m[1].map((v, j) => `<td><input type="checkbox" id="perm${i}${j}" name="permissions[${m[0]}][]" value="${abilities[j]}" ${v ? 'checked' : ''} aria-label="${m[0]} ${abilityLabels[j]}"></td>`).join('')}</tr>`).join(''));

const rolesGrid = $('rolesGrid');
if (rolesGrid && innerWidth < 760) rolesGrid.style.gridTemplateColumns = '1fr';

/* ---------------------------------------------------------------- Analyses IA (données de démonstration) */

const risk = [['Réservoir de La Soukra', 'Âge 39 ans · fissures radier', 74], ['Station Ariana Nord', '2 pannes pompe en 60 j', 68], ['Conduite fonte Bab Saadoun', 'Fonte grise 1962 · 9 fuites/an', 61], ['Conduite DN 600 Ben Arous', 'Pertes 6 m³/h', 42], ['Réservoir Mégrine', 'Inspection récente', 12]];

fill('riskTable', risk.map((r) => {
    const color = r[2] >= 60 ? 'var(--color-crit)' : r[2] >= 35 ? 'var(--color-warn)' : 'var(--color-teal)';
    return `<tr><td><strong>${r[0]}</strong></td><td class="text-[.84rem] text-muted">${r[1]}</td><td><div class="flex items-center gap-2.5"><div class="h-2 min-w-20 flex-1 overflow-hidden rounded-full bg-surface-2"><span class="block h-full rounded-full" style="width:${r[2]}%;background:${color}"></span></div><span class="w-[38px] text-right font-mono">${r[2]} %</span></div></td></tr>`;
}).join(''));

/* Détection d'anomalie : pression sur 24 h */
(function () {
    if (!$('anomChart')) return;
    const v = [3.2, 3.3, 3.1, 3.0, 2.9, 2.8, 1.4, 1.2, 1.3, 2.6, 3.0, 3.1, 3.2, 3.1, 3.0, 3.1, 3.2, 3.3, 3.2, 3.1, 3.0, 3.1, 3.2, 3.2];
    const W = 520, H = 220, L = 34, B = 24, T = 10, mx = 4;
    const x = (i) => L + i * (W - L - 10) / 23;
    const y = (b) => T + (H - T - B) * (1 - b / mx);
    let s = '';

    for (let b = 0; b <= 4; b++) s += `<line class="grid-l" x1="${L}" x2="${W - 6}" y1="${y(b)}" y2="${y(b)}"/><text x="${L - 8}" y="${y(b) + 4}" text-anchor="end">${b}</text>`;
    s += `<path d="M${x(0)} ${y(3.6)} L${x(23)} ${y(3.6)} L${x(23)} ${y(2.5)} L${x(0)} ${y(2.5)}Z" fill="var(--color-water-soft)"/>`;
    s += `<path d="${v.map((b, i) => (i ? 'L' : 'M') + x(i) + ' ' + y(b)).join(' ')}" fill="none" stroke="var(--color-water)" stroke-width="2.2"/>`;
    [6, 7, 8].forEach((i) => (s += `<circle cx="${x(i)}" cy="${y(v[i])}" r="4.5" fill="var(--color-crit)" stroke="var(--color-surface)" stroke-width="2"/>`));
    s += `<text x="${x(8) + 10}" y="${y(1.2) + 4}" style="fill:var(--color-crit-fg)">03:00 · 1,2 bar</text>`;
    [0, 6, 12, 18, 23].forEach((i) => (s += `<text x="${x(i)}" y="${H - 6}" text-anchor="middle">${String(i).padStart(2, '0')}h</text>`));

    fill('anomChart', s);
})();

/* ---------------------------------------------------------------- Carte de localisation (<x-location-map pickable>) */

/* Un clic remplit latitude / longitude, la saisie déplace le repère */
document.querySelectorAll('[data-pick-map]').forEach((box) => {
    const [la0, la1, ln0, ln1] = box.dataset.bounds.split(',').map(Number);
    const pin = box.querySelector('[data-pin]');
    const lat = $(box.dataset.lat);
    const lng = $(box.dataset.lng);
    if (!lat || !lng) return;

    const num = (input) => parseFloat(input.value.replace(',', '.'));
    const clamp = (v) => Math.min(100, Math.max(0, v));
    const place = () => {
        if (!pin) return;
        const a = num(lat);
        const o = num(lng);
        const ok = !isNaN(a) && !isNaN(o);
        pin.hidden = !ok;
        if (ok) {
            pin.style.left = clamp((o - ln0) / (ln1 - ln0) * 100) + '%';
            pin.style.top = clamp((la1 - a) / (la1 - la0) * 100) + '%';
        }
    };

    box.addEventListener('click', (e) => {
        const r = box.getBoundingClientRect();
        lat.value = (la1 - (e.clientY - r.top) / r.height * (la1 - la0)).toFixed(6);
        lng.value = (ln0 + (e.clientX - r.left) / r.width * (ln1 - ln0)).toFixed(6);
        place();
    });
    [lat, lng].forEach((input) => input.addEventListener('input', place));
});
