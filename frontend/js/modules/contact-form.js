/**
 * Controlador del formulario de contacto: conecta las reglas puras de
 * validators.js con el DOM (errores inline, estados de envío y accesibilidad).
 */
import { contactRules, validateField, validateForm } from './validators.js';

const SIMULATED_LATENCY_MS = 600;

/**
 * Envío simulado: el backend aún no expone un endpoint de contacto.
 * Se inyecta como dependencia (`send`), así que para conectarlo a una API real
 * basta con pasar una función que haga el fetch POST, sin tocar este módulo.
 */
function simulateSend() {
  return new Promise((resolve) => {
    setTimeout(resolve, SIMULATED_LATENCY_MS);
  });
}

/**
 * @param {HTMLFormElement} form
 * @param {{ rules?: object, send?: (values: object) => Promise<unknown> }} [options]
 * @returns {{ prefillInterest: (serviceName: string) => void }}
 */
export function initContactForm(form, { rules = contactRules, send = simulateSend } = {}) {
  const fieldNames = Object.keys(rules);
  const status = form.querySelector('[data-form-status]');
  const submitButton = form.querySelector('[type="submit"]');
  const submitLabel = submitButton.textContent;

  // Campos que ya mostraron feedback: desde ese momento se revalidan mientras se escribe.
  const liveFields = new Set();

  const isValidatedField = (element) => fieldNames.includes(element.name);

  function showFieldError(name, message) {
    const input = form.elements[name];
    const error = document.getElementById(`${name}-error`);

    if (message) {
      input.setAttribute('aria-invalid', 'true');
    } else {
      input.removeAttribute('aria-invalid');
    }
    if (error) error.textContent = message ?? '';
  }

  function checkField(name) {
    const message = validateField(form.elements[name].value, rules[name]);
    showFieldError(name, message);
    return message === null;
  }

  function setStatus(state, message) {
    status.dataset.state = state;
    status.textContent = message;
  }

  function setSubmitting(isSubmitting) {
    submitButton.disabled = isSubmitting;
    submitButton.classList.toggle('is-loading', isSubmitting);
    submitButton.textContent = isSubmitting ? 'Enviando…' : submitLabel;
    form.setAttribute('aria-busy', String(isSubmitting));
  }

  function getValues() {
    const entries = [...new FormData(form)].map(([key, value]) => [key, String(value).trim()]);
    return Object.fromEntries(entries);
  }

  function resetForm() {
    form.reset();
    liveFields.clear();
    fieldNames.forEach((name) => showFieldError(name, null));
  }

  // Mientras se escribe: filtra caracteres y revalida solo los campos ya "activos".
  form.addEventListener('input', ({ target }) => {
    if (!isValidatedField(target)) return;

    if (target.hasAttribute('data-digits-only')) {
      const digits = target.value.replace(/\D/g, '');
      if (digits !== target.value) target.value = digits;
    }
    if (liveFields.has(target.name)) checkField(target.name);
  });

  // Al salir del campo: "premiar pronto, castigar tarde". No se marca error
  // en un campo vacío que el usuario solo atravesó con Tab.
  form.addEventListener('focusout', ({ target }) => {
    if (!isValidatedField(target)) return;
    if (target.value.trim() === '' && !liveFields.has(target.name)) return;

    liveFields.add(target.name);
    checkField(target.name);
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submitButton.disabled) return; // evita envíos dobles

    const values = getValues();
    const errors = validateForm(values, rules);

    fieldNames.forEach((name) => {
      liveFields.add(name);
      showFieldError(name, errors[name] ?? null);
    });

    const invalidFields = Object.keys(errors);
    if (invalidFields.length > 0) {
      setStatus(
        'error',
        invalidFields.length === 1
          ? 'Revisa el campo con error antes de enviar.'
          : `Revisa los ${invalidFields.length} campos con error antes de enviar.`,
      );
      form.elements[invalidFields[0]].focus();
      return; // no se envía nada si hay errores
    }

    setStatus('', '');
    setSubmitting(true);
    try {
      await send(values);
      resetForm();
      setStatus('success', `¡Gracias, ${values.nombre}! Recibimos tu mensaje y te contactaremos pronto.`);
    } catch (error) {
      console.error('[contacto] Error al enviar:', error);
      setStatus('error', 'No pudimos enviar tu mensaje. Inténtalo de nuevo en unos minutos.');
    } finally {
      setSubmitting(false);
    }
  });

  /** Precarga el mensaje cuando el usuario pulsa "Me interesa" en una tarjeta. */
  function prefillInterest(serviceName) {
    const field = form.elements.mensaje;
    if (field.value.trim() === '') {
      field.value = `Hola, me interesa el servicio "${serviceName}" y me gustaría recibir más información.`;
    }
    if (liveFields.has('mensaje')) checkField('mensaje');
  }

  return { prefillInterest };
}
