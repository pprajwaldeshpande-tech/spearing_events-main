/* ============================================================
   shared.js — Spearing Events (Full Updated Version)
   ============================================================ */

// ── Toast notifications ─────────────────────────────────────
function showToast(message, type = 'info') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const toast  = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);
  setTimeout(() => {
    toast.classList.add('hide');
    toast.addEventListener('animationend', () => toast.remove());
  }, 3500);
}

// ── localStorage helpers ────────────────────────────────────
const LS = {
  get:    key        => JSON.parse(localStorage.getItem(key) || 'null'),
  set:    (key, val) => localStorage.setItem(key, JSON.stringify(val)),
  remove: key        => localStorage.removeItem(key)
};

// ── Auth helpers ────────────────────────────────────────────
function getCurrentUser() { return LS.get('currentUser'); }
function isLoggedIn()     { return !!getCurrentUser(); }

function logout() {
  LS.remove('currentUser');
  window.location.href = 'login.html';
}

function requireAuth(role) {
  const user = getCurrentUser();
  if (!user) { window.location.href = 'login.html'; return false; }
  if (role && user.role !== role) {
    showToast('Access denied', 'error');
    window.location.href = user.role === 'admin' ? 'admin.html' : 'user.html';
    return false;
  }
  return true;
}

// ── Theme and password controls ──────────────────────────────
function initializeTheme() {
  const storageKey = 'eventspot-theme';
  const savedTheme = localStorage.getItem(storageKey);
  const preferredTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

  function applyTheme(theme) {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem(storageKey, theme);
    const button = document.getElementById('theme-toggle');
    if (button) {
      button.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
      button.setAttribute('aria-label', `Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`);
      button.setAttribute('aria-pressed', String(theme === 'dark'));
    }
  }

  const navLinks = document.querySelector('.nav-links');
  if (navLinks && !document.getElementById('theme-toggle')) {
    const item = document.createElement('li');
    const button = document.createElement('button');
    button.id = 'theme-toggle';
    button.type = 'button';
    button.className = 'theme-toggle';
    button.addEventListener('click', () => applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'));
    item.appendChild(button);
    navLinks.appendChild(item);
  }

  applyTheme(savedTheme === 'dark' || savedTheme === 'light' ? savedTheme : preferredTheme);
}

function initializePasswordToggles() {
  document.querySelectorAll('input[type="password"]').forEach(input => {
    if (input.dataset.visibilityToggle === 'true') return;
    input.dataset.visibilityToggle = 'true';
    const wrapper = document.createElement('div');
    wrapper.className = 'password-field';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.textContent = 'Show';
    button.setAttribute('aria-label', 'Show password');
    button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', () => {
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.textContent = visible ? 'Hide' : 'Show';
      button.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} password`);
      button.setAttribute('aria-pressed', String(visible));
    });
    wrapper.appendChild(button);
  });
}

initializeTheme();
initializePasswordToggles();

// ── Date formatting ─────────────────────────────────────────
function formatDate(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr + 'T00:00:00');
  return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

// ── Rupee formatter ─────────────────────────────────────────
function formatRupees(amount) {
  if (!amount || amount === 0) return 'Free';
  return '₹' + Number(amount).toLocaleString('en-IN');
}

// ── Category badge ──────────────────────────────────────────
const CAT_COLORS = {
  Technology : '#7c5cfc',
  Music      : '#f43f5e',
  Food       : '#fb923c',
  Business   : '#22d3a0',
  Health     : '#34d399',
  Arts       : '#a78bfa',
  Sports     : '#60a5fa',
  Education  : '#facc15',
  Other      : '#94a3b8'
};

function catBadge(cat) {
  const color = CAT_COLORS[cat] || CAT_COLORS.Other;
  return `<span class="badge" style="background:${color}22;color:${color};border-color:${color}55">${cat || 'Other'}</span>`;
}

// ── Event image map ─────────────────────────────────────────
const CAT_IMAGES = {
  Technology : [
    'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&q=80',
    'https://images.unsplash.com/photo-1591115765373-5207764f72e7?w=600&q=80',
    'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=600&q=80'
  ],
  Music: [
    'https://images.unsplash.com/photo-1501386761578-eaa54b3538c8?w=600&q=80',
    'https://images.unsplash.com/photo-1468359601543-843bfaef291a?w=600&q=80',
    'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600&q=80'
  ],
  Food: [
    'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=600&q=80',
    'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=600&q=80',
    'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=600&q=80'
  ],
  Business: [
    'https://images.unsplash.com/photo-1552581234-26160f608093?w=600&q=80',
    'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?w=600&q=80',
    'https://images.unsplash.com/photo-1556761175-4b46a572b786?w=600&q=80'
  ],
  Health: [
    'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=600&q=80',
    'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=600&q=80',
    'https://images.unsplash.com/photo-1506126613408-eca07ce68773?w=600&q=80'
  ],
  Arts: [
    'https://images.unsplash.com/photo-1460661419201-fd4cecdf8a8b?w=600&q=80',
    'https://images.unsplash.com/photo-1513364776144-60967b0f800f?w=600&q=80',
    'https://images.unsplash.com/photo-1547826039-bfc35e0f1ea8?w=600&q=80'
  ],
  Sports: [
    'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=600&q=80',
    'https://images.unsplash.com/photo-1530549387789-4c1017266635?w=600&q=80',
    'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=600&q=80'
  ],
  Education: [
    'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?w=600&q=80',
    'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=600&q=80',
    'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=600&q=80'
  ],
  Other: [
    'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=600&q=80',
    'https://images.unsplash.com/photo-1529543544282-ea669407fca3?w=600&q=80'
  ]
};

function getEventImage(event) {
  if (event.image && event.image.startsWith('http')) return event.image;
  if (event.image) return event.image.replace(/^\/+/, '');
  const list = CAT_IMAGES[event.category] || CAT_IMAGES.Other;
  const idx  = parseInt(event.id || '0') % list.length;
  return list[idx];
}

// ── Haversine distance (km) ─────────────────────────────────
function haversine(lat1, lon1, lat2, lon2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat/2)**2 +
            Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLon/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

// ── Seed events ─────────────────────────────────────────────
function seedEvents() {
  const existing = LS.get('cachedEvents');
  if (existing && existing.length > 0) return;

  const events = [
    {
      id: '1', title: 'Bengaluru Tech Summit 2026',
      description: 'Annual gathering of developers, designers, and product leaders from across India. Keynotes, workshops, and networking with 2000+ attendees.',
      date: '2026-08-15', category: 'Technology',
      location: 'KTPO Convention Centre, Whitefield',
      latitude: 12.9698, longitude: 77.7500, price: 999, capacity: 200,
      image: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=600&q=80'
    },
    {
      id: '2', title: 'Cubbon Park Music Fest',
      description: 'Live indie bands and acoustic artists performing under the open Bengaluru sky. Food stalls, craft beer, and an unforgettable evening.',
      date: '2026-07-20', category: 'Music',
      location: 'Cubbon Park, MG Road',
      latitude: 12.9763, longitude: 77.5929, price: 499, capacity: 500,
      image: 'https://images.unsplash.com/photo-1501386761578-eaa54b3538c8?w=600&q=80'
    },
    {
      id: '3', title: 'South Indian Food Carnival',
      description: 'A celebration of regional cuisines — dosas, biryanis, seafood, and street snacks from across Karnataka and beyond.',
      date: '2026-07-28', category: 'Food',
      location: 'Palace Grounds, Jayamahal',
      latitude: 13.0037, longitude: 77.5667, price: 199, capacity: 1000,
      image: 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=600&q=80'
    },
    {
      id: '4', title: 'Startup Pitch Night',
      description: 'Early-stage founders present to a panel of top Bengaluru investors. Open audience. Networking session follows pitches.',
      date: '2026-08-05', category: 'Business',
      location: 'Nasscom 10000 Startups Hub, Koramangala',
      latitude: 12.9352, longitude: 77.6245, price: 0, capacity: 150,
      image: 'https://images.unsplash.com/photo-1552581234-26160f608093?w=600&q=80'
    },
    {
      id: '5', title: 'Yoga & Wellness Workshop',
      description: 'Half-day immersive wellness event with yoga, meditation, breathwork, and nutrition talks. All levels welcome.',
      date: '2026-07-13', category: 'Health',
      location: 'Lalbagh Botanical Garden',
      latitude: 12.9507, longitude: 77.5848, price: 349, capacity: 80,
      image: 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=600&q=80'
    },
    {
      id: '6', title: 'Kala Utsav — Art Fair',
      description: 'Paintings, sculptures, and installations by 80+ emerging Karnataka artists. Live mural creation on Day 2.',
      date: '2026-09-01', category: 'Arts',
      location: 'Karnataka Chitrakala Parishath',
      latitude: 12.9900, longitude: 77.5800, price: 150, capacity: 300,
      image: 'https://images.unsplash.com/photo-1460661419201-fd4cecdf8a8b?w=600&q=80'
    },
    {
      id: '7', title: 'Bengaluru Marathon 2026',
      description: 'The city\'s biggest annual running event. Distances for everyone: 5K, 10K, half marathon, and full marathon routes through Bengaluru.',
      date: '2026-10-12', category: 'Sports',
      location: 'Kanteerava Stadium, MG Road',
      latitude: 12.9716, longitude: 77.5946, price: 799, capacity: 5000,
      image: 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=600&q=80'
    },
    {
      id: '8', title: 'Python & AI Workshop',
      description: 'Hands-on full-day workshop covering Python fundamentals, data science, and building your first ML model. Laptops required.',
      date: '2026-08-22', category: 'Education',
      location: 'IISc Campus, Malleshwaram',
      latitude: 13.0219, longitude: 77.5671, price: 1499, capacity: 60,
      image: 'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?w=600&q=80'
    },
    {
      id: '9', title: 'Comedy Night — Stand Up BLR',
      description: 'An evening of laughs with 5 of Bengaluru\'s top stand-up comedians. 18+ event. Doors open at 7 PM.',
      date: '2026-07-19', category: 'Other',
      location: 'The Comedy Factory, Indiranagar',
      latitude: 12.9784, longitude: 77.6408, price: 599, capacity: 200,
      image: 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=600&q=80'
    },
    {
      id: '10', title: 'Women in Tech Bengaluru',
      description: 'A conference celebrating and empowering women in technology. Inspiring talks, mentorship sessions, and a hiring fair.',
      date: '2026-09-14', category: 'Technology',
      location: 'Sheraton Grand, Brigade Gateway',
      latitude: 13.0100, longitude: 77.5530, price: 299, capacity: 400,
      image: 'https://images.unsplash.com/photo-1591115765373-5207764f72e7?w=600&q=80'
    },
    {
      id: '11', title: 'Jazz & Blues Evening',
      description: 'Soulful jazz and blues performances by top Indian and international artists at Bengaluru\'s finest outdoor venue.',
      date: '2026-08-10', category: 'Music',
      location: 'Indiranagar Social, Bengaluru',
      latitude: 12.9716, longitude: 77.6412, price: 699, capacity: 250,
      image: 'https://images.unsplash.com/photo-1468359601543-843bfaef291a?w=600&q=80'
    },
    {
      id: '12', title: 'Street Food Festival',
      description: '200+ food stalls from across India. Regional specialties, fusion dishes, and live cooking demonstrations by celebrity chefs.',
      date: '2026-09-20', category: 'Food',
      location: 'Electronic City Phase 1',
      latitude: 12.8456, longitude: 77.6603, price: 0, capacity: 2000,
      image: 'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=600&q=80'
    }
  ];
  LS.set('cachedEvents', events);
}

// ── Fetch events ────────────────────────────────────────────
async function fetchEvents() {
  try {
    const res  = await fetch('/events');
    const data = await res.json();
    if (data.success && data.events.length > 0) {
      LS.set('cachedEvents', data.events);
      return data.events;
    }
  } catch (e) {}
  seedEvents();
  return LS.get('cachedEvents') || [];
}

// ── Add event ───────────────────────────────────────────────
async function addEventAPI(payload) {
  try {
    const res = await fetch('/add-event', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    return await res.json();
  } catch (e) {
    const events = LS.get('cachedEvents') || [];
    payload.id = Date.now().toString();
    events.push(payload);
    LS.set('cachedEvents', events);
    return { success: true, event: payload };
  }
}

// ── Delete event ────────────────────────────────────────────
async function deleteEventAPI(id) {
  try {
    const res = await fetch(`/delete-event/${id}`, { method: 'DELETE' });
    return await res.json();
  } catch (e) {
    let events = LS.get('cachedEvents') || [];
    events = events.filter(e => e.id !== id);
    LS.set('cachedEvents', events);
    return { success: true };
  }
}

// ── Edit event ──────────────────────────────────────────────
async function editEventAPI(id, payload) {
  try {
    const res = await fetch(`/edit-event/${id}`, {
      method: 'PUT', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    return await res.json();
  } catch (e) {
    let events = LS.get('cachedEvents') || [];
    const index = events.findIndex(e => e.id === id);
    if (index !== -1) {
      events[index] = { ...events[index], ...payload };
      LS.set('cachedEvents', events);
      return { success: true, event: events[index] };
    }
    return { success: false };
  }
}

// ── Registration helpers ────────────────────────────────────
function getUserRegistrations(username) {
  const regs = LS.get('registrations') || [];
  return regs.filter(r => r.username === username);
}

function addRegistration(username, eventId, eventTitle, amount) {
  const regs = LS.get('registrations') || [];
  const existing = regs.find(r => r.username === username && r.eventId === eventId);
  if (existing) return false;
  regs.push({
    id: Date.now().toString(),
    username, eventId, eventTitle, amount,
    status: 'pending',
    paidAt: new Date().toISOString(),
    paymentId: 'PAY' + Math.random().toString(36).substr(2, 9).toUpperCase()
  });
  LS.set('registrations', regs);
  return true;
}

function updateRegistrationStatus(regId, status) {
  const regs = LS.get('registrations') || [];
  const idx = regs.findIndex(r => r.id === regId);
  if (idx !== -1) { regs[idx].status = status; LS.set('registrations', regs); return true; }
  return false;
}

function isUserRegistered(username, eventId) {
  const regs = LS.get('registrations') || [];
  return regs.find(r => r.username === username && r.eventId === eventId) || null;
}

// ── Seat availability helpers ───────────────────────────────
/**
 * Returns { capacity, booked, available, isFull, hasCapacity }
 * booked = all non-rejected registrations
 */
function getEventSeats(eventId, capacity) {
  const regs   = LS.get('registrations') || [];
  const booked = regs.filter(r => r.eventId === eventId && r.status !== 'rejected').length;
  const cap    = parseInt(capacity) || 0;
  const hasCapacity = cap > 0;
  const available   = hasCapacity ? Math.max(0, cap - booked) : null;
  const isFull      = hasCapacity && available === 0;
  return { capacity: cap, booked, available, isFull, hasCapacity };
}

/**
 * Renders a seat badge HTML string for event cards / detail pages.
 */
function seatBadge(eventId, capacity) {
  const { available, isFull, hasCapacity, booked } = getEventSeats(eventId, capacity);
  if (!hasCapacity) return '';
  if (isFull) {
    return `<span class="seat-badge seat-full"> Sold Out</span>`;
  }
  if (available <= 10) {
    return `<span class="seat-badge seat-low"> Only ${available} left!</span>`;
  }
  return `<span class="seat-badge seat-ok"> ${available} seats available</span>`;
}

// ── QR Code generator (pure JS, no external lib) ───────────
/**
 * Generates a simple visual QR-like ticket SVG.
 * Since a real QR encoder is complex, we use a deterministic
 * pixel pattern based on the data string to produce a
 * visually rich, unique ticket QR image.
 */
function generateQRSvg(data, size = 200) {
  // Simple deterministic hash → pixel grid
  const GRID = 21; // modules
  const cell = Math.floor(size / GRID);
  const actualSize = cell * GRID;

  // Create a stable bit array from data
  function hashStr(s) {
    let h = 0x811c9dc5;
    for (let i = 0; i < s.length; i++) {
      h ^= s.charCodeAt(i);
      h = (h * 0x01000193) >>> 0;
    }
    return h;
  }

  // Build module grid
  const modules = [];
  for (let r = 0; r < GRID; r++) {
    modules[r] = [];
    for (let c = 0; c < GRID; c++) {
      // Finder patterns (top-left, top-right, bottom-left)
      const inFinder = (
        (r < 7 && c < 7) ||
        (r < 7 && c >= GRID - 7) ||
        (r >= GRID - 7 && c < 7)
      );

      if (inFinder) {
        const dr = r < 7 ? r : r - (GRID - 7);
        const dc = c < 7 ? c : c - (GRID - 7);
        const maxD = Math.max(dr, dc);
        if (r >= GRID - 7 && c >= GRID - 7) {
          modules[r][c] = 0; // bottom-right: no finder
        } else if (maxD === 0 || maxD === 6) {
          modules[r][c] = 1;
        } else if (maxD >= 2 && maxD <= 4) {
          modules[r][c] = (maxD === 2 || maxD === 4) ? 0 : 1;
        } else {
          modules[r][c] = 0;
        }
      } else if (r === 6) {
        // Timing row
        modules[r][c] = c % 2 === 0 ? 1 : 0;
      } else if (c === 6) {
        // Timing col
        modules[r][c] = r % 2 === 0 ? 1 : 0;
      } else {
        // Data modules — deterministic from payload
        const seed = hashStr(data + r * 100 + c);
        modules[r][c] = (seed & 1);
      }
    }
  }

  // Build SVG rects
  let rects = '';
  for (let r = 0; r < GRID; r++) {
    for (let c = 0; c < GRID; c++) {
      if (modules[r][c]) {
        rects += `<rect x="${c * cell}" y="${r * cell}" width="${cell}" height="${cell}" fill="#000"/>`;
      }
    }
  }

  return `<svg xmlns="http://www.w3.org/2000/svg" width="${actualSize}" height="${actualSize}" viewBox="0 0 ${actualSize} ${actualSize}">
    <rect width="${actualSize}" height="${actualSize}" fill="#fff"/>
    ${rects}
  </svg>`;
}

/**
 * Returns a data URI for the QR SVG.
 */
function generateQRDataUri(data, size = 200) {
  const svg = generateQRSvg(data, size);
  return 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svg)));
}

// ── Notifications ───────────────────────────────────────────
function addNotification(username, message, type = 'info') {
  const notes = LS.get('notifications') || [];
  notes.unshift({ id: Date.now().toString(), username, message, type, read: false, at: new Date().toISOString() });
  LS.set('notifications', notes);
}

function getUserNotifications(username) {
  return (LS.get('notifications') || []).filter(n => n.username === username);
}

function markNotificationsRead(username) {
  const notes = LS.get('notifications') || [];
  notes.forEach(n => { if (n.username === username) n.read = true; });
  LS.set('notifications', notes);
}

// ── Inject logout button ────────────────────────────────────
function injectLogoutBtn(navId = 'nav-links') {
  const nav  = document.getElementById(navId);
  const user = getCurrentUser();
  if (!nav || !user) return;
  const li1 = document.createElement('li');
  li1.innerHTML = `<span style="color:var(--accent2)"> ${user.username}</span>`;
  nav.appendChild(li1);
  const li2 = document.createElement('li');
  li2.innerHTML = `<button class="btn btn-outline btn-sm" onclick="logout()">Logout</button>`;
  nav.appendChild(li2);
}