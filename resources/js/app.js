/*
 * Socle commun : axios, Livewire 3 et Alpine.js.
 * Livewire 3 embarque Alpine : on importe les deux depuis le bundle ESM de Livewire (une seule instance d'Alpine)
 * et les layouts utilisent @livewireScriptConfig au lieu de @livewireScripts.
 * Alpine gère les menus déroulants, le burger, la sidebar mobile et la fenêtre du chatbot (x-data dans les layouts).
 */
import './bootstrap';

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;

Livewire.start();
