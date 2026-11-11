/**
 * Reglas de validación del formulario de contacto.
 *
 * Módulo puro: no conoce el DOM. Recibe valores y devuelve mensajes de error,
 * lo que permite probarlo con tests unitarios y reutilizarlo en otros formularios.
 *
 * @typedef {{ test: (value: string) => boolean, message: string }} Rule
 */

// Validación de formato razonable. La validación definitiva de un email
// siempre debe repetirse en el servidor (p. ej. filter_var en PHP).
export const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
export const PHONE_PATTERN = /^\d{10}$/;

/* Fábricas de reglas: cada una devuelve un objeto { test, message }. */

/** @returns {Rule} */
export const required = (message) => ({
  test: (value) => value.length > 0,
  message,
});

/** @returns {Rule} */
export const minLength = (min, message) => ({
  test: (value) => value.length >= min,
  message,
});

/** @returns {Rule} */
export const matches = (pattern, message) => ({
  test: (value) => pattern.test(value),
  message,
});

/**
 * Reglas declarativas por campo. El orden importa: se reporta la primera que falle.
 * Para validar un campo nuevo basta con añadir una entrada aquí (no se toca el motor).
 */
export const contactRules = Object.freeze({
  nombre: [
    required('El nombre es obligatorio.'),
    minLength(3, 'El nombre debe tener al menos 3 caracteres.'),
  ],
  email: [
    required('El correo electrónico es obligatorio.'),
    matches(EMAIL_PATTERN, 'Ingresa un correo válido, por ejemplo: nombre@empresa.com.'),
  ],
  telefono: [
    required('El teléfono es obligatorio.'),
    matches(PHONE_PATTERN, 'El teléfono debe tener exactamente 10 dígitos.'),
  ],
  mensaje: [
    required('El mensaje es obligatorio.'),
    minLength(10, 'El mensaje debe tener al menos 10 caracteres.'),
  ],
});

/**
 * Valida un valor contra una lista de reglas.
 * @param {unknown} value
 * @param {Rule[]} rules
 * @returns {string|null} Mensaje de la primera regla que falla, o null si es válido.
 */
export function validateField(value, rules) {
  const normalized = String(value ?? '').trim();
  const failedRule = rules.find((rule) => !rule.test(normalized));
  return failedRule ? failedRule.message : null;
}

/**
 * Valida todos los campos definidos en `rules`.
 * @param {Record<string, unknown>} values
 * @param {Record<string, Rule[]>} rules
 * @returns {Record<string, string>} Objeto { campo: mensaje } solo con los campos inválidos.
 */
export function validateForm(values, rules) {
  const errors = {};
  for (const [field, fieldRules] of Object.entries(rules)) {
    const message = validateField(values[field], fieldRules);
    if (message) errors[field] = message;
  }
  return errors;
}
