<?php
require_once __DIR__ . '/helpers.php';

$token = $_GET['token'] ?? null;
$user = getUserByToken($token);
$db = getDB();

// Trae todos los amigos (donde el usuario sea user_a o user_b)
$stmt = $db->prepare('
    SELECT u.id, u.name
    FROM friendships f
    JOIN users u ON u.id = IF(f.user_a_id = ?, f.user_b_id, f.user_a_id)
    WHERE f.user_a_id = ? OR f.user_b_id = ?
');
$stmt->execute([$user['id'], $user['id'], $user['id']]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

$result = [];
$stmtTodayWorkout = $db->prepare('SELECT id FROM workouts WHERE user_id = ? AND workout_date = CURDATE()');

$stmtTodayWorkout->execute([$user['id']]);
$userTrainedToday = (bool)$stmtTodayWorkout->fetch();

foreach ($friends as $friend) {
    // Puntos que YO le gané a este amigo (él no entrenó, yo sí) -- ya CONFIRMADOS (días cerrados)
    $stmt = $db->prepare('SELECT COUNT(*) FROM points WHERE user_a_id = ? AND user_b_id = ?');
    $stmt->execute([$user['id'], $friend['id']]);
    $myPoints = (int)$stmt->fetchColumn();

    // Puntos que este amigo me ganó a mí -- ya CONFIRMADOS
    $stmt = $db->prepare('SELECT COUNT(*) FROM points WHERE user_a_id = ? AND user_b_id = ?');
    $stmt->execute([$friend['id'], $user['id']]);
    $theirPoints = (int)$stmt->fetchColumn();

    $balance = $myPoints - $theirPoints; // balance REAL, confirmado (positivo = el amigo me debe cerveza)

    // --- Estado tentativo del día de hoy (todavía no se cierra hasta las 00:05) ---
    $stmtTodayWorkout->execute([$friend['id']]);
    $friendTrainedToday = (bool)$stmtTodayWorkout->fetch();

    $tentativeDelta = 0;
    $alert = null;
    if ($userTrainedToday && !$friendTrainedToday) {
        $tentativeDelta = 1; // si nadie más entrena, mañana este punto se confirma a mi favor
    } elseif (!$userTrainedToday && $friendTrainedToday) {
        $tentativeDelta = -1;
        $alert = "{$friend['name']} ya entrenó hoy. Tenés hasta las 23:59 para cargar tu entrenamiento y empatar, ¡mové el culo!";
    }

    $tentativeBalance = $balance + $tentativeDelta;

    $result[] = [
        'id' => $friend['id'],
        'name' => $friend['name'],
        'my_points' => $myPoints,
        'their_points' => $theirPoints,
        'balance' => $balance,
        'beers_owed_to_me' => $balance > 0 ? intdiv($balance, 2) : 0,
        'beers_i_owe' => $balance < 0 ? intdiv(-$balance, 2) : 0,
        'tentative_balance' => $tentativeBalance,
        'is_tentative' => $tentativeDelta !== 0,
        'alert' => $alert,
    ];
}

$trainedToday = $userTrainedToday;

// Pedidos de saldo pendientes que YO hice (esperando que el otro confirme)
$stmt = $db->prepare("
    SELECT s.id, s.friend_id, u.name AS friend_name
    FROM settlements s JOIN users u ON u.id = s.friend_id
    WHERE s.requester_id = ? AND s.status = 'pending'
");
$stmt->execute([$user['id']]);
$sentSettlements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pedidos de saldo pendientes que ME llegaron (tengo que confirmar o rechazar)
$stmt = $db->prepare("
    SELECT s.id, s.requester_id, u.name AS requester_name
    FROM settlements s JOIN users u ON u.id = s.requester_id
    WHERE s.friend_id = ? AND s.status = 'pending'
");
$stmt->execute([$user['id']]);
$receivedSettlements = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonResponse([
    'user' => ['id' => $user['id'], 'name' => $user['name'], 'invite_code' => $user['invite_code']],
    'friends' => $result,
    'trained_today' => $trainedToday,
    'sent_settlements' => $sentSettlements,
    'received_settlements' => $receivedSettlements
]);