const API = 'api'; // carpeta con los endpoints PHP

const els = {
  onboarding: document.getElementById('onboarding'),
  main: document.getElementById('main'),
  nameInput: document.getElementById('nameInput'),
  pinInput: document.getElementById('pinInput'),
  registerBtn: document.getElementById('registerBtn'),
  showLoginLink: document.getElementById('showLoginLink'),
  loginScreen: document.getElementById('loginScreen'),
  loginCodeInput: document.getElementById('loginCodeInput'),
  loginPinInput: document.getElementById('loginPinInput'),
  loginBtn: document.getElementById('loginBtn'),
  showRegisterLink: document.getElementById('showRegisterLink'),
  myInviteCode: document.getElementById('myInviteCode'),
  friendsList: document.getElementById('friendsList'),
  settleRequests: document.getElementById('settleRequests'),
  tabBtns: document.querySelectorAll('.tab-btn'),
  friendsTab: document.getElementById('friendsTab'),
  calendarTab: document.getElementById('calendarTab'),
  calendarLegend: document.getElementById('calendarLegend'),
  calendarGrid: document.getElementById('calendarGrid'),
  calendarMonthLabel: document.getElementById('calendarMonthLabel'),
  prevMonthBtn: document.getElementById('prevMonthBtn'),
  nextMonthBtn: document.getElementById('nextMonthBtn'),
  logoutBtn: document.getElementById('logoutBtn'),
  emptyState: document.getElementById('emptyState'),
  addFriendBtn: document.getElementById('addFriendBtn'),
  addWorkoutBtn: document.getElementById('addWorkoutBtn'),
  friendModal: document.getElementById('friendModal'),
  friendCodeInput: document.getElementById('friendCodeInput'),
  friendCancelBtn: document.getElementById('friendCancelBtn'),
  friendConfirmBtn: document.getElementById('friendConfirmBtn'),
  workoutModal: document.getElementById('workoutModal'),
  workoutDate: document.getElementById('workoutDate'),
  workoutType: document.getElementById('workoutType'),
  workoutDuration: document.getElementById('workoutDuration'),
  workoutCancelBtn: document.getElementById('workoutCancelBtn'),
  workoutConfirmBtn: document.getElementById('workoutConfirmBtn'),
  toast: document.getElementById('toast'),
};

function getToken() {
  return localStorage.getItem('entrenapp_token');
}

function setToken(token) {
  localStorage.setItem('entrenapp_token', token);
}

function showToast(msg) {
  els.toast.textContent = msg;
  els.toast.classList.remove('hidden');
  setTimeout(() => els.toast.classList.add('hidden'), 2500);
}

function showScreen(screen) {
  els.onboarding.classList.add('hidden');
  els.loginScreen.classList.add('hidden');
  els.main.classList.add('hidden');
  els.addWorkoutBtn.classList.add('hidden');
  screen.classList.remove('hidden');
}

async function apiCall(endpoint, method = 'GET', body = null) {
  const opts = { method };
  if (body) {
    opts.headers = { 'Content-Type': 'application/json' };
    opts.body = JSON.stringify(body);
  }
  const res = await fetch(`${API}/${endpoint}`, opts);
  const data = await res.json();
  if (!res.ok) {
    throw new Error(data.error || 'Error desconocido');
  }
  return data;
}

// --- Registro ---
els.registerBtn.addEventListener('click', async () => {
  const name = els.nameInput.value.trim();
  const pin = els.pinInput.value.trim();
  if (!name) return showToast('Ingresá un nombre');
  if (!/^\d{4}$/.test(pin)) return showToast('El PIN debe ser de 4 dígitos');
  try {
    const data = await apiCall('register.php', 'POST', { name, pin });
    setToken(data.token);
    showToast(`¡Listo! Guardá tu código: ${data.invite_code}`);
    loadDashboard();
  } catch (e) {
    showToast(e.message);
  }
});

// --- Login (recuperar cuenta en otro dispositivo) ---
els.showLoginLink.addEventListener('click', () => showScreen(els.loginScreen));
els.showRegisterLink.addEventListener('click', () => showScreen(els.onboarding));

els.loginBtn.addEventListener('click', async () => {
  const name = els.loginCodeInput.value.trim();
  const pin = els.loginPinInput.value.trim();
  if (!name || !pin) return showToast('Completá nombre y PIN');
  try {
    const data = await apiCall('login.php', 'POST', { name, pin });
    setToken(data.token);
    loadDashboard();
  } catch (e) {
    showToast(e.message);
  }
});

// --- Dashboard ---
async function loadDashboard() {
  const token = getToken();
  if (!token) {
    showScreen(els.onboarding);
    return;
  }
  try {
    const data = await apiCall(`dashboard.php?token=${token}`);
    renderDashboard(data);
    showScreen(els.main);
    els.addWorkoutBtn.classList.remove('hidden');
    syncLastKnownChange();
  } catch (e) {
    // Token inválido: limpiamos y volvemos a onboarding
    localStorage.removeItem('entrenapp_token');
    showScreen(els.onboarding);
  }
}

function renderDashboard(data) {
  els.myInviteCode.textContent = `Tu código: ${data.user.invite_code}`;

  els.addWorkoutBtn.textContent = data.trained_today
    ? '✓ Ya entrenaste hoy'
    : '+ Entrenamiento';

  renderSettleRequests(data.received_settlements, data.sent_settlements);

  els.friendsList.innerHTML = '';

  if (data.friends.length === 0) {
    els.emptyState.classList.remove('hidden');
    return;
  }
  els.emptyState.classList.add('hidden');

  data.friends.forEach(friend => {
    const card = document.createElement('div');
    card.className = 'friend-card';

    let statusHtml = '';
    let showSettleBtn = false;
    // El botón de saldar y el estado de "debe cerveza" usan el balance REAL (confirmado), no el tentativo
    if (friend.beers_owed_to_me > 0) {
      statusHtml = `<div class="beer-status owed-to-me">🍺 Te debe ${friend.beers_owed_to_me} cerveza(s)</div>`;
      showSettleBtn = true;
    } else if (friend.beers_i_owe > 0) {
      statusHtml = `<div class="beer-status i-owe">🍺 Le debés ${friend.beers_i_owe} cerveza(s)</div>`;
      showSettleBtn = true;
    } else {
      statusHtml = `<div class="beer-status even">Sin cervezas pendientes</div>`;
    }

    const alertHtml = friend.alert
      ? `<div class="today-alert">⏰ ${escapeHtml(friend.alert)}</div>`
      : '';

    const settleBtnHtml = showSettleBtn
      ? `<button class="settle-btn" data-friend-id="${friend.id}" data-friend-name="${escapeHtml(friend.name)}">Saldar cuenta</button>`
      : '';

    card.innerHTML = `
      <div class="friend-card-header">
        <div class="friend-info">
          <span class="friend-name">${escapeHtml(friend.name)}</span>
          <span class="friend-score">${friend.my_points} - ${friend.their_points}</span>
        </div>
        ${renderBeerGlass(friend.tentative_balance, friend.beers_owed_to_me, friend.beers_i_owe, friend.is_tentative)}
      </div>
      ${alertHtml}
      ${statusHtml}
      ${settleBtnHtml}
    `;
    els.friendsList.appendChild(card);
  });

  document.querySelectorAll('.settle-btn').forEach(btn => {
    btn.addEventListener('click', () => requestSettle(btn.dataset.friendId, btn.dataset.friendName));
  });
}

function renderSettleRequests(received, sent) {
  els.settleRequests.innerHTML = '';

  received.forEach(req => {
    const box = document.createElement('div');
    box.className = 'settle-request-box';
    box.innerHTML = `
      <p>${escapeHtml(req.requester_name)} quiere saldar la cuenta con vos 🍺</p>
      <div class="modal-actions">
        <button class="secondary-btn reject-settle-btn" data-id="${req.id}">Rechazar</button>
        <button class="approve-settle-btn" data-id="${req.id}">Confirmar</button>
      </div>
    `;
    els.settleRequests.appendChild(box);
  });

  sent.forEach(req => {
    const box = document.createElement('div');
    box.className = 'settle-request-box pending';
    box.innerHTML = `<p>Esperando que ${escapeHtml(req.friend_name)} confirme el saldo de cuenta...</p>`;
    els.settleRequests.appendChild(box);
  });

  document.querySelectorAll('.approve-settle-btn').forEach(btn => {
    btn.addEventListener('click', () => resolveSettle(btn.dataset.id, true));
  });
  document.querySelectorAll('.reject-settle-btn').forEach(btn => {
    btn.addEventListener('click', () => resolveSettle(btn.dataset.id, false));
  });
}

async function requestSettle(friendId, friendName) {
  if (!confirm(`¿Pedirle a ${friendName} que confirme el saldo de cuenta?`)) return;
  try {
    await apiCall('request_settle.php', 'POST', { token: getToken(), friend_id: friendId });
    showToast('Pedido enviado, esperando confirmación');
    loadDashboard();
  } catch (e) {
    showToast(e.message);
  }
}

async function resolveSettle(settlementId, approve) {
  try {
    await apiCall('confirm_settle.php', 'POST', { token: getToken(), settlement_id: settlementId, approve });
    showToast(approve ? 'Cuenta saldada 🍻' : 'Pedido rechazado');
    loadDashboard();
  } catch (e) {
    showToast(e.message);
  }
}

// Genera un SVG de vaso de cerveza dividido en 5 partes.
// Se llena según el progreso hacia la próxima cerveza: abs(balance) % 5.
// Ámbar = el amigo me debe, rojo = yo le debo, vacío = balance en 0.
function renderBeerGlass(balance, beersOwedToMe, beersIOwe, isTentative) {
  const SEGMENTS = 2; // cantidad de entrenamientos para completar una cerveza
  const progress = Math.abs(balance) % SEGMENTS;
  const color = balance > 0 ? '#f59e0b' : balance < 0 ? '#ef4444' : '#d1d5db';
  const glassW = 32, glassH = 44;
  const segH = glassH / SEGMENTS;
  const totalBeers = beersOwedToMe > 0 ? beersOwedToMe : beersIOwe;
  const glassStroke = isTentative ? '#f59e0b' : '#6b7280';
  const glassDash = isTentative ? 'stroke-dasharray="3,2"' : '';

  let segments = '';
  for (let i = 0; i < SEGMENTS; i++) {
    const y = glassH - (i + 1) * segH;
    const filled = i < progress;
    segments += `<rect x="2" y="${y}" width="${glassW - 4}" height="${segH}"
      fill="${filled ? color : 'transparent'}"
      stroke="#9ca3af" stroke-width="1" />`;
  }

  const badge = totalBeers > 0
    ? `<div class="beer-count" style="color:${color}">🍺×${totalBeers}</div>`
    : '';

  return `
    <div class="beer-glass-wrapper">
      ${badge}
      <svg width="${glassW}" height="${glassH + 6}" viewBox="0 0 ${glassW} ${glassH + 6}">
        <rect x="0" y="0" width="${glassW}" height="${glassH}" rx="3"
          fill="none" stroke="${glassStroke}" stroke-width="2" ${glassDash} />
        ${segments}
      </svg>
      ${isTentative ? '<div class="tentative-label">hoy</div>' : ''}
    </div>
  `;
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// --- Agregar amigo ---
els.addFriendBtn.addEventListener('click', () => {
  els.friendCodeInput.value = '';
  els.friendModal.classList.remove('hidden');
});

els.friendCancelBtn.addEventListener('click', () => {
  els.friendModal.classList.add('hidden');
});

els.friendConfirmBtn.addEventListener('click', async () => {
  const code = els.friendCodeInput.value.trim().toUpperCase();
  if (!code) return showToast('Ingresá un código');
  try {
    const data = await apiCall('add_friend.php', 'POST', { token: getToken(), invite_code: code });
    els.friendModal.classList.add('hidden');
    showToast(`Conectado con ${data.friend.name}`);
    loadDashboard();
  } catch (e) {
    showToast(e.message);
  }
});

// --- Agregar entrenamiento ---
els.addWorkoutBtn.addEventListener('click', () => {
  els.workoutDate.value = new Date().toISOString().split('T')[0];
  els.workoutType.value = '';
  els.workoutDuration.value = '';
  els.workoutModal.classList.remove('hidden');
});

els.workoutCancelBtn.addEventListener('click', () => {
  els.workoutModal.classList.add('hidden');
});

els.workoutConfirmBtn.addEventListener('click', async () => {
  const date = els.workoutDate.value;
  if (!date) return showToast('Elegí una fecha');
  try {
    await apiCall('add_workout.php', 'POST', {
      token: getToken(),
      date,
      type: els.workoutType.value.trim(),
      duration_minutes: els.workoutDuration.value || null,
    });
    els.workoutModal.classList.add('hidden');
    showToast('¡Entrenamiento registrado!');
    loadDashboard();
  } catch (e) {
    showToast(e.message);
  }
});

// --- Tabs ---
els.tabBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    els.tabBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    els.friendsTab.classList.add('hidden');
    els.calendarTab.classList.add('hidden');
    document.getElementById(btn.dataset.tab).classList.remove('hidden');
    if (btn.dataset.tab === 'calendarTab') loadCalendar();
  });
});

// --- Calendario ---
let calendarData = null; // { people, workouts }
let calendarCursor = new Date(); // mes que se está mostrando

async function loadCalendar() {
  try {
    calendarData = await apiCall(`calendar.php?token=${getToken()}`);
    renderCalendarLegend();
    renderCalendarMonth();
    syncLastKnownChange();
  } catch (e) {
    showToast(e.message);
  }
}

async function syncLastKnownChange() {
  try {
    const { last_change } = await apiCall(`check_updates.php?token=${getToken()}`);
    lastKnownChange = last_change;
  } catch (e) {
    // no-op
  }
}

function renderCalendarLegend() {
  els.calendarLegend.innerHTML = calendarData.people.map(p => `
    <div class="legend-item">
      <span class="legend-dot" style="background:${p.color}"></span>
      ${escapeHtml(p.name)}
    </div>
  `).join('');
}

els.prevMonthBtn.addEventListener('click', () => {
  calendarCursor.setMonth(calendarCursor.getMonth() - 1);
  renderCalendarMonth();
});

els.nextMonthBtn.addEventListener('click', () => {
  calendarCursor.setMonth(calendarCursor.getMonth() + 1);
  renderCalendarMonth();
});

function renderCalendarMonth() {
  const year = calendarCursor.getFullYear();
  const month = calendarCursor.getMonth(); // 0-indexed

  const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  els.calendarMonthLabel.textContent = `${monthNames[month]} ${year}`;

  // Mapa: 'YYYY-MM-DD' -> [colores de quienes entrenaron ese día]
  const dayColors = {};
  calendarData.workouts.forEach(w => {
    const person = calendarData.people.find(p => p.id == w.user_id);
    if (!person) return;
    if (!dayColors[w.workout_date]) dayColors[w.workout_date] = [];
    dayColors[w.workout_date].push(person.color);
  });

  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const startOffset = (firstDay.getDay() + 6) % 7; // que la semana arranque en lunes
  const todayStr = new Date().toISOString().split('T')[0];

  let html = ['Lu','Ma','Mi','Ju','Vi','Sa','Do']
    .map(d => `<div class="calendar-weekday">${d}</div>`).join('');

  for (let i = 0; i < startOffset; i++) {
    html += `<div class="calendar-day empty"></div>`;
  }

  for (let day = 1; day <= lastDay.getDate(); day++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const colors = dayColors[dateStr] || [];
    const dotsHtml = colors.map(c => `<span class="dot" style="background:${c}"></span>`).join('');
    const isToday = dateStr === todayStr ? 'today' : '';
    html += `
      <div class="calendar-day ${isToday}">
        <span>${day}</span>
        <div class="calendar-day-dots">${dotsHtml}</div>
      </div>
    `;
  }

  els.calendarGrid.innerHTML = html;
}

// --- Logout ---
els.logoutBtn.addEventListener('click', () => {
  if (!confirm('¿Cerrar sesión en este dispositivo?')) return;
  localStorage.removeItem('entrenapp_token');
  showScreen(els.onboarding);
});

// --- Init ---
loadDashboard();

// --- Auto-refresh: polling cada 15s para reflejar cambios de amigos sin recargar ---
let activeTab = 'friendsTab';
let lastKnownChange = null;
els.tabBtns.forEach(btn => {
  btn.addEventListener('click', () => { activeTab = btn.dataset.tab; });
});

setInterval(async () => {
  if (document.hidden || !getToken()) return;
  const modalOpen = !els.friendModal.classList.contains('hidden') || !els.workoutModal.classList.contains('hidden');
  if (modalOpen) return;

  try {
    const { last_change } = await apiCall(`check_updates.php?token=${getToken()}`);
    if (last_change !== lastKnownChange) {
      lastKnownChange = last_change;
      if (activeTab === 'calendarTab') {
        loadCalendar();
      } else {
        loadDashboard();
      }
    }
  } catch (e) {
    // Silencioso: si falla el chequeo, no interrumpimos la experiencia
  }
}, 5000);

// --- PWA: registrar service worker ---
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js').catch((err) => console.error('SW error:', err));
  });
}