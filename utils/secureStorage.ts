
/**
 * SECURE STORAGE UTILITY
 * 
 * IMPORTANT SECURITY NOTICE FOR PLESK/PRODUCTION:
 * This utility uses simple obfuscation (Base64 + Reversal) to obscure data in LocalStorage.
 * 
 * IN A PRODUCTION ENVIRONMENT (Laravel/PHP):
 * 1. DO NOT use this to store sensitive API Keys (Syncro, Office365 Refresh Tokens).
 * 2. Sensitive keys should be stored in the Laravel .env file or encrypted in the MariaDB database.
 * 3. The Frontend should request data via a proxied API endpoint (e.g., /api/syncro/customers)
 *    where the Laravel backend attaches the API key server-side.
 * 
 * This file is sufficient for the "Client-Side Demo" mode but must be refactored 
 * to use API calls for the V1.0 Server Installation.
 */

const ENCRYPTION_PREFIX = "ENC_v1_";

const encrypt = (data: string): string => {
  // Simple obfuscation for demo purposes. 
  return ENCRYPTION_PREFIX + btoa(unescape(encodeURIComponent(data))).split('').reverse().join('');
};

const decrypt = (data: string): string => {
  if (!data.startsWith(ENCRYPTION_PREFIX)) return data; // Return as is if not encrypted
  const clean = data.replace(ENCRYPTION_PREFIX, '').split('').reverse().join('');
  return decodeURIComponent(escape(atob(clean)));
};

export const SecureStorage = {
  setItem: (key: string, value: any) => {
    try {
      const stringified = JSON.stringify(value);
      const encrypted = encrypt(stringified);
      localStorage.setItem(key, encrypted);
    } catch (e) {
      console.error("Storage Error", e);
    }
  },

  getItem: <T>(key: string, defaultValue: T): T => {
    try {
      const item = localStorage.getItem(key);
      if (!item) return defaultValue;
      
      const decrypted = decrypt(item);
      return JSON.parse(decrypted);
    } catch (e) {
      console.error("Decryption Error", e);
      return defaultValue;
    }
  },

  // NEW: Create a full dump of the application state
  getAll: () => {
      const keys = ['sr_events', 'sr_settings', 'sr_customers', 'sr_services', 'sr_techs'];
      const backup: Record<string, any> = {
          version: '1.0',
          timestamp: Date.now(),
          data: {}
      };

      keys.forEach(key => {
          const raw = localStorage.getItem(key);
          if (raw) {
              backup.data[key] = decrypt(raw);
          }
      });
      return backup;
  },

  // NEW: Restore data from a JSON object
  restoreAll: (backupData: any) => {
      if (!backupData || !backupData.data) {
          throw new Error("Invalid backup file format");
      }
      
      Object.entries(backupData.data).forEach(([key, value]) => {
          // Encrypt before storing to maintain consistency
          const stringified = typeof value === 'string' ? value : JSON.stringify(value);
          const encrypted = encrypt(stringified);
          localStorage.setItem(key, encrypted);
      });
  }
};
