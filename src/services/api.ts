/**
 * SmartRecur WP REST client.
 *
 * Auth + identity come from the host WordPress page via `window.smartrecurData`,
 * which the plugin localizes onto the bundle. JWT tokens, localStorage, and
 * password-handling have been removed; the user is already authenticated when
 * the React app boots, and every state-changing request carries an X-WP-Nonce.
 */

interface SmartRecurBootstrap {
  restUrl: string;
  nonce: string;
  userId: number;
  userName: string;
  userCaps: {
    manage: boolean;
    book: boolean;
    view: boolean;
    manage_clients: boolean;
  };
  timezone: string;
  locale: string;
  loginUrl: string;
  logoutUrl: string;
  siteUrl: string;
  pluginUrl: string;
  version: string;
}

declare global {
  interface Window {
    smartrecurData?: SmartRecurBootstrap;
  }
}

const bootstrap = (): SmartRecurBootstrap => {
  if (!window.smartrecurData) {
    throw new Error(
      'SmartRecur bootstrap data missing — the plugin did not localize smartrecurData. Reload the page.',
    );
  }
  return window.smartrecurData;
};

const data = bootstrap();

class ApiClient {
  private readonly base = data.restUrl.replace(/\/$/, '') + '/';
  private readonly nonce = data.nonce;

  private async request<T = any>(method: string, path: string, body?: any): Promise<T> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-WP-Nonce': this.nonce,
    };

    const url = this.base + path.replace(/^\//, '');
    const res = await fetch(url, {
      method,
      headers,
      credentials: 'same-origin',
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    if (res.status === 401 || res.status === 403) {
      window.location.href = data.loginUrl;
      throw new Error('Session expired');
    }

    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      throw new Error(`Server returned non-JSON response (${res.status}).`);
    }

    const payload = await res.json();
    if (!res.ok) {
      const message = payload?.message || payload?.error || `Request failed (${res.status})`;
      throw new Error(message);
    }
    return payload;
  }

  // ── Identity (replaces JWT login) ───────────────────────

  /** Returns true if the WordPress session bootstrapped a logged-in user. */
  hasToken(): boolean {
    return data.userId > 0;
  }

  /** Stub for backward compatibility — real auth happens in wp-login.php. */
  setToken(): void {
    /* noop */
  }

  /** Redirect to wp-login.php?action=logout. */
  clearToken(): void {
    window.location.href = data.logoutUrl;
  }

  /** Current user descriptor, sourced from the bootstrap. */
  getMe() {
    return Promise.resolve({
      user: {
        id: data.userId,
        username: data.userName,
        capabilities: data.userCaps,
      },
    });
  }

  // ── Appointments / events ───────────────────────────────

  getEvents() {
    return this.request<{ events: any[] }>('GET', 'appointments');
  }

  createEvent(event: any) {
    return this.request<{ event: any }>('POST', 'appointments', event);
  }

  updateEvent(id: string, event: any) {
    return this.request('PUT', `appointments/${id}`, event);
  }

  deleteEvent(id: string) {
    return this.request('DELETE', `appointments/${id}`);
  }

  // ── Clients (legacy name "customers" kept for component compatibility) ──

  getCustomers() {
    return this.request<{ customers: any[] }>('GET', 'clients');
  }

  createCustomer(customer: any) {
    return this.request<{ customer: { id: string } }>('POST', 'clients', customer);
  }

  updateCustomer(id: string, customer: any) {
    return this.request('PUT', `clients/${id}`, customer);
  }

  deleteCustomer(id: string) {
    return this.request('DELETE', `clients/${id}`);
  }

  // ── Services ────────────────────────────────────────────

  getServices() {
    return this.request<{ services: any[] }>('GET', 'services');
  }

  createService(service: any) {
    return this.request<{ service: { id: string } }>('POST', 'services', service);
  }

  updateService(id: string, service: any) {
    return this.request('PUT', `services/${id}`, service);
  }

  deleteService(id: string) {
    return this.request('DELETE', `services/${id}`);
  }

  // ── Technicians ─────────────────────────────────────────

  getTechnicians() {
    return this.request<{ technicians: any[] }>('GET', 'technicians');
  }

  createTechnician(tech: any) {
    return this.request<{ technician: { id: string } }>('POST', 'technicians', tech);
  }

  updateTechnician(id: string, tech: any) {
    return this.request('PUT', `technicians/${id}`, tech);
  }

  deleteTechnician(id: string) {
    return this.request('DELETE', `technicians/${id}`);
  }

  // ── Settings ────────────────────────────────────────────

  getSettings() {
    return this.request<{ settings: Record<string, any> }>('GET', 'settings');
  }

  updateSettings(settings: Record<string, any>) {
    return this.request('PUT', 'settings', settings);
  }

  exportBackup() {
    return this.request('GET', 'settings/backup');
  }

  importBackup(payload: any) {
    return this.request('POST', 'settings/restore', payload);
  }

  // ── Integrations ────────────────────────────────────────

  getIntegrationStatus() {
    return this.request<{
      integrations: Record<
        string,
        { isConnected: boolean; lastChecked: string | null; config: Record<string, any> }
      >;
    }>('GET', 'integrations/status');
  }

  saveIntegrationConfig(type: string, config: Record<string, any>) {
    return this.request('PUT', `integrations/${type}/config`, config);
  }

  testIntegration(type: string) {
    return this.request<{ isConnected: boolean; message: string }>(
      'POST',
      `integrations/${type}/test`,
    );
  }

  getOffice365AuthUrl() {
    return this.request<{ url: string }>('POST', 'integrations/office365/connect');
  }

  disconnectOffice365() {
    return this.request<{ success: boolean }>('POST', 'integrations/office365/disconnect');
  }

  getOffice365Calendars() {
    return this.request<{ calendars: Array<{ id: string; name: string }> }>(
      'GET',
      'integrations/office365/calendars',
    );
  }

  createOffice365Event(payload: {
    calendarId?: string;
    subject: string;
    description: string;
    startDateTime: string;
    endDateTime: string;
    timeZone?: string;
  }) {
    return this.request<{ success: boolean; eventId?: string }>(
      'POST',
      'integrations/office365/sync',
      payload,
    );
  }

  syncroImportCustomers() {
    return this.request<{ customers: any[]; total: number }>(
      'POST',
      'integrations/syncro/import',
    );
  }

  syncroCreateTicket(payload: { customerId: string; subject: string; description: string }) {
    return this.request<{ ticketId: string }>('POST', 'integrations/syncro/tickets', payload);
  }

  invoiceNinjaSyncCustomers() {
    return this.request<{ imported: number; total: number; message: string }>(
      'POST',
      'integrations/invoiceninja/import',
    );
  }

  // ── Email ──────────────────────────────────────────────

  sendEmail(payload: { to: string; subject: string; body: string }) {
    return this.request<{ success: boolean; method: string }>(
      'POST',
      'integrations/email/send',
      payload,
    );
  }

  getEmailLogs() {
    return this.request<{
      logs: Array<{
        id: string;
        recipient: string;
        subject: string;
        status: string;
        method: string;
        error: string | null;
        created_at: string;
      }>;
    }>('GET', 'email-logs');
  }

  // ── Push (no-op — handled at the WP plugin layer if ever needed) ──

  getPushConfig() {
    return Promise.resolve({ vapidPublicKey: '', enabled: false, timings: [] });
  }

  registerPushSubscription(_subscription: any) {
    return Promise.resolve({ success: false });
  }

  unregisterPushSubscription(_endpoint: string) {
    return Promise.resolve({ success: false });
  }

  testPushNotification() {
    return Promise.resolve({ success: false });
  }
}

export const api = new ApiClient();
export const smartrecurBootstrap = data;
