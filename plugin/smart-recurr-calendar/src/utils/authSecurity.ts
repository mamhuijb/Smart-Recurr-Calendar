
import * as OTPAuth from 'otpauth';

const APP_NAME = 'SmartRecur';

/**
 * Generates a random Base32 secret for TOTP.
 * Uses 20 bytes (160 bits) for standard security.
 */
export const generateSecret = (): string => {
  const secret = new OTPAuth.Secret({ size: 20 });
  return secret.base32;
};

/**
 * Generates the otpauth:// URI for the QR code.
 */
export const generateTotpUri = (secret: string, accountName: string = 'Admin'): string => {
  const totp = new OTPAuth.TOTP({
    issuer: APP_NAME,
    label: accountName,
    algorithm: 'SHA1',
    digits: 6,
    period: 30,
    secret: OTPAuth.Secret.fromBase32(secret),
  });
  return totp.toString();
};

/**
 * Verifies a TOTP token against a secret.
 * Returns true if valid within the time window (+/- 1 step = 30 seconds).
 */
export const verifyToken = (token: string, secret: string): boolean => {
  if (!token || !secret) return false;

  try {
    const totp = new OTPAuth.TOTP({
      issuer: APP_NAME,
      algorithm: 'SHA1',
      digits: 6,
      period: 30,
      secret: OTPAuth.Secret.fromBase32(secret),
    });

    // validate returns the delta (0 for current, -1 for prev, 1 for next) or null if invalid
    const delta = totp.validate({ token, window: 1 });
    return delta !== null;
  } catch (e) {
    console.error("TOTP Verification Error", e);
    return false;
  }
};
