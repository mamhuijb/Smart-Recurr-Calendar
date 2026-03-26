/**
 * Lightweight toast notification utility.
 * Renders non-blocking notifications without external dependencies.
 */

type ToastType = 'success' | 'error' | 'info';

let container: HTMLDivElement | null = null;

function getContainer(): HTMLDivElement {
  if (container && document.body.contains(container)) return container;
  container = document.createElement('div');
  container.id = 'sr-toast-container';
  container.style.cssText = 'position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
  document.body.appendChild(container);
  return container;
}

function show(message: string, type: ToastType = 'info', durationMs = 4000): void {
  const el = document.createElement('div');
  const colors: Record<ToastType, string> = {
    success: 'background:#065f46;color:#d1fae5;',
    error: 'background:#991b1b;color:#fee2e2;',
    info: 'background:#1e3a5f;color:#dbeafe;',
  };
  el.style.cssText = `${colors[type]}padding:10px 16px;border-radius:12px;font-size:13px;font-family:system-ui,sans-serif;max-width:360px;box-shadow:0 4px 12px rgba(0,0,0,.25);pointer-events:auto;opacity:0;transform:translateX(20px);transition:all .3s ease;`;
  el.textContent = message;
  getContainer().appendChild(el);

  requestAnimationFrame(() => {
    el.style.opacity = '1';
    el.style.transform = 'translateX(0)';
  });

  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transform = 'translateX(20px)';
    setTimeout(() => el.remove(), 300);
  }, durationMs);
}

export const toast = {
  success: (msg: string) => show(msg, 'success'),
  error: (msg: string) => show(msg, 'error', 6000),
  info: (msg: string) => show(msg, 'info'),
};
