// Confirmación antes de acciones destructivas: <form data-confirm="¿Seguro?">.
document.addEventListener('submit', (event) => {
  const message = event.target.dataset.confirm;
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
});

// Tras un envío con errores del servidor, lleva el foco al primer campo inválido.
document.querySelector('[aria-invalid="true"]')?.focus();
