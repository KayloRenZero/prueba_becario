<?php
// backend/servicios/delete.php
require_once __DIR__ . '/../helpers.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(403); echo 'Token inválido'; exit; }
$id = (int)($_POST['id'] ?? 0);
$stmt = getDB()->prepare('DELETE FROM servicios WHERE id = :id');
$stmt->execute([':id' => $id]);
header('Location: index.php');
exit;
