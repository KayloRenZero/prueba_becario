<?php
/**
 * Formulario compartido por create.php y update.php (una sola fuente de verdad).
 *
 * @var array{nombre: string, descripcion: ?string, precio: string, activo: int|bool} $values
 * @var array<string, string> $errors  Errores por campo; la clave 'form' es un error general.
 * @var string $submitLabel
 */
$fieldErrors = array_diff_key($errors, ['form' => true]);
$invalid = static fn(string $field): string => isset($errors[$field]) ? ' aria-invalid="true"' : '';
?>
<?php if (isset($errors['form'])): ?>
  <p class="alert alert--error" role="alert"><?= e($errors['form']) ?></p>
<?php elseif ($fieldErrors !== []): ?>
  <p class="alert alert--error" role="alert">
    Revisa <?= count($fieldErrors) === 1 ? 'el campo con error' : 'los ' . count($fieldErrors) . ' campos con error' ?> antes de guardar.
  </p>
<?php endif; ?>

<form class="admin-form" method="post" novalidate>
  <?= csrfField() ?>

  <div class="field">
    <label class="field__label" for="nombre">Nombre</label>
    <input class="field__input" id="nombre" name="nombre" type="text" maxlength="<?= SERVICE_NAME_MAX ?>"
           required value="<?= e($values['nombre']) ?>" aria-describedby="nombre-error"<?= $invalid('nombre') ?>>
    <p class="field__error" id="nombre-error"><?= e($errors['nombre'] ?? '') ?></p>
  </div>

  <div class="field">
    <label class="field__label" for="descripcion">
      Descripción <span class="field__optional">(opcional)</span>
    </label>
    <textarea class="field__input field__input--textarea" id="descripcion" name="descripcion" rows="4"
              maxlength="<?= SERVICE_DESCRIPTION_MAX ?>" aria-describedby="descripcion-hint descripcion-error"<?= $invalid('descripcion') ?>><?= e($values['descripcion']) ?></textarea>
    <p class="field__hint" id="descripcion-hint">Se muestra en la tarjeta del sitio público. Máximo <?= SERVICE_DESCRIPTION_MAX ?> caracteres.</p>
    <p class="field__error" id="descripcion-error"><?= e($errors['descripcion'] ?? '') ?></p>
  </div>

  <div class="field">
    <label class="field__label" for="precio">Precio (MXN)</label>
    <input class="field__input field__input--short" id="precio" name="precio" type="text" inputmode="decimal"
           required value="<?= e($values['precio']) ?>" aria-describedby="precio-hint precio-error"<?= $invalid('precio') ?>>
    <p class="field__hint" id="precio-hint">Sin signo de pesos ni comas. Ej.: 1500.50</p>
    <p class="field__error" id="precio-error"><?= e($errors['precio'] ?? '') ?></p>
  </div>

  <label class="checkbox">
    <input type="checkbox" name="activo" value="1"<?= $values['activo'] ? ' checked' : '' ?>>
    Visible en el sitio público
  </label>

  <div class="form-actions">
    <button class="btn btn--primary" type="submit"><?= e($submitLabel) ?></button>
    <a class="btn btn--secondary" href="index.php">Cancelar</a>
  </div>
</form>
