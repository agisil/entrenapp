<?php
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$body = getJsonBody();
$name = trim($body['name'] ?? '');
$pin = trim($body['pin'] ?? '');

if ($name === '' || strlen($name) > 50) {
    jsonResponse(['error' => 'Nombre inválido'], 400);
}

if (!preg_match('/^\d{4}$/', $pin)) {
    jsonResponse(['error' => 'El PIN debe ser de 4 dígitos'], 400);
}

$db = getDB();

$stmt = $db->prepare('SELECT id FROM users WHERE LOWER(name) = LOWER(?)');
$stmt->execute([$name]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'Ese nombre ya está en uso, elegí otro'], 409);
}

$token = generateToken();
$pinHash = password_hash($pin, PASSWORD_DEFAULT);
$color = pickUserColor($db);

// Genera un invite_code único (reintenta si por casualidad choca)
do {
    $inviteCode = generateInviteCode();
    $stmt = $db->prepare('SELECT id FROM users WHERE invite_code = ?');
    $stmt->execute([$inviteCode]);
} while ($stmt->fetch());

$stmt = $db->prepare('INSERT INTO users (name, token, invite_code, pin_hash, color) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$name, $token, $inviteCode, $pinHash, $color]);

jsonResponse([
    'id' => $db->lastInsertId(),
    'name' => $name,
    'token' => $token,
    'invite_code' => $inviteCode,
    'color' => $color
]);