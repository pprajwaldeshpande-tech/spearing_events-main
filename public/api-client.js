(() => {
  const state = { user: null, csrf: null, events: [], bookings: [], notifications: [], users: [] };
  const api = async (path, options = {}) => {
    const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
    const headers = { Accept: 'application/json', ...(options.body && !isFormData ? { 'Content-Type': 'application/json' } : {}), ...(options.headers || {}) };
    if (!['GET', 'HEAD'].includes(options.method || 'GET') && state.csrf) headers['X-CSRF-Token'] = state.csrf;
    const response = await fetch(`./api${path}`, { credentials: 'same-origin', ...options, headers });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || !payload.success) throw new Error(payload.error?.message || 'Request failed.');
    return payload.data;
  };
  const loadUserData = async () => {
    if (!state.user) return;
    const [bookingData, notificationData] = await Promise.all([api('/me/bookings'), api('/notifications')]);
    state.bookings = bookingData.bookings.map(toLegacyBooking);
    state.notifications = notificationData.notifications;
    if (state.user.role === 'admin') {
      const [users, bookings] = await Promise.all([api('/admin/users'), api('/admin/bookings')]);
      state.users = users.users;
      state.bookings = bookings.bookings.map(toLegacyBooking);
    }
  };
  const syncState = async () => {
    const session = await api('/auth/me');
    state.user = session.user;
    state.csrf = session.csrfToken;
    await loadUserData();
  };
  const toLegacyBooking = booking => ({ ...booking, username: booking.username || state.user?.username, eventId: String(booking.event_id), eventTitle: booking.title, amount: Number(booking.total_amount), paymentId: booking.id, bookingStatus: booking.status, paymentReference: booking.payment_reference || null, paidAt: booking.created_at, status: booking.status === 'confirmed' ? 'approved' : booking.status === 'cancelled' ? 'rejected' : 'pending' });
  const refreshEvents = async () => { state.events = (await api('/events?limit=50')).events; return state.events; };
  const authReady = api('/auth/me')
    .then(session => {
      state.user = session.user;
      state.csrf = session.csrfToken;
      return session.user;
    })
    .catch(error => { console.error(error); return null; });
  window.EventSpot = {
    state, api, authReady, ready: Promise.all([authReady.then(loadUserData), refreshEvents()]).catch(error => { console.error(error); return null; }),
    async refreshSession() {
      const session = await api('/auth/me');
      state.user = session.user;
      state.csrf = session.csrfToken;
      return state.user;
    },
    async login(identifier, password) { const data = await api('/auth/login', { method: 'POST', body: JSON.stringify({ identifier, password }) }); state.user = data.user; state.csrf = data.csrfToken; return data.user; },
    async register(fullName, username, email, password, termsAccepted) { const data = await api('/auth/register', { method: 'POST', body: JSON.stringify({ fullName, username, email, password, termsAccepted }) }); state.user = data.user; state.csrf = data.csrfToken; return data.user; },
    async requestPasswordReset(email) { return api('/auth/forgot-password', { method: 'POST', body: JSON.stringify({ email }) }); },
    async resetPassword(token, password) { return api('/auth/reset-password', { method: 'POST', body: JSON.stringify({ token, password }) }); },
    async logout() { await api('/auth/logout', { method: 'POST', body: '{}' }); state.user = null; state.csrf = null; location.href = 'login.html'; },
    async refresh() { await syncState(); await refreshEvents(); },
    async getEvent(id) { return (await api(`/events/${encodeURIComponent(id)}`)).event; },
    async adminEvents() { return (await api('/admin/events')).events; },
    async uploadEventImages(id, files) {
      const form = new FormData();
      Array.from(files).forEach(file => form.append('images[]', file));
      return api(`/admin/events/${encodeURIComponent(id)}/image`, { method: 'POST', body: form });
    },
    async createBooking(eventId, quantity = 1) { const key = crypto.randomUUID(); return api('/bookings', { method: 'POST', headers: { 'Idempotency-Key': key }, body: JSON.stringify({ eventId, quantity }) }); },
    async paymentOrder(bookingId) { return api('/payments/orders', { method: 'POST', body: JSON.stringify({ bookingId }) }); },
    async submitUpiPayment(bookingId, transactionReference) { return api('/payments/upi-submissions', { method: 'POST', body: JSON.stringify({ bookingId, transactionReference }) }); },
    async verifyPayment(payment) { return api('/payments/verify', { method: 'POST', body: JSON.stringify(payment) }); },
    async getWishlist() { return (await api('/wishlists')).eventIds; },
    async addWishlist(eventId) { return api('/wishlists', { method: 'POST', body: JSON.stringify({ eventId }) }); },
    async removeWishlist(eventId) { return api(`/wishlists/${encodeURIComponent(eventId)}`, { method: 'DELETE', body: '{}' }); },
    async getReviews(eventId) { return (await api(`/reviews?eventId=${encodeURIComponent(eventId)}`)).reviews; },
    async submitReview(eventId, rating, body) { return api('/reviews', { method: 'POST', body: JSON.stringify({ eventId, rating, body }) }); }
  };
  LS.get = key => ({ currentUser: state.user, cachedEvents: state.events, registrations: state.bookings, notifications: state.notifications, users: state.users }[key] || null);
  LS.set = () => {};
  LS.remove = () => {};
  window.getCurrentUser = () => state.user;
  window.isLoggedIn = () => !!state.user;
  window.logout = () => window.EventSpot.logout().catch(() => { location.href = 'login.html'; });
  window.requireAuth = role => { if (!state.user) { location.href = 'login.html'; return false; } if (role && state.user.role !== role) { location.href = state.user.role === 'admin' ? 'admin.html' : 'user.html'; return false; } return true; };
  window.fetchEvents = async () => { await window.EventSpot.ready; return state.events; };
  window.getUserRegistrations = username => state.bookings.filter(booking => booking.username === username || booking.user_id === state.user?.id);
  window.getUserNotifications = () => state.notifications.map(notification => ({ ...notification, at: notification.created_at, read: !!notification.read_at }));
  window.markNotificationsRead = async () => { await api('/notifications/read-all', { method: 'POST', body: '{}' }); state.notifications = state.notifications.map(notification => ({ ...notification, read_at: notification.read_at || new Date().toISOString(), read: true })); };
  const eventPayload = payload => ({ title: payload.title, description: payload.description || '', category: payload.category, venue: payload.location, venueDetails: payload.venueDetails || '', startsAt: payload.date, price: payload.price || 0, capacity: payload.capacity || 1, latitude: payload.latitude || null, longitude: payload.longitude || null, status: payload.status || 'published' });
  window.addEventAPI = async payload => { const data = await api('/admin/events', { method: 'POST', body: JSON.stringify(eventPayload(payload)) }); state.events = (await api('/events?limit=50')).events; const events = await api('/admin/events'); return { success: true, event: events.events.find(event => event.id === String(data.eventId)) }; };
  window.editEventAPI = async (id, payload) => { const data = await api(`/admin/events/${encodeURIComponent(id)}`, { method: 'PUT', body: JSON.stringify(eventPayload(payload)) }); state.events = (await api('/events?limit=50')).events; const events = await api('/admin/events'); return { success: true, event: events.events.find(event => event.id === String(data.eventId)) }; };
  window.deleteEventAPI = async id => { await api(`/admin/events/${id}/archive`, { method: 'POST', body: '{}' }); state.events = (await api('/admin/events')).events; return { success: true }; };
})();