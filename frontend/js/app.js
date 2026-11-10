const API = '../backend/api/servicios.php';

async function loadServicios() {
  const container = document.getElementById('servicios-list');
  try {
    const res = await fetch(API);
    const data = await res.json();
    container.innerHTML = data.map(s => `
      <article class="card fade-in">
        <h3>${escapeHtml(s.nombre)}</h3>
        <p>${escapeHtml(s.descripcion || '')}</p>
        <p class="price">$${Number(s.precio).toFixed(2)}</p>
      </article>
    `).join('');
  } catch (e) {
    container.innerHTML = '<p>No se pudieron cargar los servicios.</p>';
  }
}

function escapeHtml(str) {
  return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function validate(form) {
  const errors = {};
  const nombre = form.nombre.value.trim();
  const email = form.email.value.trim();
  const telefono = form.telefono.value.trim();
  const mensaje = form.mensaje.value.trim();

  if (nombre.length < 3) errors.nombre = 'Mínimo 3 caracteres.';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.email = 'Email inválido.';
  if (!/^\d{10}$/.test(telefono)) errors.telefono = 'Debe tener 10 dígitos.';
  if (mensaje.length < 10) errors.mensaje = 'Mínimo 10 caracteres.';

  // limpiar
  form.querySelectorAll('.error').forEach(e => e.textContent = '');
  Object.keys(errors).forEach(k => {
    const el = form.querySelector(`.error[data-for="${k}"]`);
    if (el) el.textContent = errors[k];
  });
  return Object.keys(errors).length === 0;
}

document.addEventListener('DOMContentLoaded', () => {
  loadServicios();
  const form = document.getElementById('contactForm');
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const status = document.getElementById('formStatus');
    if (!validate(form)) { status.textContent = 'Corrige los errores.'; status.style.color = '#c0392b'; return; }
    status.textContent = 'Mensaje enviado (simulado).';
    status.style.color = '#1f4e79';
    form.reset();
  });
});
