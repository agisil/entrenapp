<?php
require_once __DIR__ . '/helpers.php';

$token = $_GET['token'] ?? null;
$user = getUserByToken($token);
$db = getDB();

// Rango opcional (por defecto: últimos 3 meses hasta hoy, suficiente para pintar el calendario)
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-3 months'));
$to = $_GET['to'] ?? date('Y-m-d');

// Trae al usuario + todos sus amigos, con su color
$stmt = $db->prepare('
    SELECT u.id, u.name, u.color
    FROM users u
    WHERE u.id = ?
    UNION
    SELECT u.id, u.name, u.color
    FROM friendships f
    JOIN users u ON u.id = IF(f.user_a_id = ?, f.user_b_id, f.user_a_id)
    WHERE f.user_a_id = ? OR f.user_b_id = ?
');
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
$people = $stmt->fetchAll(PDO::FETCH_ASSOC);

$peopleIds = array_column($people, 'id');
if (empty($peopleIds)) {
    jsonResponse(['people' => [], 'workouts' => []]);
}

// Trae todos los entrenamientos de ese grupo de personas en el rango de fechas
$placeholders = implode(',', array_fill(0, count($peopleIds), '?'));
$stmt = $db->prepare("
    SELECT user_id, workout_date, type, duration_minutes
    FROM workouts
    WHERE user_id IN ($placeholders) AND workout_date BETWEEN ? AND ?
    ORDER BY workout_date ASC
");
$stmt->execute([...$peopleIds, $from, $to]);
$workouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonResponse([
    'people' => $people,
    'workouts' => $workouts
]);
