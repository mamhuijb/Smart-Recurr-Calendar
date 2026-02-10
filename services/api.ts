/**
 * SmartRecur API Client
 * Handles all communication with the PHP/MariaDB backend.
 * JWT token is stored in localStorage for persistence across page reloads.
 */

const API_BASE = '/api';
const TOKEN_KEY = 'sr_token';

class ApiClient {
  private token: string | null = null;

  constructor() {
    this.token = localStorage.getItem(TOKEN_KEY);
  }

  // ── Core request method ─────────────────────────────────

  private async request<T = any>(method: string, path: string, body?: any): Promise<T> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const res = await fetch(`${API_BASE}${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    if (res.status === 401) {
      this.clearToken();
      window.location.reload();
      throw new Error('Session expired');
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
  }

  clearToken(): void {
    this.token = null;
    localStorage.removeItem(TOKEN_KEY);
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
}

export const api = new ApiClient();
