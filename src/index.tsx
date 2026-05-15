import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import './styles.css';

type SupportedView = 'calendar' | 'booking' | 'admin' | 'dashboard' | 'clients';

const mountAll = () => {
  const containers = document.querySelectorAll<HTMLDivElement>('#smartrecur-app, .smartrecur-app');
  containers.forEach((el) => {
    if (el.dataset.smartrecurMounted === '1') return;
    el.dataset.smartrecurMounted = '1';

    const rawView = (el.dataset.view as SupportedView) || 'calendar';
    const view: SupportedView = ['calendar', 'booking', 'admin', 'dashboard', 'clients'].includes(rawView)
      ? rawView
      : 'calendar';
    const clientId = el.dataset.clientId || '';

    ReactDOM.createRoot(el).render(
      <React.StrictMode>
        <App initialView={view} clientId={clientId} />
      </React.StrictMode>,
    );
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountAll);
} else {
  mountAll();
}
