const defaultConfig = {
  apiBaseUrl: 'https://gestionemployerbackend.onrender.com/api/v1',
  deviceCode: 'KIOSK001',
  companyName: 'Leopardo RH Client',
  locationLabel: 'Entree principale',
  defaultAction: 'check_in',
};

const state = {
  config: { ...defaultConfig },
};

const companyNameEl = document.getElementById('companyName');
const locationLabelEl = document.getElementById('locationLabel');
const deviceCodeEl = document.getElementById('deviceCode');
const identifierInput = document.getElementById('identifier');
const statusBox = document.getElementById('statusBox');
const checkInButton = document.getElementById('checkInButton');
const checkOutButton = document.getElementById('checkOutButton');

async function boot() {
  try {
    const response = await fetch('./config.json');
    if (response.ok) {
      state.config = { ...state.config, ...(await response.json()) };
    }
  } catch (_) {
  }

  companyNameEl.textContent = state.config.companyName;
  locationLabelEl.textContent = state.config.locationLabel;
  deviceCodeEl.textContent = state.config.deviceCode;
  identifierInput.focus();
}

function setStatus(message, isError = false) {
  statusBox.textContent = message;
  statusBox.classList.toggle('error', isError);
}

async function submitPunch(action) {
  const identifier = identifierInput.value.trim();
  if (!identifier) {
    setStatus('Veuillez saisir ou scanner un identifiant employe.', true);
    return;
  }

  setStatus('Transmission du pointage en cours...');

  try {
    const response = await fetch(`${state.config.apiBaseUrl}/kiosks/${state.config.deviceCode}/punch`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ identifier, action }),
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(payload.message || payload.error || 'Echec de pointage');
    }

    const employeeId = payload?.data?.employee_id ?? '?';
    const method = payload?.data?.method ?? 'kiosk';
    setStatus(`Pointage enregistre pour employe #${employeeId} via ${method}.`);
    identifierInput.value = '';
    identifierInput.focus();
  } catch (error) {
    setStatus(error.message || 'Impossible de joindre Leopardo RH.', true);
  }
}

checkInButton.addEventListener('click', () => submitPunch('check_in'));
checkOutButton.addEventListener('click', () => submitPunch('check_out'));
identifierInput.addEventListener('keydown', (event) => {
  if (event.key === 'Enter') {
    event.preventDefault();
    submitPunch(state.config.defaultAction || 'check_in');
  }
});

window.ZKTecoBridge = {
  submitIdentifier(value, action = state.config.defaultAction || 'check_in') {
    identifierInput.value = value || '';
    return submitPunch(action);
  },
  fillIdentifier(value) {
    identifierInput.value = value || '';
    identifierInput.focus();
  },
};

boot();
