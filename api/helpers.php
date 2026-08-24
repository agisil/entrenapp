<?php
require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function getJsonBody() {
    $data = json_decode(file_get_contents('php://input'), true);
    return $data ?? [];
}

// Busca al usuario dueño de un token. Si no existe, corta la ejecución con 401.
function getUserByToken($token) {
    if (!$token) {
        jsonResponse(['error' => 'Falta token'], 401);
    }
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE token = ?');
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        jsonResponse(['error' => 'Token inválido'], 401);
    }
    return $user;
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function generateInviteCode() {
    // 6 caracteres, sin caracteres ambiguos (0/O, 1/I)
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// Elige un color de una paleta fija, evitando repetir uno ya usado mientras haya disponibles
function pickUserColor($db) {
    $palette = ['#2563eb', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
    $stmt = $db->query('SELECT color FROM users');
    $used = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $available = array_values(array_diff($palette, $used));
    if (empty($available)) {
        return $palette[array_rand($palette)]; // ya se usaron todos, repite al azar
    }
    return $available[array_rand($available)];
}

// Recalcula los puntos de un usuario contra todos sus amigos, para una fecha puntual.
// Se llama cada vez que alguien carga un entrenamiento, así el balance se actualiza al instante.
function recalcPointsForUserDate($db, $userId, $date) {
    $stmt = $db->prepare('
        SELECT IF(f.user_a_id = ?, f.user_b_id, f.user_a_id) AS friend_id
        FROM friendships f
        WHERE f.user_a_id = ? OR f.user_b_id = ?
    ');
    $stmt->execute([$userId, $userId, $userId]);
    $friendIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $checkWorkout = $db->prepare('SELECT id FROM workouts WHERE user_id = ? AND workout_date = ?');
    $deletePoint = $db->prepare('DELETE FROM points WHERE user_a_id = ? AND user_b_id = ? AND point_date = ?');
    $insertPoint = $db->prepare('INSERT IGNORE INTO points (user_a_id, user_b_id, point_date) VALUES (?, ?, ?)');

    $checkWorkout->execute([$userId, $date]);
    $userTrained = (bool)$checkWorkout->fetch();

    foreach ($friendIds as $friendId) {
        $checkWorkout->execute([$friendId, $date]);
        $friendTrained = (bool)$checkWorkout->fetch();

        if ($userTrained && !$friendTrained) {
            // Yo entrené, el amigo no: punto para mí, y me asguro de borrar el punto inverso si existía
            $deletePoint->execute([$friendId, $userId, $date]);
            $insertPoint->execute([$userId, $friendId, $date]);
        } elseif (!$userTrained && $friendTrained) {
            // El amigo entrenó, yo no: punto para él
            $deletePoint->execute([$userId, $friendId, $date]);
            $insertPoint->execute([$friendId, $userId, $date]);
        } else {
            // Los dos entrenaron o ninguno: no debería haber punto ese día entre estos dos
            $deletePoint->execute([$userId, $friendId, $date]);
            $deletePoint->execute([$friendId, $userId, $date]);
        }
    }
}