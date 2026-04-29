window.addEventListener('unhandledrejection', function(event) {
  console.error('Promesa rechazada no manejada:', event.reason);
  toast('Error inesperado: ' + (event.reason?.message || 'Ver consola para detalles'), 'error');
});

const originalFetch = window.fetch;
window.fetch = function(...args) {
  console.log('[FETCH]', args[0], args[1]?.method || 'GET');
  return originalFetch.apply(this, args).catch(err => {
    console.error('[FETCH ERROR]', args[0], err);
    throw err;
  });
};

const API_BASE = '/api';

const api = {
  async request(path, opts = {}) {
    const token = localStorage.getItem('token');
    const headers = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = 'Bearer ' + token;

    let url = API_BASE + path;
    if (token) {
      const separator = url.includes('?') ? '&' : '?';
      url = url + separator + 'token=' + encodeURIComponent(token);
    }
    
    try {
      const res = await fetch(url, { ...opts, headers: { ...headers, ...(opts.headers || {}) } });
      
      console.log(`[API] ${opts.method || 'GET'} ${path} -> Status: ${res.status}`);
      
      let data;
      const contentType = res.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        data = await res.json();
      } else {
        const text = await res.text();
        console.error(`[API] Respuesta no JSON para ${path}:`, text.substring(0, 500));
        throw new Error(`Respuesta no JSON (Status ${res.status}): ${text.substring(0, 200)}`);
      }
      
      if (!res.ok) {
        const error = new Error(data.error || data.message || `Error ${res.status}: ${res.statusText}`);
        error.status = res.status;
        error.details = data;
        error.endpoint = path;
        console.error(`[API Error] ${path}:`, error);
        throw error;
      }
      return data;
    } catch (e) {
      if (e.name === 'TypeError' && e.message.includes('fetch')) {
        throw new Error(`Error de conexión: No se pudo conectar al servidor. Verifica tu conexión a internet.`);
      }
      throw e;
    }
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
  const colors = { success: '#276a44', error: '#b83232', info: '#111111', warning: '#c07020' };
  
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.style.backgroundColor = colors[type];
  el.style.color = '#fff';
  el.style.maxWidth = '500px';
  el.style.wordBreak = 'break-word';
  el.style.whiteSpace = 'pre-wrap';
  
  let displayMsg = typeof msg === 'object' ? JSON.stringify(msg, null, 2) : String(msg);
  
  el.innerHTML = `
    <div style="display:flex;align-items:flex-start;gap:12px;">
      <span style="font-size:1.2rem;">${icons[type] || ''}</span>
      <div style="flex:1;font-size:0.85rem;line-height:1.4;">
        ${displayMsg.replace(/\n/g, '<br>')}
      </div>
      <button style="background:none;border:none;color:#fff;cursor:pointer;font-size:1rem;opacity:0.7;" onclick="this.parentElement.parentElement.remove()">✕</button>
    </div>
  `;
  
  toastWrap.appendChild(el);
  
  const timeout = setTimeout(() => {
    if (el.parentElement) {
      el.style.animation = 'toastOut .3s ease forwards';
      setTimeout(() => el.remove(), 300);
    }
  }, type === 'error' ? 8000 : 5000);
  
  el.querySelector('button')?.addEventListener('click', () => {
    clearTimeout(timeout);
    el.remove();
  });
}

function formatGrade(val) {
  const clean = val.toUpperCase().replace(/[\s\-_]+/g, '').trim();
  const m = clean.match(/^(\d{1,2})([A-Z])$/);
  return m ? m[1] + m[2] : clean;
}

const COLOMBIA_TZ = 'America/Bogota';

function fmtDate(d) {
    if (!d) return '—';
    const date = new Date(d);
    return date.toLocaleString('es-CO', { 
        timeZone: COLOMBIA_TZ,
        day: '2-digit', 
        month: 'short', 
        year: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

function fmtCurrency(n) {
    const num = parseFloat(n);
    if (isNaN(num)) return '$0';
    return new Intl.NumberFormat('es-CO', { 
        style: 'currency', 
        currency: 'COP', 
        maximumFractionDigits: 0 
    }).format(num);
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
    <button class="mobile-nav-link ${activePage==='store'?'active':''}" onclick="closeMobileMenu();window.location='/store/'">🍽️ Tienda</button>
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
        <button href="/store/" class="nav-link ${activePage==='store'?'active':''}">Tienda</button>
        ${!isAdmin ? `<a href="/store/#orders" class="nav-link ${activePage==='orders'?'active':''}">Mis Pedidos</a>` : ''}
        <span id="nav-user-name" class="nav-link" style="color:rgba(36, 24, 3, 0.45);cursor:default">👤 ${user.full_name?.split(' ')[0]}</span>
        ${!isAdmin ? `<button class="nav-cart" id="nav-cart-btn" onclick="window.location='/store/#cart'">🛒 Carrito<span class="cart-badge" style="display:none">0</span></button>` : ''}
        <button class="nav-link active" onclick="auth.logout()" style="color:rgba(247,243,236,.4)">Salir</button>
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