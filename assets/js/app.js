const API_BASE = '/api';

const api = {
  async request(path, opts = {}) {
    const token = localStorage.getItem('token');
    const headers = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = 'Bearer ' + token;
    const res = await fetch(API_BASE + path, { ...opts, headers: { ...headers, ...(opts.headers || {}) } });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Error en la solicitud');
    return data;
  },
  get: (p) => api.request(p),
  post: (p, b) => api.request(p, { method: 'POST', body: JSON.stringify(b) }),
  put: (p, b) => api.request(p, { method: 'PUT', body: JSON.stringify(b) }),
  del: (p) => api.request(p, { method: 'DELETE' }),
};

const auth = {
  getUser: () => JSON.parse(localStorage.getItem('user') || 'null'),
  getToken: () => localStorage.getItem('token'),
  isLoggedIn: () => !!localStorage.getItem('token'),
  isAdmin: () => auth.getUser()?.role === 'admin',
  save: (token, user) => { localStorage.setItem('token', token); localStorage.setItem('user', JSON.stringify(user)); },
  logout: () => { localStorage.removeItem('token'); localStorage.removeItem('user'); window.location.href = '/'; },
  requireLogin: () => { if (!auth.isLoggedIn()) window.location.href = '/auth/'; },
  requireAdmin: () => { if (!auth.isAdmin()) window.location.href = '/'; },
};

let toastWrap;
function toast(msg, type = 'info') {
  if (!toastWrap) { toastWrap = document.createElement('div'); toastWrap.className = 'toast-wrap'; document.body.appendChild(toastWrap); }
  const icons = { success: '✓', error: '✕', info: 'ℹ', warning: '⚠' };
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.innerHTML = `<span>${icons[type] || ''}</span><span>${msg}</span>`;
  toastWrap.appendChild(el);
  setTimeout(() => { el.style.animation = 'toastOut .3s ease forwards'; setTimeout(() => el.remove(), 300); }, 3200);
}

function formatGrade(val) {
  const clean = val.toUpperCase().replace(/[\s\-_]+/g, '').trim();
  const m = clean.match(/^(\d{1,2})([A-Z])$/);
  return m ? m[1] + m[2] : clean;
}

function fmtCurrency(n) {
  const num = parseFloat(n);
  if (isNaN(num)) return '$0';
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(num);
}

function fmtDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('es-CO', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function modal(content, opts = {}) {
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.innerHTML = `<div class="modal"><div class="modal-head"><h3>${opts.title || ''}</h3><button class="modal-close">✕</button></div><div class="modal-body">${content}</div></div>`;
  document.body.appendChild(overlay);
  overlay.querySelector('.modal-close').onclick = () => overlay.remove();
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
  return overlay;
}

function confirm(msg, onYes) {
  const m = modal(`<p style="font-size:1rem;margin-bottom:20px">${msg}</p><div class="flex gap-2"><button class="btn btn-danger btn-sm" id="confirm-yes">Confirmar</button><button class="btn btn-ghost btn-sm" id="confirm-no">Cancelar</button></div>`, { title: 'Confirmar acción' });
  m.querySelector('#confirm-yes').onclick = () => { m.remove(); onYes(); };
  m.querySelector('#confirm-no').onclick = () => m.remove();
}

function statusBadge(s) {
  const map = { pending: ['⏳', 'pending', 'Pendiente'], paid: ['✅', 'paid', 'Pagado'], cancelled: ['❌', 'cancelled', 'Cancelado'] };
  const [ic, cls, label] = map[s] || ['?', '', s];
  return `<span class="badge badge-${cls}">${ic} ${label}</span>`;
}

async function updateCartBadge() {
  if (!auth.isLoggedIn() || auth.isAdmin()) return;
  try {
    const items = await api.get('/cart');
    const count = items.reduce((s, i) => s + parseInt(i.quantity), 0);
    document.querySelectorAll('.cart-badge').forEach(el => {
      el.textContent = count;
      el.style.display = count > 0 ? 'flex' : 'none';
    });
  } catch {}
}

function openMobileMenu() {
  document.getElementById('mobile-menu-overlay')?.classList.add('open');
  document.getElementById('mobile-menu-panel')?.classList.add('open');
}

function closeMobileMenu() {
  document.getElementById('mobile-menu-overlay')?.classList.remove('open');
  document.getElementById('mobile-menu-panel')?.classList.remove('open');
}

function buildNav(activePage) {
  const user = auth.getUser();
  const isAdmin = user?.role === 'admin';
  const nav = document.getElementById('main-nav');
  if (!nav) return;

  const mobileLinks = user ? `
    ${isAdmin ? `<button class="mobile-nav-link ${activePage==='admin'?'active':''}" onclick="closeMobileMenu();window.location='/admin/'">📊 Panel Admin</button>` : ''}
    ${!isAdmin ? `<button class="mobile-nav-link ${activePage==='store'?'active':''}" onclick="closeMobileMenu();window.location='/store/'">🍽️ Tienda</button>` : ''}
    ${!isAdmin ? `<button class="mobile-nav-link" onclick="closeMobileMenu();window.location='/store/#orders'">📋 Mis Pedidos</button>` : ''}
    ${!isAdmin ? `<button class="mobile-nav-link" onclick="closeMobileMenu();window.location='/store/#cart'">🛒 Carrito</button>` : ''}
    <div style="height:1px;background:rgba(247,243,236,.08);margin:8px 0"></div>
    <button class="mobile-nav-link accent" onclick="closeMobileMenu();auth.logout()">↩ Cerrar sesión</button>
  ` : `
    <button class="mobile-nav-link" onclick="closeMobileMenu();window.location='/store/'">🍽️ Tienda</button>
    <button class="mobile-nav-link" onclick="closeMobileMenu();window.location='/auth/'">👤 Iniciar sesión</button>
    <button class="mobile-nav-link accent" onclick="closeMobileMenu();window.location='/auth/?tab=register'">✨ Registrarse</button>
  `;

  nav.innerHTML = `
    <a href="/" class="nav-logo">
      <img src="/imgs/icon.png" alt="logo" onerror="this.style.display='none'">
      Cafeteria<span>Escolar</span>
    </a>
    <div class="nav-links">
      ${user ? `
        ${isAdmin ? `<a href="/admin/" class="nav-link ${activePage==='admin'?'active':''}">Panel Admin</a>` : ''}
        ${!isAdmin ? `<a href="/store/" class="nav-link ${activePage==='store'?'active':''}">Tienda</a>` : ''}
        ${!isAdmin ? `<a href="/store/#orders" class="nav-link ${activePage==='orders'?'active':''}">Mis Pedidos</a>` : ''}
        <span id="nav-user-name" class="nav-link" style="color:rgba(247,243,236,.45);cursor:default">👤 ${user.full_name?.split(' ')[0]}</span>
        ${!isAdmin ? `<button class="nav-cart" id="nav-cart-btn" onclick="window.location='/store/#cart'">🛒 Carrito<span class="cart-badge" style="display:none">0</span></button>` : ''}
        <button class="nav-link" onclick="auth.logout()" style="color:rgba(247,243,236,.4)">Salir</button>
      ` : `
        <a href="/store/" class="nav-link ${activePage==='store'?'active':''}">Tienda</a>
        <a href="/auth/" class="btn btn-accent btn-sm">Ingresar</a>
      `}
      <button class="nav-mobile-menu" onclick="openMobileMenu()" aria-label="Menú">☰</button>
    </div>
  `;

  if (!document.getElementById('mobile-menu-overlay')) {
    const overlay = document.createElement('div');
    overlay.id = 'mobile-menu-overlay';
    overlay.className = 'mobile-menu-overlay';
    overlay.onclick = closeMobileMenu;
    document.body.appendChild(overlay);

    const panel = document.createElement('div');
    panel.id = 'mobile-menu-panel';
    panel.className = 'mobile-menu-panel';
    panel.innerHTML = `<button class="mobile-menu-close" onclick="closeMobileMenu()">✕</button>${mobileLinks}`;
    document.body.appendChild(panel);
  }

  if (!isAdmin) updateCartBadge();
}