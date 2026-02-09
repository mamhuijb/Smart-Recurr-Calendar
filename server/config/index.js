
import dotenv from 'dotenv';

dotenv.config();

export const config = {
    port: process.env.PORT || 8080,
    nodeEnv: process.env.NODE_ENV || 'development',
    syncro: {
        apiKey: process.env.SYNCRO_API_KEY || '',
        subdomain: process.env.SYNCRO_SUBDOMAIN || ''
    },
    office365: {
        clientId: process.env.O365_CLIENT_ID || '',
        clientSecret: process.env.O365_CLIENT_SECRET || '',
        tenantId: process.env.O365_TENANT_ID || '',
        redirectUri: process.env.O365_REDIRECT_URI || 'http://localhost:5173/auth/callback'
    },
    jwtSecret: process.env.JWT_SECRET || 'dev_secret_key_change_in_prod'
};
