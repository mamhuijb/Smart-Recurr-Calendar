
export type LocationType = 'REMOTE' | 'ON_SITE';

export interface RecurrenceEvent {
  id: string;
  title: string; 
  customerId: string; 
  serviceId: string;
  technicianId?: string; 
  assetId?: string; 
  syncroTicketId?: string; 
  locationType: LocationType; 
  description: string;
  recurrenceRule: string; 
  generatedDates: string[]; 
  status: 'SCHEDULED' | 'COMPLETED' | 'MISSED';
  createdAt: number;
}

export interface Technician {
  id: string;
  name: string;
  email: string;
  color: string;
  skills: string[];
}

export interface Customer {
  id: string;
  name: string;
  email: string;
  phone: string;
  company: string;
  address?: string; 
  postcode?: string;
  syncroId?: string; 
  assets?: Asset[];
}

export interface Asset {
  id: string;
  name: string;
  type: string; 
}

export interface Service {
  id: string;
  name: string;
  type: 'RECURRING' | 'ONE_TIME';
  defaultDurationMin: number;
  defaultLocation: LocationType;
  color: string;
  createTicket: boolean;
  emailTemplate?: EmailTemplate; // New: Service specific template
}

export interface EmailTemplate {
  subject: string;
  body: string;
}

export interface OAuthState {
  isConnected: boolean;
  accessToken?: string;
  expiresAt?: number;
  userEmail?: string;
}

export interface BusinessHours {
  start: string; // "09:00"
  end: string;   // "17:00"
  closedDays: number[]; // 0=Sun, 1=Mon, etc.
}

export interface BrandingSettings {
  logoUrl: string;
  primaryColorHex: string; // e.g. #4f46e5
  themeMode: 'dark' | 'light';
}

export interface IntegrationConfig {
    enabled: boolean;
    apiKey: string;
    apiSecret?: string;
    endpoint: string;
    isConnected: boolean;
    lastChecked: number;
}

export interface SecuritySettings {
    twoFactorEnabled: boolean;
    twoFactorSecret: string; // In production, this would be encrypted
}

export interface AppSettings {
  branding: BrandingSettings;
  security: SecuritySettings; // New
  office365: {
    clientId: string;
    tenantId: string;
    auth: OAuthState;
    selectedCalendarId?: string; 
  };
  integrations: {
    syncroApiKey: string;
    syncroSubdomain: string;
    invoiceNinja: IntegrationConfig; // New
    zoho: IntegrationConfig; // New
  };
  reminders: {
    days: number[]; 
  };
  templates: {
    reminder: EmailTemplate; // Global default
  };
  businessHours: BusinessHours; 
  manualClosures: string[]; 
  holidays: string[]; 
}

export interface Reminder {
  eventId: string;
  eventTitle: string;
  customerName: string;
  targetDate: string;
  daysUntil: number;
  emailBody: string; 
}

export enum ViewMode {
  CALENDAR = 'CALENDAR',
  CREATE = 'CREATE',
  ADMIN = 'ADMIN'
}

export const DEFAULT_SETTINGS: AppSettings = {
  branding: {
    logoUrl: '',
    primaryColorHex: '#4f46e5', // Indigo-600 default
    themeMode: 'dark'
  },
  security: {
      twoFactorEnabled: false,
      twoFactorSecret: 'JBSWY3DPEHPK3PXP' // Mock Base32 secret
  },
  office365: { 
    clientId: '', 
    tenantId: '', 
    auth: { isConnected: false } 
  },
  integrations: { 
      syncroApiKey: '', 
      syncroSubdomain: '',
      invoiceNinja: { enabled: false, apiKey: '', endpoint: 'https://app.invoiceninja.com', isConnected: false, lastChecked: 0 },
      zoho: { enabled: false, apiKey: '', apiSecret: '', endpoint: 'https://www.zohoapis.com', isConnected: false, lastChecked: 0 }
  },
  reminders: { days: [14, 7, 1] },
  holidays: ['2025-01-01', '2025-04-27', '2025-12-25', '2025-12-26'], 
  manualClosures: [],
  businessHours: {
      start: "09:00",
      end: "17:00",
      closedDays: [0] // Sunday closed by default
  },
  templates: {
    reminder: {
      subject: "Appointment: {service_name} - {date}",
      body: "Hi {customer_name},\n\nWe have scheduled a technician ({tech_name}) for {service_name} on {date}.\nLocation: {location_type}\n\nPlease click here to confirm or reschedule: {link}\n\nMet vriendelijke groet,\n{company_name}"
    }
  }
};
