<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EntrenApp</title>
<link rel="icon" type="image/x-icon" href="/entrenapp/icons/Recurso 1.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#f4f5f7">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="entrenapp/icons/icon_square_2.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:ital,wght@0,200..900;1,200..900&family=Special+Gothic+Expanded+One&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
</head>
<body>

<div id="app">

  <!-- Pantalla de registro (primera vez) -->
  <div id="onboarding" class="screen hidden">
    <h1>🏋️ EntrenApp</h1>
    <p>Elegí tu nombre y un PIN de 4 dígitos</p>
    <input type="text" id="nameInput" placeholder="Tu nombre" maxlength="50">
    <input type="text" id="pinInput" placeholder="PIN (4 dígitos)" maxlength="4" inputmode="numeric">
    <button id="registerBtn">Empezar</button>
    <p class="link-text" id="showLoginLink">Ya tengo cuenta, quiero entrar desde otro dispositivo</p>
  </div>

  <!-- Pantalla de login (recuperar cuenta en otro dispositivo) -->
  <div id="loginScreen" class="screen hidden">
    <h1>🏋️ EntrenApp</h1>
    <p>Ingresá tu nombre y tu PIN</p>
    <input type="text" id="loginCodeInput" placeholder="Tu nombre" maxlength="50">
    <input type="text" id="loginPinInput" placeholder="PIN (4 dígitos)" maxlength="4" inputmode="numeric">
    <button id="loginBtn">Entrar</button>
    <p class="link-text" id="showRegisterLink">Todavía no tengo cuenta</p>
  </div>

  <!-- Pantalla principal -->
  <div id="main" class="screen hidden">
    <header>
      <div class="entrenapp-logo">
          <img src="/entrenapp/icons/Recurso 1.png" />
          <h1>Entrenapp</h1>
      </div>
    </header>
    <nav class="navbar">
      <div id="myInviteCode"></div>
      <button id="addFriendBtn" class="secondary-btn">+ Agregar amigo</button>
      <button id="logoutBtn" class="logout-btn" title="Cerrar sesión">⎋</button>
    </nav>

    <div class="tabs">
      <button class="tab-btn active" data-tab="friendsTab">Amigos</button>
      <button class="tab-btn" data-tab="calendarTab">Calendario</button>
    </div>

    <div id="friendsTab" class="tab-content">
      <div id="settleRequests"></div>
      <div id="friendsList"></div>
      <div id="emptyState" class="hidden">
        <p>Todavía no tenés amigos conectados.</p>
        <p>Compartí tu código de invitación o agregá uno.</p>
      </div>
    </div>

    <div id="calendarTab" class="tab-content hidden">
      <div id="calendarLegend"></div>
      <div id="calendarNav">
        <button id="prevMonthBtn">‹</button>
        <span id="calendarMonthLabel"></span>
        <button id="nextMonthBtn">›</button>
      </div>
      <div id="calendarGrid"></div>
    </div>
  </div>

  <!-- Botón fijo para agregar entrenamiento -->
  <button id="addWorkoutBtn" class="fab hidden">+ Entrenamiento</button>

  <!-- Modal: agregar amigo -->
  <div id="friendModal" class="modal hidden">
    <div class="modal-content">
      <h2>Agregar amigo</h2>
      <p>Pedile su código de invitación</p>
      <input type="text" id="friendCodeInput" placeholder="CÓDIGO" maxlength="6" style="text-transform:uppercase">
      <div class="modal-actions">
        <button id="friendCancelBtn" class="secondary-btn">Cancelar</button>
        <button id="friendConfirmBtn">Conectar</button>
      </div>
    </div>
  </div>

  <!-- Modal: agregar entrenamiento -->
  <div id="workoutModal" class="modal hidden">
    <div class="modal-content">
      <h2>Registrar entrenamiento</h2>
      <label>Fecha</label>
      <input type="date" id="workoutDate">
      <label>Tipo (opcional)</label>
      <input type="text" id="workoutType" placeholder="Ej: gym, running, yoga">
      <label>Duración en minutos (opcional)</label>
      <input type="number" id="workoutDuration" min="1" placeholder="Ej: 45">
      <div class="modal-actions">
        <button id="workoutCancelBtn" class="secondary-btn">Cancelar</button>
        <button id="workoutConfirmBtn">Guardar</button>
      </div>
    </div>
  </div>

  <div id="toast" class="toast hidden"></div>

</div>

<script src="app.js?v=<?php echo filemtime(__DIR__ . '/app.js'); ?>"></script>
</body>
</html>