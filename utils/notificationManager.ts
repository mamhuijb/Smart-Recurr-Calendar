/**
 * NotificationManager — handles Web Push registration, permission requests,
 * and local notification scheduling as a fallback.
 *
 * Works with any backend (PHP on Plesk) via standard Web Push API (VAPID).
 * No Node.js runtime required — the server sends pushes via HTTP/2 to push endpoints.
 */

import { api } from '../services/api';

class NotificationManager {
  private registration: ServiceWorkerRegistration | null = null;
  private subscription: PushSubscription | null = null;

  /**
   * Check if push notifications are supported in this browser.
   */
  get isSupported(): boolean {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  }

  /**
   * Get current permission state.
   */
  get permission(): NotificationPermission {
    if (!('Notification' in window)) return 'denied';
    return Notification.permission;
  }

  /**
   * Check if currently subscribed to push.
   */
  get isSubscribed(): boolean {
    return this.subscription !== null;
  }

  /**
   * Initialize: register service worker and check existing subscription.
   */
  async init(): Promise<void> {
    if (!this.isSupported) return;

    try {
      this.registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
      await navigator.serviceWorker.ready;

      // Check for existing subscription
      this.subscription = await this.registration.pushManager.getSubscription();
    } catch (e) {
      console.warn('NotificationManager: SW registration failed', e);
    }
  }

  /**
   * Request notification permission and subscribe to push.
   * Returns true if successfully subscribed.
   */
  async requestPermissionAndSubscribe(vapidPublicKey: string): Promise<boolean> {
    if (!this.isSupported || !this.registration) return false;

    try {
      // Request permission
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') return false;

      // Convert VAPID key
      const applicationServerKey = this.urlBase64ToUint8Array(vapidPublicKey);

      // Subscribe to push
      this.subscription = await this.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: applicationServerKey as BufferSource,
      });

      // Send subscription to backend
      await api.registerPushSubscription(this.subscription.toJSON());

      return true;
    } catch (e) {
      console.error('NotificationManager: subscription failed', e);
      return false;
    }
  }

  /**
   * Unsubscribe from push notifications.
   */
  async unsubscribe(): Promise<boolean> {
    if (!this.subscription) return true;

    try {
      const endpoint = this.subscription.endpoint;
      await this.subscription.unsubscribe();
      this.subscription = null;

      // Notify backend
      await api.unregisterPushSubscription(endpoint);
      return true;
    } catch (e) {
      console.error('NotificationManager: unsubscribe failed', e);
      return false;
    }
  }

  /**
   * Schedule a local notification as fallback (for offline or when push unavailable).
   * Uses the Notifications API directly with a setTimeout.
   */
  scheduleLocalNotification(title: string, body: string, delayMs: number, data?: Record<string, any>): void {
    if (Notification.permission !== 'granted') return;

    setTimeout(() => {
      if (this.registration) {
        this.registration.showNotification(title, {
          body,
          icon: '/favicon.ico',
          tag: `local-${Date.now()}`,
          data: data || {},
        });
      } else {
        new Notification(title, { body, icon: '/favicon.ico' });
      }
    }, Math.max(0, delayMs));
  }

  /**
   * Convert VAPID public key from base64url to Uint8Array.
   */
  private urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    return Uint8Array.from(rawData, char => char.charCodeAt(0));
  }
}

export const notificationManager = new NotificationManager();
