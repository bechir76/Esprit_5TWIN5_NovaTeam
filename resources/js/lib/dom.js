/*
 * Petits utilitaires DOM partagés par front.js et back.js.
 * Chaque vue ne contient qu'un écran : un élément absent de la page courante est simplement ignoré.
 */
export const $ = (id) => document.getElementById(id);

export const fill = (id, html) => {
    const el = $(id);
    if (el) el.innerHTML = html;
};

export const on = (id, event, handler) => {
    const el = $(id);
    if (el) el.addEventListener(event, handler);
};

/* URL d'un écran : le layout définit window.AQUA_ROUTES = {ecran: "{{ route('...') }}"}, sinon ancre */
export const url = (screen) => (window.AQUA_ROUTES || {})[screen] || '#' + screen;

/* Échappe une valeur avant de l'insérer avec innerHTML */
export const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));
