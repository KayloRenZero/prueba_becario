/**
 * Catálogo de servicios: obtiene los datos de la API y los pinta como tarjetas.
 *
 * Estados de la UI: cargando (skeleton) → lista | vacío | error (con reintento).
 */

const API_URL = '../backend/api/servicios.php';
const REQUEST_TIMEOUT_MS = 8000;
const SKELETON_COUNT = 3;
const MAX_STAGGER_STEPS = 8; // limita el retraso acumulado de la animación escalonada

const priceFormatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

/**
 * Formatea un precio en pesos mexicanos. Acepta número o string ("15000.00"),
 * porque PDO devuelve las columnas DECIMAL como string.
 * @param {unknown} value
 * @returns {string}
 */
export function formatPrice(value) {
  const amount = Number.parseFloat(value);
  return Number.isFinite(amount) ? `${priceFormatter.format(amount)} MXN` : 'Precio a consultar';
}

/**
 * Pide la lista de servicios activos a la API.
 * @returns {Promise<Array<{id: number, nombre: string, descripcion: string|null, precio: string}>>}
 * @throws {Error} Si la red falla, se agota el tiempo, el estado HTTP no es 2xx
 *                 o la respuesta no tiene la forma esperada.
 */
export async function fetchServices() {
  const response = await fetch(API_URL, {
    headers: { Accept: 'application/json' },
    signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
  });

  // fetch() solo rechaza por errores de red: un 404 o un 500 hay que detectarlos a mano.
  if (!response.ok) {
    throw new Error(`La API respondió con estado HTTP ${response.status}`);
  }

  const data = await response.json();
  if (!Array.isArray(data)) {
    throw new TypeError('La API no devolvió una lista de servicios');
  }
  return data;
}

/**
 * Carga los servicios y gestiona los estados de la UI en `container`.
 * @param {HTMLElement} container
 * @param {{ onInterest?: (service: object) => void }} [options]
 *        onInterest se ejecuta al pulsar "Me interesa" en una tarjeta.
 */
export async function loadServices(container, { onInterest } = {}) {
  container.setAttribute('aria-busy', 'true');
  renderLoading(container);

  try {
    const services = await fetchServices();
    if (services.length === 0) {
      renderEmpty(container);
    } else {
      renderList(container, services, onInterest);
    }
  } catch (error) {
    console.error('[servicios] No se pudieron cargar:', error);
    renderError(container, () => loadServices(container, { onInterest }));
  } finally {
    container.setAttribute('aria-busy', 'false');
  }
}

/* ---------- Render ---------- */

function renderLoading(container) {
  const template = document.getElementById('service-skeleton-template');
  const list = createList();
  for (let i = 0; i < SKELETON_COUNT; i += 1) {
    list.append(template.content.firstElementChild.cloneNode(true));
  }
  container.replaceChildren(list, createLiveText('Cargando servicios…'));
}

function renderList(container, services, onInterest) {
  const template = document.getElementById('service-card-template');
  const list = createList();
  services.forEach((service, index) => {
    list.append(createServiceCard(template, service, index, onInterest));
  });
  container.replaceChildren(list);
}

function renderEmpty(container) {
  const notice = createNotice({
    title: 'Por ahora no hay servicios disponibles.',
    text: 'Escríbenos y te ayudaremos a encontrar la solución que necesitas.',
  });
  const link = document.createElement('a');
  link.className = 'btn btn--primary btn--sm';
  link.href = '#contacto';
  link.textContent = 'Ir al formulario de contacto';
  notice.append(link);
  container.replaceChildren(notice);
}

function renderError(container, onRetry) {
  const notice = createNotice({
    variant: 'error',
    title: 'No pudimos cargar los servicios.',
    text: 'Revisa tu conexión e inténtalo de nuevo en unos segundos.',
  });
  notice.setAttribute('role', 'alert');

  const retryButton = document.createElement('button');
  retryButton.type = 'button';
  retryButton.className = 'btn btn--primary btn--sm';
  retryButton.textContent = 'Reintentar';
  retryButton.addEventListener('click', onRetry, { once: true });

  notice.append(retryButton);
  container.replaceChildren(notice);
}

/**
 * Crea una tarjeta a partir de la plantilla HTML.
 * Se usa textContent (nunca innerHTML) para los datos de la API:
 * cualquier "<script>" que venga de la BD se muestra como texto, no se ejecuta.
 */
function createServiceCard(template, service, index, onInterest) {
  const card = template.content.firstElementChild.cloneNode(true);
  const nombre = String(service.nombre ?? '').trim();

  fillSlot(card, 'badge', nombre.charAt(0).toUpperCase());
  fillSlot(card, 'nombre', nombre);
  fillSlot(card, 'descripcion', service.descripcion || 'Pregúntanos por los detalles de este servicio.');
  fillSlot(card, 'precio', formatPrice(service.precio));
  card.style.setProperty('--i', Math.min(index, MAX_STAGGER_STEPS));

  const cta = card.querySelector('[data-action="interest"]');
  cta.setAttribute('aria-label', `Me interesa: ${nombre}`);
  if (onInterest) {
    cta.addEventListener('click', () => onInterest(service));
  }

  return card;
}

/* ---------- Helpers de DOM ---------- */

function fillSlot(root, slot, text) {
  root.querySelector(`[data-slot="${slot}"]`).textContent = text;
}

function createList() {
  const list = document.createElement('ul');
  list.className = 'cards';
  list.setAttribute('role', 'list');
  return list;
}

function createLiveText(text) {
  const status = document.createElement('p');
  status.className = 'visually-hidden';
  status.setAttribute('role', 'status');
  status.textContent = text;
  return status;
}

function createNotice({ title, text, variant }) {
  const notice = document.createElement('div');
  notice.className = variant ? `notice notice--${variant}` : 'notice';

  const titleEl = document.createElement('p');
  titleEl.className = 'notice__title';
  titleEl.textContent = title;

  const textEl = document.createElement('p');
  textEl.className = 'notice__text';
  textEl.textContent = text;

  notice.append(titleEl, textEl);
  return notice;
}
