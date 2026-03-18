export async function getCommunications(patientId, token) {
  const headers = {};
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const res = await fetch(`/api/patients/${patientId}/communications`, { headers });
  if (!res.ok) throw new Error(`Failed to load communications (${res.status})`);
  return await res.json();
}

export async function sendCommunication(patientId, payload, token) {
  const headers = { 'Content-Type': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const res = await fetch(`/api/patients/${patientId}/communications`, {
    method: 'POST',
    headers,
    body: JSON.stringify(payload)
  });

  if (!res.ok) {
    const text = await res.text();
    throw new Error(`Send failed: ${res.status} ${text}`);
  }

  return await res.json();
}
