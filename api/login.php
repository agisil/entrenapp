<?php
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$body = getJsonBody();
$name = trim($body['name'] ?? '');
$pin = trim($body['pin'] ?? '');

if ($name === '' || $pin === '') {
    jsonResponse(['error' => 'Faltan datos'], 400);
}

$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE LOWER(name) = LOWER(?)');
$stmt->execute([$name]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si hay más de un usuario con el mismo nombre, no podemos saber a cuál te referís por nombre solo
if (count($matches) > 1) {
    jsonResponse(['error' => 'Hay varias cuentas con ese nombre, usá tu código de invitación'], 409);
}

$user = $matches[0] ?? null;

// Mismo mensaje de error si el nombre no existe o el PIN está mal,
// para no revelar cuáles nombres existen (evita enumeración)
if (!$user || !password_verify($pin, $user['pin_hash'])) {
    jsonResponse(['error' => 'Nombre o PIN incorrecto'], 401);
}

jsonResponse([
    'id' => $user['id'],
    'name' => $user['name'],
    'token' => $user['token'],
    'invite_code' => $user['invite_code']
]);