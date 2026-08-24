<?php
require_once __DIR__ . '/helpers.php';

$token = $_GET['token'] ?? null;
$user = getUserByToken($token);
$db = getDB();

// IDs de amigos + el propio usuario, para filtrar solo lo relevante
$stmt = $db->prepare('
    SELECT IF(f.user_a_id = ?, f.user_b_id, f.user_a_id) AS friend_id
    FROM friendships f WHERE f.user_a_id = ? OR f.user_b_id = ?
');
$stmt->execute([$user['id'], $user['id'], $user['id']]);
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
$ids[] = $user['id'];

$placeholders = implode(',', array_fill(0, count($ids), '?'));

// Último cambio entre: entrenamientos, puntos, o settlements de este grupo de personas
$stmt = $db->prepare("
    SELECT MAX(t) FROM (
        SELECT MAX(created_at) AS t FROM workouts WHERE user_id IN ($placeholders)
        UNION ALL
        SELECT MAX(created_at) FROM points WHERE user_a_id IN ($placeholders) OR user_b_id IN ($placeholders)
        UNION ALL
        SELECT MAX(GREATEST(created_at, IFNULL(resolved_at, created_at))) FROM settlements WHERE requester_id IN ($placeholders) OR friend_id IN ($placeholders)
        UNION ALL
        SELECT MAX(created_at) FROM friendships WHERE user_a_id IN ($placeholders) OR user_b_id IN ($placeholders)
    ) sub
");
$stmt->execute(array_merge($ids, $ids, $ids, $ids, $ids, $ids, $ids));
$lastChange = $stmt->fetchColumn();

jsonResponse(['last_change' => $lastChange]);