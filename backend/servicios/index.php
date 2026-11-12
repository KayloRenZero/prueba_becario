<?php
// backend/servicios/index.php
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../lib/servicios.php';

requireAdmin();

$services = listServices(getDB());
$activeCount = count(array_filter($services, static fn(array $s): bool => (int) $s['activo'] === 1));

$pageTitle = 'Servicios';
require __DIR__ . '/../views/layout/header.php';
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Servicios</h1>
    <p class="page-header__meta">
      <?= count($services) ?> registrados · <?= $activeCount ?> visibles en el sitio público
    </p>
  </div>
  <a class="btn btn--primary" href="create.php">Nuevo servicio</a>
</div>

<?php if ($services === []): ?>
  <div class="notice">
    <p class="notice__title">Aún no hay servicios.</p>
    <p class="notice__text">Crea el primero para que aparezca en el sitio público.</p>
    <a class="btn btn--primary btn--sm" href="create.php">Crear servicio</a>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <caption class="visually-hidden">Listado de servicios</caption>
      <thead>
        <tr>
          <th scope="col">Servicio</th>
          <th scope="col">Precio</th>
          <th scope="col">Estado</th>
          <th scope="col">Actualizado</th>
          <th scope="col"><span class="visually-hidden">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($services as $service): ?>
          <?php $isActive = (int) $service['activo'] === 1; ?>
          <tr>
            <td>
              <strong><?= e($service['nombre']) ?></strong>
              <?php if (!empty($service['descripcion'])): ?>
                <span class="table__sub"><?= e($service['descripcion']) ?></span>
              <?php endif; ?>
            </td>
            <td class="table__num"><?= e(formatMoney($service['precio'])) ?></td>
            <td>
              <span class="badge badge--<?= $isActive ? 'success' : 'muted' ?>">
                <?= $isActive ? 'Activo' : 'Inactivo' ?>
              </span>
            </td>
            <td class="table__date">
              <time datetime="<?= e(date('c', strtotime($service['actualizado_en']))) ?>">
                <?= e(date('d/m/Y H:i', strtotime($service['actualizado_en']))) ?>
              </time>
            </td>
            <td>
              <div class="actions">
                <a class="btn btn--sm btn--secondary" href="update.php?id=<?= (int) $service['id'] ?>">
                  Editar<span class="visually-hidden"> <?= e($service['nombre']) ?></span>
                </a>
                <form method="post" action="delete.php"
                      data-confirm="¿Eliminar «<?= e($service['nombre']) ?>»? Esta acción no se puede deshacer.">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= (int) $service['id'] ?>">
                  <button class="btn btn--sm btn--danger" type="submit">
                    Eliminar<span class="visually-hidden"> <?= e($service['nombre']) ?></span>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../views/layout/footer.php'; ?>
