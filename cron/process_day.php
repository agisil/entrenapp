<?php
// Este script se ejecuta cada hora via cron de cPanel (el servidor está en UTC).
// Fuerza la zona horaria de Europa y solo procesa si es la hora 00 en esa zona,
// así no hay que recalcular el offset cada vez que cambia el horario de verano.

date_default_timezone_set('Europe/Madrid');

if ((int)date('H') !== 0) {
    echo "No es medianoche en Europe/Madrid todavía (son las " . date('H:i') . "), no se procesa." . PHP_EOL;
    exit;
}

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