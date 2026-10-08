/*
 * AquaSecure Citoyens (layouts/front/app) : JS extrait de templates-source/aquasecure-front.html.
 * Menus, burger et ouverture du chatbot sont gérés par Alpine dans les vues ; ce fichier garde
 * la carte, le toast, les messages du chatbot, l'animation du hero et le formulaire de signalement.
 */
import './app';
import { $, esc, fill, on, url } from './lib/dom';

/* ---------------------------------------------------------------- Toast */

const toast = $('toast');
let toastTimer;

function showToast(message) {
    if (!toast) return;
    toast.textContent = '✓ ' + message;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toast.hidden = true), 2800);
}

/* Message flash rendu par Laravel (session status) : masqué comme les autres toasts */
if (toast && !toast.hidden) toastTimer = setTimeout(() => (toast.hidden = true), 2800);
document.querySelectorAll('[data-toast]').forEach((b) => b.addEventListener('click', () => showToast(b.dataset.toast)));

/* ---------------------------------------------------------------- Carte du réseau (/carte) */

/* Infrastructures publiques envoyées par InfrastructureMapController (window.AQUA_SITES),
   placées par projection linéaire de leurs coordonnées dans l'emprise window.AQUA_MAP_BOUNDS */
const sites = window.AQUA_SITES || [];
const bounds = window.AQUA_MAP_BOUNDS || { lat: [36.70, 36.95], lng: [10.05, 10.35] };

const project = (lat, lng) => {
    const c = (v, a, b) => Math.min(1, Math.max(0, (v - a) / (b - a)));
    return [40 + c(lng, bounds.lng[0], bounds.lng[1]) * 820, 40 + (1 - c(lat, bounds.lat[0], bounds.lat[1])) * 480];
};

const mapLabel = (text, lat, lng) => {
    const [x, y] = project(lat, lng);
    return `<text x="${x.toFixed(0)}" y="${y.toFixed(0)}" fill="var(--color-muted)" font-family="IBM Plex Mono,monospace" font-size="12" text-anchor="middle">${text}</text>`;
};

if ($('mapSvg')) {
    let svg = `<rect width="900" height="560" fill="var(--color-surface-2)"/>
<path d="M620 0 C700 60 820 70 900 50 L900 0Z" fill="var(--color-water-soft)"/>
<path d="M0 260 C200 240 380 300 900 280" stroke="var(--color-line)" stroke-width="16" fill="none"/>
<path d="M260 0 C280 200 240 400 300 560" stroke="var(--color-line)" stroke-width="12" fill="none"/>
<path d="M600 0 C580 200 640 380 620 560" stroke="var(--color-line)" stroke-width="12" fill="none"/>
<path d="M0 470 L900 420" stroke="var(--color-line)" stroke-width="10" fill="none"/>`
        + mapLabel('TUNIS', 36.790, 10.160) + mapLabel('ARIANA', 36.905, 10.150) + mapLabel('BEN AROUS', 36.735, 10.200)
        + mapLabel('LA MANOUBA', 36.835, 10.080) + mapLabel('MORNAG', 36.690, 10.300);

    sites.forEach((s, k) => {
        const [x, y] = project(s.lat, s.lng);
        svg += `<g class="group cursor-pointer" data-marker data-k="${k}" tabindex="0" role="button" aria-label="${esc(s.name)}"><circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" class="transition-all group-hover:[r:16px]" r="13" fill="${esc(s.color)}" opacity=".22"/><circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="7" fill="${esc(s.color)}" stroke="#fff" stroke-width="2.5"/></g>`;
    });

    fill('mapSvg', svg);
}

const pop = $('mapPop');
const row = 'line-item';

function showSite(k) {
    const s = sites[k];
    if (!pop || !s) return;
    pop.innerHTML = `<div class="flex flex-wrap items-center justify-between gap-3"><span class="badge ${esc(s.badge)}">${esc(s.statusLabel)}</span><span class="font-mono text-xs text-muted">${esc(s.zone)}</span></div>
<h3>${esc(s.name)}</h3><div class="text-[.88rem] text-muted">${esc(s.type)} · ${esc(s.capacity)}</div>
<div class="${row}"><span>Dernière inspection</span><span class="font-mono">${esc(s.inspection)}</span></div>
<div class="${row} border-b-0"><span>Code ouvrage</span><span class="font-mono">${esc(s.code)}</span></div>
<a href="${url('signaler')}" class="btn btn-ghost btn-sm justify-center">Signaler un problème ici</a>`;
}

/* Fiche affichée par défaut : le premier ouvrage critique, sinon le premier */
if (pop) {
    if (sites.length) {
        const critical = sites.findIndex((s) => s.status === 'critique');
        showSite(critical < 0 ? 0 : critical);
    } else {
        pop.innerHTML = '<h3>Aucun ouvrage</h3><div class="text-[.88rem] text-muted">Aucune infrastructure publique ne correspond à ces filtres.</div>';
    }
}

on('mapSvg', 'click', (e) => {
    const g = e.target.closest('[data-marker]');
    if (g) showSite(+g.dataset.k);
});
on('mapSvg', 'keydown', (e) => {
    const g = e.target.closest('[data-marker]');
    if (g && e.key === 'Enter') showSite(+g.dataset.k);
});

/* ---------------------------------------------------------------- Chatbot (<x-chatbot />) */

const chatBubble = {
    bot: 'max-w-[85%] self-start rounded-xl rounded-bl-sm bg-surface-2 px-[13px] py-2.5',
    me: 'max-w-[85%] self-end rounded-xl rounded-br-sm bg-water px-[13px] py-2.5 text-[#032A3F]',
};

on('chatForm', 'submit', (e) => {
    e.preventDefault();
    const input = $('chatIn');
    const body = $('chatBody');
    if (!input || !body) return;
    const text = input.value.trim();
    if (!text) return;

    const mine = document.createElement('div');
    mine.className = chatBubble.me;
    mine.textContent = text;
    body.appendChild(mine);

    setTimeout(() => {
        body.insertAdjacentHTML('beforeend', `<div class="${chatBubble.bot}">Je vérifie les données de votre commune… (réponse simulée dans ce template)</div>`);
        body.scrollTop = body.scrollHeight;
    }, 500);

    input.value = '';
    body.scrollTop = body.scrollHeight;
});

document.querySelectorAll('[data-chip]').forEach((chip) => chip.addEventListener('click', () => {
    const input = $('chatIn');
    const form = $('chatForm');
    if (!input || !form) return;
    input.value = chip.textContent;
    form.requestSubmit();
}));

/* ---------------------------------------------------------------- Ondulations du hero (accueil) */

(function () {
    const canvas = $('ripple');
    if (!canvas || !canvas.getContext || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const ctx = canvas.getContext('2d');
    let w;
    let h;
    let t = 0;

    function resize() {
        w = canvas.width = canvas.offsetWidth * devicePixelRatio;
        h = canvas.height = canvas.offsetHeight * devicePixelRatio;
    }
    resize();
    addEventListener('resize', resize);

    (function frame() {
        ctx.clearRect(0, 0, w, h);
        ctx.strokeStyle = 'rgba(127,216,255,.5)';
        ctx.lineWidth = 1.2 * devicePixelRatio;
        for (let i = 0; i < 9; i++) {
            ctx.beginPath();
            for (let px = 0; px <= w; px += 12) {
                const y = h * (0.15 + i * 0.1) + Math.sin(px / 140 / devicePixelRatio + t + i * 0.7) * 10 * devicePixelRatio;
                px ? ctx.lineTo(px, y) : ctx.moveTo(px, y);
            }
            ctx.stroke();
        }
        t += 0.012;
        requestAnimationFrame(frame);
    })();
})();

/* ---------------------------------------------------------------- Formulaire de signalement */

/* « Utiliser ma position » remplit latitude / longitude */
on('geoBtn', 'click', () => {
    const lat = $('latitude');
    const lng = $('longitude');
    if (!lat || !lng || !navigator.geolocation) {
        showToast('Géolocalisation indisponible sur cet appareil.');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        (p) => {
            lat.value = p.coords.latitude.toFixed(6);
            lng.value = p.coords.longitude.toFixed(6);
            showToast('Position ajoutée au signalement.');
        },
        () => showToast("Position non disponible : saisissez l'adresse."),
    );
});

/* Nom de la photo choisie */
on('photo', 'change', (e) => {
    const name = $('photoName');
    const file = e.target.files[0];
    if (name) name.textContent = file ? file.name : '';
});
