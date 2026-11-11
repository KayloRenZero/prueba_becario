/**
 * Menú de navegación responsive (botón hamburguesa en móvil).
 * El estado se refleja en aria-expanded para que los lectores de pantalla
 * anuncien si el menú está abierto o cerrado.
 */

/**
 * @param {{ toggle: HTMLButtonElement, menu: HTMLElement, desktopQuery?: string }} options
 */
export function initMenu({ toggle, menu, desktopQuery = '(min-width: 768px)' }) {
  if (!toggle || !menu) return;

  const desktop = window.matchMedia(desktopQuery);
  const isOpen = () => toggle.getAttribute('aria-expanded') === 'true';

  function setOpen(open) {
    toggle.setAttribute('aria-expanded', String(open));
    menu.classList.toggle('is-open', open);
  }

  toggle.addEventListener('click', () => setOpen(!isOpen()));

  // Cierra al elegir un enlace (navegación dentro de la misma página).
  menu.addEventListener('click', (event) => {
    if (event.target.closest('a')) setOpen(false);
  });

  // Cierra con Escape y devuelve el foco al botón (patrón "disclosure").
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && isOpen()) {
      setOpen(false);
      toggle.focus();
    }
  });

  // Cierra al hacer clic fuera del menú.
  document.addEventListener('click', (event) => {
    if (isOpen() && !menu.contains(event.target) && !toggle.contains(event.target)) {
      setOpen(false);
    }
  });

  // Si se agranda la ventana a escritorio, se resetea el estado del menú móvil.
  desktop.addEventListener('change', (event) => {
    if (event.matches) setOpen(false);
  });
}
