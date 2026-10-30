(() => {
  const QUICK_PROMPTS = [
    'Best events this weekend',
    'Events under ₹500',
    'Music events',
    'Free events'
  ];

  const money = value => {
    const price = Number(value) || 0;
    return price === 0 ? 'Free' : `₹${price.toLocaleString('en-IN')}`;
  };

  const eventDate = event => new Date(event.starts_at || event.date || event.startsAt);
  const eventPrice = event => Number(event.ticket_price ?? event.price ?? 0);
  const eventName = event => event.title || 'Untitled event';
  const eventVenue = event => event.venue || event.location || 'Venue to be confirmed';
  const eventCategory = event => event.category || 'Other';

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function createAssistant() {
    if (document.getElementById('event-assistant')) return;
    const assistant = document.createElement('aside');
    assistant.id = 'event-assistant';
    assistant.className = 'event-assistant';
    assistant.innerHTML = `
      <button type="button" id="event-assistant-toggle" class="event-assistant-toggle" aria-expanded="false" aria-controls="event-assistant-panel">
        <span aria-hidden="true">AI</span>
        <span>Event assistant</span>
      </button>
      <section id="event-assistant-panel" class="event-assistant-panel" aria-label="Event assistant">
        <header class="event-assistant-header">
          <div>
            <strong>Event Assistant</strong>
            <small>Find events by date, price, category, or venue</small>
          </div>
          <button type="button" id="event-assistant-close" aria-label="Close assistant">&times;</button>
        </header>
        <div id="event-assistant-messages" class="event-assistant-messages" aria-live="polite"></div>
        <div class="event-assistant-prompts">
          ${QUICK_PROMPTS.map(prompt => `<button type="button" data-assistant-prompt="${escapeHtml(prompt)}">${escapeHtml(prompt)}</button>`).join('')}
        </div>
        <form id="event-assistant-form" class="event-assistant-form">
          <input id="event-assistant-input" autocomplete="off" maxlength="180" placeholder="Ask about events, dates, or prices…" />
          <button type="submit" aria-label="Send">→</button>
        </form>
      </section>`;
    document.body.appendChild(assistant);

    const toggle = document.getElementById('event-assistant-toggle');
    const close = document.getElementById('event-assistant-close');
    const panel = document.getElementById('event-assistant-panel');
    const openPanel = () => {
      panel.classList.add('open');
      toggle.setAttribute('aria-expanded', 'true');
      document.getElementById('event-assistant-input').focus();
    };
    const closePanel = () => {
      panel.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    };
    toggle.addEventListener('click', () => panel.classList.contains('open') ? closePanel() : openPanel());
    close.addEventListener('click', closePanel);
    document.getElementById('event-assistant-form').addEventListener('submit', event => {
      event.preventDefault();
      const input = document.getElementById('event-assistant-input');
      const question = input.value.trim();
      if (!question) return;
      input.value = '';
      answerQuestion(question);
    });
    document.querySelectorAll('[data-assistant-prompt]').forEach(button => {
      button.addEventListener('click', () => answerQuestion(button.dataset.assistantPrompt));
    });

    addMessage('assistant', 'Hello! Tell me your preferred date, category, venue, or maximum budget. I’ll suggest the best available events.');
  }

  let eventCatalog = null;

  async function loadEvents() {
    if (eventCatalog) return eventCatalog;
    try {
      await (window.EventSpot?.ready || Promise.resolve());
      if (typeof fetchEvents === 'function') {
        eventCatalog = await fetchEvents();
      } else {
        const response = await fetch('./api/events?limit=50');
        const payload = await response.json();
        eventCatalog = payload.data?.events || [];
      }
    } catch (_) {
      eventCatalog = [];
    }
    return eventCatalog;
  }

  function addMessage(type, content) {
    const messages = document.getElementById('event-assistant-messages');
    const message = document.createElement('div');
    message.className = `event-assistant-message ${type}`;
    message.innerHTML = content;
    messages.appendChild(message);
    messages.scrollTop = messages.scrollHeight;
  }

  function dateMatches(text, date) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (/today|tonight/.test(text)) return date.toDateString() === today.toDateString();
    if (/tomorrow/.test(text)) {
      const tomorrow = new Date(today);
      tomorrow.setDate(tomorrow.getDate() + 1);
      return date.toDateString() === tomorrow.toDateString();
    }
    if (/weekend/.test(text)) {
      const saturday = new Date(today);
      saturday.setDate(today.getDate() + ((6 - today.getDay() + 7) % 7));
      const sunday = new Date(saturday);
      sunday.setDate(saturday.getDate() + 1);
      return date >= saturday && date <= new Date(sunday.setHours(23, 59, 59, 999));
    }
    const monthMatch = text.match(/\b(january|february|march|april|may|june|july|august|september|october|november|december)\b/i);
    if (monthMatch) return date.toLocaleString('en-IN', { month: 'long' }).toLowerCase() === monthMatch[1].toLower();
    return true;
  }

  async function answerQuestion(question) {
    addMessage('user', escapeHtml(question));
    addMessage('assistant', '<span class="event-assistant-loading">Finding the best matches…</span>');
    const events = await loadEvents();
    const text = question.toLower().trim();

    let matches = events.filter(event => {
      const date = eventDate(event);
      return !Number.isNaN(date.getTime()) && date >= new Date() && dateMatches(text, date);
    });

    const categories = [...new Set(matches.map(eventCategory).map(value => value.toLowerCase()))];
    const category = categories.find(value => text.includes(value));
    if (category) matches = matches.filter(event => eventCategory(event).toLowerCase() === category);

    const budgetMatch = text.match(/(?:under|below|less than|max(?:imum)?|upto|up to)\s*(?:₹|rs\.?\s*)?(\d+)/i)
      || text.match(/₹\s*(\d+)/);
    if (budgetMatch) {
      const budget = Number(budgetMatch[1]);
      matches = matches.filter(event => eventPrice(event) <= budget);
    } else if (/\bfree\b/.test(text)) {
      matches = matches.filter(event => eventPrice(event) === 0);
    }

    if (/cheap|budget|affordable/.test(text)) {
      matches.sort((a, b) => eventPrice(a) - eventPrice(b));
    } else {
      matches.sort((a, b) => eventDate(a) - eventDate(b));
    }

    if (!events.length) {
      updateLastMessage('I could not load events right now. Please refresh the page and try again.');
      return;
    }
    if (!matches.length) {
      updateLastMessage('I couldn’t find an exact match. Try a broader date, another category, or a higher budget.');
      return;
    }

    const cards = matches.slice(0, 5).map(event => {
      const date = eventDate(event).toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' });
      const detailPage = `event.html?id=${encodeURIComponent(event.id)}`;
      return `<a class="event-assistant-event" href="${detailPage}">
        <strong>${escapeHtml(eventName(event))}</strong>
        <span>${escapeHtml(date)} · ${escapeHtml(eventVenue(event))}</span>
        <b>${money(eventPrice(event))}</b>
      </a>`;
    }).join('');
    updateLastMessage(`I found ${matches.length} match${matches.length === 1 ? '' : 'es'}. Best suggestions:<div class="event-assistant-results">${cards}</div>`);
  }

  function updateLastMessage(content) {
    const messages = document.getElementById('event-assistant-messages');
    const loading = messages.querySelector('.event-assistant-loading')?.closest('.event-assistant-message');
    if (loading) loading.innerHTML = content;
    messages.scrollTop = messages.scrollHeight;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', createAssistant);
  } else {
    createAssistant();
  }
})();
