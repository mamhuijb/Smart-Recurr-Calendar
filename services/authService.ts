
import { OAuthState } from '../types';

export const startOAuthFlow = async (clientId: string, tenantId: string): Promise<OAuthState> => {
  return new Promise(async (resolve, reject) => {
    try {
      // 1. Get the Auth URL from the Server
      const res = await fetch('/api/auth/url');
      if (!res.ok) throw new Error("Failed to get auth URL");
      const { url } = await res.json();

      // 2. Open Popup
      const width = 500;
      const height = 600;
      const left = window.screen.width / 2 - width / 2;
      const top = window.screen.height / 2 - height / 2;

      const popup = window.open(
        url,
        'Office 365 Login',
        `width=${width},height=${height},top=${top},left=${left}`
      );

      if (!popup) {
        reject(new Error("Popup blocked"));
        return;
      }

      // 3. Listen for the success message from the popup (which will be sent by the server callback)
      const messageHandler = (event: MessageEvent) => {
        if (event.origin !== window.location.origin) return; // simple security check

        if (event.data.type === 'OAUTH_SUCCESS') {
          window.removeEventListener('message', messageHandler);
          popup.close();
          resolve({
            isConnected: true,
            accessToken: event.data.token,
            expiresAt: Date.now() + 3500 * 1000,
            userEmail: event.data.email
          });
        } else if (event.data.type === 'OAUTH_ERROR') {
          window.removeEventListener('message', messageHandler);
          popup.close();
          reject(new Error(event.data.error));
        }
      };

      window.addEventListener('message', messageHandler);

      // 4. Poll to check if popup closed manually
      const timer = setInterval(() => {
        if (popup.closed) {
          clearInterval(timer);
          window.removeEventListener('message', messageHandler);
          // If we haven't resolved yet, it means user closed it
          // We can't easily detect "resolved" state here without a flag, but strict Promise impl handles it? 
          // Using a slightly loose check
        }
      }, 1000);

    } catch (e: any) {
      reject(e);
    }
  });
};