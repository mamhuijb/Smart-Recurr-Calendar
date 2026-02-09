
import axios from 'axios';
import { config } from '../config/index.js';

// Get Auth URL
export const getAuthUrl = (req, res) => {
    const scopes = ['User.Read', 'Calendars.ReadWrite', 'Offline_access'];
    const authUrl = `https://login.microsoftonline.com/${config.office365.tenantId}/oauth2/v2.0/authorize?` +
        `client_id=${config.office365.clientId}` +
        `&response_type=code` +
        `&redirect_uri=${encodeURIComponent(config.office365.redirectUri)}` +
        `&response_mode=query` +
        `&scope=${encodeURIComponent(scopes.join(' '))}` +
        `&state=12345`; // Should be random in prod

    res.json({ url: authUrl });
};

// Exchange Code for Token
export const handleCallback = async (req, res) => {
    // Note: Microsoft redirects with GET parameters
    const code = req.query.code || req.body.code;

    if (!code) {
        return res.send(`<script>window.opener.postMessage({ type: 'OAUTH_ERROR', error: 'No code provided' }, '*'); window.close();</script>`);
    }

    try {
        const params = new URLSearchParams();
        params.append('client_id', config.office365.clientId);
        params.append('scope', 'User.Read Calendars.ReadWrite Offline_access');
        params.append('code', code);
        params.append('redirect_uri', config.office365.redirectUri);
        params.append('grant_type', 'authorization_code');
        params.append('client_secret', config.office365.clientSecret);

        const response = await axios.post(`https://login.microsoftonline.com/${config.office365.tenantId}/oauth2/v2.0/token`, params, {
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });

        const { access_token } = response.data;

        // Fetch User Profile to get Email
        let email = 'user@office365';
        try {
            const profile = await axios.get('https://graph.microsoft.com/v1.0/me', {
                headers: { 'Authorization': `Bearer ${access_token}` }
            });
            email = profile.data.mail || profile.data.userPrincipalName;
        } catch (e) {
            console.warn("Could not fetch profile", e.message);
        }

        const safeHtml = `
            <html><body>
            <script>
                window.opener.postMessage({ 
                    type: 'OAUTH_SUCCESS', 
                    token: '${access_token}', 
                    email: '${email}' 
                }, '*'); 
                window.close();
            </script>
            <h3>Authentication Successful. You can close this window.</h3>
            </body></html>
        `;
        res.send(safeHtml);

    } catch (error) {
        console.error('MS Graph Auth Error:', error.response?.data || error.message);
        res.send(`<script>window.opener.postMessage({ type: 'OAUTH_ERROR', error: 'Authentication Failed' }, '*'); window.close();</script>`);
    }
};

export const getEvents = async (req, res) => {
    const accessToken = req.headers.authorization?.split(' ')[1];
    if (!accessToken) return res.status(401).json({ error: 'No token' });

    try {
        const response = await axios.get('https://graph.microsoft.com/v1.0/me/calendar/events', {
            headers: { 'Authorization': `Bearer ${accessToken}` }
        });
        res.json(response.data);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};
