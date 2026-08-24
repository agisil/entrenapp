<?php
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$body = getJsonBody();
$user = getUserByToken($body['token'] ?? null);
$settlementId = (int)($body['settlement_id'] ?? 0);
$approve = (bool)($body['approve'] ?? false);

$db = getDB();
$stmt = $db->prepare("SELECT * FROM settlements WHERE id = ? AND status = 'pending'");
$stmt->execute([$settlementId]);
$settlement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$settlement) {
    jsonResponse(['error' => 'Pedido no encontrado o ya resuelto'], 404);
}

// Solo la otra parte (no quien pidió el saldo) puede confirmar/rechazar
if ($settlement['friend_id'] != $user['id']) {
    jsonResponse(['error' => 'No podés confirmar tu propio pedido'], 403);
}

if ($approve) {
    // Resetea el balance: borra todos los puntos entre ambos, en las dos direcciones
    $stmt = $db->prepare('DELETE FROM points WHERE (user_a_id = ? AND user_b_id = ?) OR (user_a_id = ? AND user_b_id = ?)');
    $stmt->execute([$settlement['requester_id'], $settlement['friend_id'], $settlement['friend_id'], $settlement['requester_id']]);

    $stmt = $db->prepare("UPDATE settlements SET status = 'confirmed', resolved_at = NOW() WHERE id = ?");
} else {
    $stmt = $db->prepare("UPDATE settlements SET status = 'rejected', resolved_at = NOW() WHERE id = ?");
}
$stmt->execute([$settlementId]);

jsonResponse(['ok' => true, 'approved' => $approve]);
