import { getCommunications, sendCommunication } from './api.js';

let patient = null;
let care = [];
let currentUser = null;

const threadEl = document.getElementById('thread');
const ct = document.getElementById('careTeam');

async function fetchInitialData() {
  if (window.__INITIAL_DATA__) return window.__INITIAL_DATA__;
  try {
    const res = await fetch('/api/initialdata');
    if (!res.ok) throw new Error('failed to fetch initial data');
    return await res.json();
  } catch (e) {
    console.warn('Could not load initial data, falling back to empty defaults', e);
    return { patient: null, care: [], currentUser: null };
  }
}

function applyInitialData(data) {
  patient = data.patient || { id: 'p123', name: '—', mrn: '—', encounter: '—' };
  care = data.care || [];
  currentUser = data.currentUser || { id: null, name: 'You', role: 'clinician', token: '' };

  document.getElementById('patientName').textContent = patient.name || '—';
  document.getElementById('mrn').textContent = patient.mrn || '—';
  document.getElementById('enc').textContent = patient.encounter || '—';

  ct.innerHTML = '';
  care.forEach(c => { const li = document.createElement('li'); li.textContent = c; ct.appendChild(li); });
}

let messages = [
  { id: 1, from: 'Dr. Smith', role: 'clinician', text: 'Reviewed intake — patient reported increased anxiety.', ts: '2026-02-01T09:12Z' },
  { id: 2, from: 'Nurse Lee', role: 'nurse', text: 'Medication adjusted, follow-up in 48h.', ts: '2026-02-01T10:05Z' }
];

function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); }

function renderMessages() {
  threadEl.innerHTML = '';
  messages.forEach(m => {
    const div = document.createElement('div');
    div.className = 'message ' + (m.from === currentUser?.name ? 'me' : 'them');
    div.innerHTML = `<div><strong>${escapeHtml(m.from)}</strong> <span class="meta">• ${new Date(m.ts).toLocaleString()}</span></div>` +
                    `<div>${escapeHtml(m.text)}</div>`;
    threadEl.appendChild(div);
  });
  threadEl.scrollTop = threadEl.scrollHeight;
}

async function sendCurrentMessage() {
  const textEl = document.getElementById('messageInput');
  const text = textEl.value.trim();
  const urgency = document.getElementById('urgency').value;
  if (!text) return;

  const payload = {
    resourceType: 'Communication',
    status: 'completed',
    category: [{ coding: [{ system: 'http://terminology.hl7.org/CodeSystem/communication-category', code: urgency }] }],
    subject: { reference: `Patient/${patient.id}` },
    sent: new Date().toISOString(),
    payload: [{ contentString: text }],
    sender: { reference: `Practitioner/${currentUser.id}`, display: currentUser.name }
  };

  const optimistic = { id: Date.now(), from: currentUser?.name || 'You', role: currentUser?.role || 'clinician', text, ts: new Date().toISOString() };
  messages.push(optimistic);
  renderMessages();
  textEl.value = '';

  try {
    const created = await sendCommunication(patient.id, payload, currentUser?.token);
    const textFromPayload = (created.payload && created.payload[0] && created.payload[0].contentString) || text;
    const fromDisplay = (created.sender && created.sender.display) || currentUser?.name || 'You';
    messages = messages.map(m => m.id === optimistic.id ? { ...m, id: created.id || m.id, from: fromDisplay, text: textFromPayload, ts: created.sent || m.ts } : m);
    renderMessages();
  } catch (err) {
    console.warn('send failed, keeping optimistic message', err);
  }
}

document.getElementById('sendBtn').addEventListener('click', () => sendCurrentMessage());

// allow Enter to send, Shift+Enter to insert newline
const messageInputEl = document.getElementById('messageInput');
messageInputEl.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    // call send but don't block the key handler
    sendCurrentMessage();
  }
});

async function loadMessages() {
  try {
    const data = await getCommunications(patient.id, currentUser?.token);
    messages = data.map(c => ({
      id: c.id || Date.now(),
      from: (c.sender && c.sender.display) || (c.from && c.from.display) || (c.participant && c.participant[0] && c.participant[0].display) || 'Unknown',
      role: (c.sender && c.sender.role) || 'clinician',
      text: (c.payload && c.payload[0] && c.payload[0].contentString) || '',
      ts: c.sent || c.authored || new Date().toISOString()
    }));
  } catch (e) {
    console.warn('Could not load messages, using mock store', e);
  }
  renderMessages();
}

async function init() {
  const data = await fetchInitialData();
  applyInitialData(data);
  await loadMessages();
}

init();
