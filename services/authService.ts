import { OAuthState } from '../types';

/**
 * MOCK OAUTH SERVICE
 * In a real PHP environment, you would use a library like `league/oauth2-client` 
 * to handle the redirect and token exchange on the server.
 */

export const MS_GRAPH_SCOPES = [
  'User.Read',
  'Mail.Send',
  'Calendars.ReadWrite',
  'Offline_access'
];

export const startOAuthFlow = async (clientId: string, tenantId: string): Promise<OAuthState> => {
  return new Promise((resolve) => {
    // 1. In reality, we would redirect:
    // const authUrl = `https://login.microsoftonline.com/${tenantId}/oauth2/v2.0/authorize?client_id=${clientId}&response_type=code...`;
    // window.location.href = authUrl;

    // 2. Simulating the Popup interaction
    const width = 500;
    const height = 600;
    const left = window.screen.width / 2 - width / 2;
    const top = window.screen.height / 2 - height / 2;
    
    const popup = window.open(
      '', 
      'Office 365 Login', 
      `width=${width},height=${height},top=${top},left=${left}`
    );

    if (popup) {
      popup.document.write(`
        <div style="font-family: 'Segoe UI', sans-serif; padding: 40px; text-align: center;">
          <h2 style="color: #2f2f2f;">Microsoft</h2>
          <p>Sign in to connect <b>SmartRecur</b></p>
          <div style="margin: 20px 0; padding: 10px; background: #f0f0f0; border-radius: 4px;">
            Simulating Authentication...
          </div>
          <p style="font-size: 12px; color: #666;">This is a simulation. In production, this redirects to Microsoft.</p>
        </div>
      `);

      setTimeout(() => {
        popup.close();
        // 3. Simulating receiving a token back
        resolve({
          isConnected: true,
          accessToken: 'ey...SIMULATED_ACCESS_TOKEN...xyz',
          expiresAt: Date.now() + 3600 * 1000,
          userEmail: 'admin@msp-service.nl'
        });
      }, 2000);
    }
  });
};