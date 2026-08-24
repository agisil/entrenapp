<?php
// Este script se ejecuta 1 vez por día via cron job de cPanel (configurar a las 00:05, por ejemplo)
// Compara el día ANTERIOR entre cada par de amigos y asigna el punto a quien entrenó
// mientras el otro no.
//
// Ejemplo de línea de cron en cPanel:
// 5 0 * * * php /home/tu_usuario/entrenapp/cron/process_day.php

require_once __DIR__ . '/../../../config.php';

$db = getDB();
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Trae todas las parejas de amigos
$stmt = $db->query('SELECT user_a_id, user_b_id FROM friendships');
$friendships = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtWorkout = $db->prepare('SELECT id FROM workouts WHERE user_id = ? AND workout_date = ?');
$stmtInsertPoint = $db->prepare('
    INSERT IGNORE INTO points (user_a_id, user_b_id, point_date) VALUES (?, ?, ?)
');

$pointsAwarded = 0;

foreach ($friendships as $pair) {
    $stmtWorkout->execute([$pair['user_a_id'], $yesterday]);
    $aTrained = (bool)$stmtWorkout->fetch();

    $stmtWorkout->execute([$pair['user_b_id'], $yesterday]);
    $bTrained = (bool)$stmtWorkout->fetch();

    // Solo se asigna punto si UNO entrenó y el otro no (si entrenaron los dos o ninguno, no pasa nada)
    if ($aTrained && !$bTrained) {
        $stmtInsertPoint->execute([$pair['user_a_id'], $pair['user_b_id'], $yesterday]);
        $pointsAwarded++;
    } elseif ($bTrained && !$aTrained) {
        $stmtInsertPoint->execute([$pair['user_b_id'], $pair['user_a_id'], $yesterday]);
        $pointsAwarded++;
    }
}

echo "Procesado $yesterday: $pointsAwarded puntos asignados." . PHP_EOL;