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

/* Carte Leaflet rendue par le composant Livewire InfrastructureMap (resources/views/livewire/infrastructure-map.blade.php). */

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
