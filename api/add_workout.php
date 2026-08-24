<?php
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$body = getJsonBody();
$user = getUserByToken($body['token'] ?? null);

$date = $body['date'] ?? date('Y-m-d'); // si no manda fecha, usa hoy
$type = trim($body['type'] ?? '') ?: null;
$duration = isset($body['duration_minutes']) ? (int)$body['duration_minutes'] : null;

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonResponse(['error' => 'Formato de fecha inválido (usar YYYY-MM-DD)'], 400);
}

$db = getDB();

// Si ya había un entrenamiento ese día, lo actualiza (no duplica) gracias al UNIQUE KEY
$stmt = $db->prepare('
    INSERT INTO workouts (user_id, workout_date, type, duration_minutes)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE type = ?, duration_minutes = ?
');
$stmt->execute([$user['id'], $date, $type, $duration, $type, $duration]);

// Solo recalculamos puntos definitivos si la fecha ya pasó (carga atrasada).
// El día de hoy se cierra recién con el cron a las 00:05, para darle tiempo al otro a entrenar.
if ($date < date('Y-m-d')) {
    recalcPointsForUserDate($db, $user['id'], $date);
}

jsonResponse(['ok' => true, 'date' => $date]);