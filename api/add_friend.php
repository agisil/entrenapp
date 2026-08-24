<?php
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$body = getJsonBody();
$me = getUserByToken($body['token'] ?? null);
$inviteCode = trim($body['invite_code'] ?? '');

if ($inviteCode === '') {
    jsonResponse(['error' => 'Falta el código de invitación'], 400);
}

$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE invite_code = ?');
$stmt->execute([strtoupper($inviteCode)]);
$friend = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$friend) {
    jsonResponse(['error' => 'Código no encontrado'], 404);
}

if ($friend['id'] == $me['id']) {
    jsonResponse(['error' => 'No podés agregarte a vos mismo'], 400);
}

// Guardamos siempre user_a_id < user_b_id para que la relación sea única sin importar quién invita
$aId = min($me['id'], $friend['id']);
$bId = max($me['id'], $friend['id']);

$stmt = $db->prepare('SELECT id FROM friendships WHERE user_a_id = ? AND user_b_id = ?');
$stmt->execute([$aId, $bId]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'Ya están conectados'], 409);
}

$stmt = $db->prepare('INSERT INTO friendships (user_a_id, user_b_id) VALUES (?, ?)');
$stmt->execute([$aId, $bId]);

jsonResponse(['ok' => true, 'friend' => ['id' => $friend['id'], 'name' => $friend['name']]]);
