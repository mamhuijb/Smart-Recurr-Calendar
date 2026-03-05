/**
 * SmartRecur API Client
 * Handles all communication with the PHP/MariaDB backend.
 * JWT token is stored in localStorage for persistence across page reloads.
 */

const API_BASE = '/api';
const TOKEN_KEY = 'sr_token';
const SESSION_TIMESTAMP_KEY = 'sr_session_ts';
const SESSION_MAX_AGE_MS = 24 * 60 * 60 * 1000; // 1 day

class ApiClient {
  private token: string | null = null;

  constructor() {
    this.token = localStorage.getItem(TOKEN_KEY);

    // Check if session has expired on load
    if (this.token) {
      const ts = parseInt(localStorage.getItem(SESSION_TIMESTAMP_KEY) || '0', 10);
      if (ts && Date.now() - ts > SESSION_MAX_AGE_MS) {
        this.clearToken();
        window.location.reload();
      }
    }
  }

  // ── Core request method ─────────────────────────────────

  private async request<T = any>(method: string, path: string, body?: any): Promise<T> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    // Method override: send PUT/DELETE as POST with X-HTTP-Method-Override header.
    // This bypasses ModSecurity/WAF on Plesk/CloudLinux that blocks PUT/DELETE with 403.
    let actualMethod = method;
    if (method === 'PUT' || method === 'DELETE') {
      actualMethod = 'POST';
      headers['X-HTTP-Method-Override'] = method;
    }

    const res = await fetch(`${API_BASE}${path}`, {
      method: actualMethod,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    if (res.status === 401) {
      this.clearToken();
      window.location.reload();
      throw new Error('Session expired');
    }

    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      throw new Error(`Server returned non-JSON response (${res.status}). Check API routing.`);
    }

    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || `Request failed (${res.status})`);
    }

    return data;
  }

  // ── Token management ────────────────────────────────────

  setToken(token: string): void {
    this.token = token;
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(SESSION_TIMESTAMP_KEY, String(Date.now()));
  }

  clearToken(): void {
    this.token = null;
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(SESSION_TIMESTAMP_KEY);
  }

  hasToken(): boolean {
    return !!this.token;
  }

  // ── Auth ────────────────────────────────────────────────

  login(username: string, password: string) {
    return this.request<{
      token?: string;
      requires_2fa?: boolean;
      temp_token?: string;
      user?: { id: number; username: string };
    }>('POST', '/auth/login', { username, password });
  }

  verify2FA(tempToken: string, code: string) {
    return this.request<{
      token: string;
      user: { id: number; username: string };
    }>('POST', '/auth/verify-2fa', { temp_token: tempToken, code });
  }

  getMe() {
    return this.request<{ user: { id: number; username: string; two_factor_enabled: boolean } }>('GET', '/auth/me');
  }

  // ── Events ──────────────────────────────────────────────

  getEvents() {
    return this.request<{ events: any[] }>('GET', '/events');
  }

  createEvent(event: any) {
    return this.request<{ event: any }>('POST', '/events', event);
  }

  updateEvent(id: string, event: any) {
    return this.request('PUT', `/events/${id}`, event);
  }

  deleteEvent(id: string) {
    return this.request('DELETE', `/events/${id}`);
  }

  // ── Customers ───────────────────────────────────────────

  getCustomers() {
    return this.request<{ customers: any[] }>('GET', '/customers');
  }

  createCustomer(customer: any) {
    return this.request<{ customer: { id: string } }>('POST', '/customers', customer);
  }

  updateCustomer(id: string, customer: any) {
    return this.request('PUT', `/customers/${id}`, customer);
  }

  deleteCustomer(id: string) {
    return this.request('DELETE', `/customers/${id}`);
  }

  // ── Services ────────────────────────────────────────────

  getServices() {
    return this.request<{ services: any[] }>('GET', '/services');
  }

  createService(service: any) {
    return this.request<{ service: { id: string } }>('POST', '/services', service);
  }

  updateService(id: string, service: any) {
    return this.request('PUT', `/services/${id}`, service);
  }

  deleteService(id: string) {
    return this.request('DELETE', `/services/${id}`);
  }

  // ── Technicians ─────────────────────────────────────────

  getTechnicians() {
    return this.request<{ technicians: any[] }>('GET', '/technicians');
  }

  createTechnician(tech: any) {
    return this.request<{ technician: { id: string } }>('POST', '/technicians', tech);
  }

  updateTechnician(id: string, tech: any) {
    return this.request('PUT', `/technicians/${id}`, tech);
  }

  deleteTechnician(id: string) {
    return this.request('DELETE', `/technicians/${id}`);
  }

  // ── Settings ────────────────────────────────────────────

  getSettings() {
    return this.request<{ settings: Record<string, any> }>('GET', '/settings');
  }

  updateSettings(settings: Record<string, any>) {
    return this.request('PUT', '/settings', settings);
  }

  exportBackup() {
    return this.request('GET', '/settings/backup');
  }

  importBackup(data: any) {
    return this.request('POST', '/settings/restore', data);
  }

  // ── Integrations ────────────────────────────────────────

  getIntegrationStatus() {
    return this.request<{
      integrations: Record<string, {
        isConnected: boolean;
        lastChecked: string | null;
        config: Record<string, any>;
      }>;
    }>('GET', '/integrations/status');
  }

  saveIntegrationConfig(type: string, config: Record<string, any>) {
    return this.request('PUT', `/integrations/${type}/config`, config);
  }

  testIntegration(type: string) {
    return this.request<{ isConnected: boolean; message: string }>('POST', `/integrations/${type}/test`);
  }

  getOffice365AuthUrl() {
    return this.request<{ url: string }>('GET', '/integrations/office365/auth-url');
  }

  syncroImportCustomers() {
    return this.request<{ customers: any[] }>('GET', '/integrations/syncro/customers');
  }

  syncroCreateTicket(data: { customerId: string; subject: string; description: string }) {
    return this.request<{ ticketId: string }>('POST', '/integrations/syncro/tickets', data);
  }

  // ── InvoiceNinja ────────────────────────────────────────────

  invoiceNinjaSyncCustomers() {
    return this.request<{ imported: number; total: number; message: string }>('POST', '/integrations/invoiceninja/sync-customers');
  }

  // ── Email ──────────────────────────────────────────────────

  sendEmail(data: { to: string; subject: string; body: string }) {
    return this.request<{ success: boolean; method: string }>('POST', '/integrations/email/send', data);
  }

  // ── Office 365 Calendar ───────────────────────────────────

  getOffice365Calendars() {
    return this.request<{ calendars: Array<{ id: string; name: string }> }>('GET', '/integrations/office365/calendars');
  }

  createOffice365Event(data: { calendarId?: string; subject: string; description: string; startDateTime: string; endDateTime: string; timeZone?: string }) {
    return this.request<{ success: boolean; eventId?: string }>('POST', '/integrations/office365/calendar-event', data);
  }

  // ── Email Logs ──────────────────────────────────────────────

  getEmailLogs() {
    return this.request<{ logs: Array<{ id: string; recipient: string; subject: string; status: string; method: string; error: string | null; created_at: string }> }>('GET', '/email-logs');
  }
}

export const api = new ApiClient();
