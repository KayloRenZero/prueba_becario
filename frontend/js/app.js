/**
 * Punto de entrada del frontend.
 *
 * Solo compone los módulos; cada uno tiene una única responsabilidad:
 *  - menu.js         → navegación responsive
 *  - services.js     → consumo de la API y render de tarjetas
 *  - contact-form.js → interacción del formulario
 *  - validators.js   → reglas de validación puras (sin DOM)
 *
 * Al cargarse con type="module", el script se ejecuta cuando el HTML ya está
 * parseado (equivale a "defer"), así que no hace falta esperar a DOMContentLoaded.
 */
import { initMenu } from './modules/menu.js';
import { loadServices } from './modules/services.js';
import { initContactForm } from './modules/contact-form.js';

const contactForm = initContactForm(document.getElementById('contact-form'));

initMenu({
  toggle: document.getElementById('nav-toggle'),
  menu: document.getElementById('primary-nav'),
});

loadServices(document.getElementById('services'), {
  onInterest: (service) => contactForm.prefillInterest(service.nombre),
});

document.querySelectorAll('[data-current-year]').forEach((element) => {
  element.textContent = String(new Date().getFullYear());
});
