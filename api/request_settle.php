<?php
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$body = getJsonBody();
$user = getUserByToken($body['token'] ?? null);
$friendId = (int)($body['friend_id'] ?? 0);

if (!$friendId) {
    jsonResponse(['error' => 'Falta friend_id'], 400);
}

$db = getDB();

// No permitir pedidos duplicados: si ya hay uno pendiente entre estos dos, no crear otro
$stmt = $db->prepare("
    SELECT id FROM settlements
    WHERE status = 'pending'
    AND ((requester_id = ? AND friend_id = ?) OR (requester_id = ? AND friend_id = ?))
");
$stmt->execute([$user['id'], $friendId, $friendId, $user['id']]);
if ($stmt->fetch()) {
    jsonResponse(['error' => 'Ya hay un pedido de saldo pendiente con esta persona'], 409);
}

$stmt = $db->prepare('INSERT INTO settlements (requester_id, friend_id) VALUES (?, ?)');
$stmt->execute([$user['id'], $friendId]);

jsonResponse(['ok' => true, 'settlement_id' => $db->lastInsertId()]);
