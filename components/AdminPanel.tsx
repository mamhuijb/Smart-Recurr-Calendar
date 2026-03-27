
import React, { useState, useMemo, useEffect } from 'react';
import { AppSettings, Customer, Service, Technician, RecurrenceEvent } from '../types';
import { Save, Users, Bell, RefreshCw, Briefcase, Key, ShieldCheck, UserCog, BarChart3, MapPin, Headset, PieChart, Clock, Calendar, Lock, Trash2, Palette, Moon, Sun, Database, Download, Upload, CheckCircle2, XCircle, Activity, Smartphone, Loader2, Mail, Send, FileText, Plus, BellRing } from 'lucide-react';
import { api } from '../services/api';
import { notificationManager } from '../utils/notificationManager';
import { toast } from '../utils/toast';
import { QRCodeSVG } from 'qrcode.react';
import { generateSecret, generateTotpUri } from '../utils/authSecurity';

interface IntegrationStatus {
    isConnected: boolean;
    lastChecked: string | null;
    message?: string;
}

interface AdminPanelProps {
    settings: AppSettings;
    onUpdateSettings: (s: AppSettings) => void;
    customers: Customer[];
    onUpdateCustomers: (c: Customer[]) => void;
    services: Service[];
    onUpdateServices: (s: Service[]) => void;
    technicians: Technician[];
    onUpdateTechnicians: (t: Technician[]) => void;
    events: RecurrenceEvent[];
    onClose: () => void;
}

type Tab = 'REPORTS' | 'BRANDING' | 'OAUTH' | 'SMTP' | 'INTEGRATIONS' | 'INVOICENINJA' | 'ZOHO' | 'SERVICES' | 'TECHS' | 'CUSTOMERS' | 'BUSINESS' | 'NOTIFICATIONS' | 'PUSH_NOTIFICATIONS' | 'EMAIL_LOGS' | 'BACKUP' | 'SECURITY';

// Extracted as a proper component to avoid hooks-in-IIFE violation
const PushNotificationsTab: React.FC = () => {
    const [pushEnabled, setPushEnabled] = React.useState(false);
    const [pushTimings, setPushTimings] = React.useState<number[]>([15, 60, 1440]);
    const [vapidKey, setVapidKey] = React.useState('');
    const [pushSupported] = React.useState(notificationManager.isSupported);
    const [pushPermission, setPushPermission] = React.useState(notificationManager.permission);
    const [subscribing, setSubscribing] = React.useState(false);
    const [testing, setTesting] = React.useState(false);
    const [pushLoaded, setPushLoaded] = React.useState(false);

    React.useEffect(() => {
        notificationManager.init();
        api.getPushConfig().then(res => {
            setVapidKey(res.vapidPublicKey || '');
            setPushEnabled(res.enabled);
            setPushTimings(res.timings || [15, 60, 1440]);
            setPushLoaded(true);
        }).catch(() => setPushLoaded(true));
    }, []);

    const handleSubscribe = async () => {
        if (!vapidKey) { toast.error('VAPID key not configured. Save settings first.'); return; }
        setSubscribing(true);
        const ok = await notificationManager.requestPermissionAndSubscribe(vapidKey);
        setPushPermission(notificationManager.permission);
        setSubscribing(false);
        if (ok) toast.success('Push notifications enabled!');
        else if (notificationManager.permission === 'denied') toast.error('Notifications were blocked. Check your browser settings.');
    };

    const handleUnsubscribe = async () => {
        setSubscribing(true);
        await notificationManager.unsubscribe();
        setPushPermission(notificationManager.permission);
        setSubscribing(false);
    };

    const handleTestPush = async () => {
        setTesting(true);
        try {
            const res = await api.testPushNotification();
            toast.success(`Test sent to ${(res as any).sent || 0} device(s).`);
        } catch (e: any) { toast.error(`Test failed: ${e.message}`); }
        finally { setTesting(false); }
    };

    const handleSavePushSettings = async () => {
        try {
            await api.updateSettings({
                pushNotifications: { enabled: pushEnabled, timings: pushTimings }
            });
            toast.success('Push notification settings saved.');
        } catch (e: any) { toast.error(`Save failed: ${e.message}`); }
    };

    const toggleTiming = (minutes: number) => {
        setPushTimings(prev => prev.includes(minutes) ? prev.filter(t => t !== minutes) : [...prev, minutes].sort((a, b) => a - b));
    };

    const timingOptions = [
        { label: '15 minutes before', value: 15 },
        { label: '30 minutes before', value: 30 },
        { label: '1 hour before', value: 60 },
        { label: '2 hours before', value: 120 },
        { label: '1 day before', value: 1440 },
        { label: '2 days before', value: 2880 },
        { label: '1 week before', value: 10080 },
    ];

    return (
    <div className="space-y-6 max-w-2xl animate-fade-in">
        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4 flex items-center gap-2">
            <BellRing className="w-6 h-6 text-primary-500" />
            Push Notifications
        </h3>

        {!pushSupported && (
            <div className="p-4 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-600 dark:text-red-400">
                Your browser does not support push notifications. Use Chrome, Firefox, or Edge for this feature.
            </div>
        )}

        <div className="p-5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
            <div className="flex items-center justify-between">
                <div>
                    <h4 className="font-bold text-gray-800 dark:text-white">Enable Push Notifications</h4>
                    <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">
                        Receive browser notifications for upcoming appointments.
                    </p>
                </div>
                <label className="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" checked={pushEnabled} onChange={e => setPushEnabled(e.target.checked)} className="sr-only peer" />
                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary-500/30 dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all rounded-full"></div>
                </label>
            </div>
        </div>

        {pushSupported && (
            <div className="p-5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 space-y-4">
                <h4 className="font-bold text-gray-800 dark:text-white">Device Registration</h4>

                <div className="flex items-center gap-3">
                    <div className={`w-3 h-3 rounded-full ${pushPermission === 'granted' && notificationManager.isSubscribed ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]' : pushPermission === 'denied' ? 'bg-red-500' : 'bg-yellow-500'}`} />
                    <span className="text-sm text-gray-600 dark:text-slate-300">
                        {pushPermission === 'granted' && notificationManager.isSubscribed ? 'This device is subscribed to push notifications.'
                         : pushPermission === 'denied' ? 'Notifications are blocked in browser settings.'
                         : 'This device is not registered for push notifications.'}
                    </span>
                </div>

                <div className="flex gap-2 flex-wrap">
                    {pushPermission !== 'denied' && !notificationManager.isSubscribed && (
                        <button onClick={handleSubscribe} disabled={subscribing} className="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50 flex items-center gap-2">
                            {subscribing ? <Loader2 className="w-4 h-4 animate-spin" /> : <BellRing className="w-4 h-4" />}
                            Enable on this device
                        </button>
                    )}
                    {notificationManager.isSubscribed && (
                        <button onClick={handleUnsubscribe} disabled={subscribing} className="bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-300 px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50 flex items-center gap-2">
                            {subscribing ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                            Unsubscribe
                        </button>
                    )}
                    <button onClick={handleTestPush} disabled={testing || !notificationManager.isSubscribed} className="bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-300 px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50 flex items-center gap-2">
                        {testing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
                        Send Test
                    </button>
                </div>
            </div>
        )}

        <div className="p-5 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 space-y-4">
            <h4 className="font-bold text-gray-800 dark:text-white">Notification Timing</h4>
            <p className="text-sm text-gray-500 dark:text-slate-400">
                Choose when to send push notifications before an appointment starts.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                {timingOptions.map(opt => (
                    <label key={opt.value} className={`flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition-all ${
                        pushTimings.includes(opt.value)
                            ? 'bg-primary-50 dark:bg-primary-900/15 border-primary-300 dark:border-primary-600/40 text-primary-700 dark:text-primary-300'
                            : 'bg-white dark:bg-slate-900 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-400 hover:border-gray-300'
                    }`}>
                        <input type="checkbox" checked={pushTimings.includes(opt.value)} onChange={() => toggleTiming(opt.value)}
                            className="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500" />
                        <span className="text-sm font-medium">{opt.label}</span>
                    </label>
                ))}
            </div>
        </div>

        <div className="p-5 bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-xl text-sm text-blue-700 dark:text-blue-300 space-y-3">
            <h4 className="font-bold">Server-Side Setup</h4>
            <p>Push notifications are sent by the cron job at the configured timings. Ensure:</p>
            <ol className="list-decimal ml-4 space-y-1 text-xs">
                <li>Cron job is running: <code className="bg-blue-100 dark:bg-blue-900/30 px-1 rounded">php api/cron/send-reminders.php</code></li>
                <li>Run frequency: every 15 minutes (to match notification timing)</li>
                <li>VAPID keys are auto-generated on first use (stored in database)</li>
                <li>For HTTPS: push notifications require a secure context (HTTPS)</li>
            </ol>
            {vapidKey && (
                <div className="mt-2">
                    <p className="text-xs font-medium">VAPID Public Key:</p>
                    <code className="block text-[10px] bg-blue-100 dark:bg-blue-900/30 p-2 rounded mt-1 break-all">{vapidKey}</code>
                </div>
            )}
        </div>

        <button onClick={handleSavePushSettings} className="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-xl font-medium flex items-center gap-2 shadow-sm">
            <Save className="w-4 h-4" /> Save Push Settings
        </button>
    </div>
    );
};

export const AdminPanel: React.FC<AdminPanelProps> = ({
    settings,
    onUpdateSettings,
    customers,
    onUpdateCustomers,
    services,
    onUpdateServices,
    technicians,
    onUpdateTechnicians,
    events,
    onClose
}) => {
    const [activeTab, setActiveTab] = useState<Tab>('REPORTS');
    const [localSettings, setLocalSettings] = useState<AppSettings>(settings);
    const [integrationStatus, setIntegrationStatus] = useState<Record<string, IntegrationStatus>>({});
    const [testingIntegration, setTestingIntegration] = useState<string | null>(null);

    // Fetch integration status from API on mount
    useEffect(() => {
        api.getIntegrationStatus().then(res => {
            const integrations = res.integrations || {};
            setIntegrationStatus(integrations);

            // Populate localSettings with saved integration configs from DB
            setLocalSettings(prev => {
                const smtpCfg = integrations.smtp?.config || {};
                const o365Cfg = integrations.office365?.config || {};
                const syncroCfg = integrations.syncro?.config || {};
                const invoiceCfg = integrations.invoiceninja?.config || {};
                const zohoCfg = integrations.zoho?.config || {};

                return {
                    ...prev,
                    smtp: {
                        host: smtpCfg.host || prev.smtp.host,
                        port: smtpCfg.port || prev.smtp.port,
                        username: smtpCfg.username || prev.smtp.username,
                        password: '', // Never populate masked password — user re-enters or leaves blank to keep existing
                        fromEmail: smtpCfg.fromEmail || prev.smtp.fromEmail,
                        fromName: smtpCfg.fromName || prev.smtp.fromName,
                        encryption: smtpCfg.encryption || prev.smtp.encryption,
                    },
                    office365: {
                        ...prev.office365,
                        clientId: o365Cfg.clientId || prev.office365.clientId,
                        tenantId: o365Cfg.tenantId || prev.office365.tenantId,
                        auth: {
                            isConnected: integrations.office365?.isConnected || false,
                            userEmail: o365Cfg.userEmail || prev.office365.auth.userEmail,
                        },
                    },
                    integrations: {
                        ...prev.integrations,
                        syncroApiKey: syncroCfg.apiKey === '••••••••' ? '' : (syncroCfg.apiKey || prev.integrations.syncroApiKey),
                        syncroSubdomain: syncroCfg.subdomain || prev.integrations.syncroSubdomain,
                        invoiceNinja: {
                            ...prev.integrations.invoiceNinja,
                            apiKey: invoiceCfg.apiKey === '••••••••' ? '' : (invoiceCfg.apiKey || prev.integrations.invoiceNinja.apiKey),
                            endpoint: invoiceCfg.endpoint || prev.integrations.invoiceNinja.endpoint,
                        },
                        zoho: {
                            ...prev.integrations.zoho,
                            apiKey: zohoCfg.apiKey === '••••••••' ? '' : (zohoCfg.apiKey || prev.integrations.zoho.apiKey),
                            apiSecret: zohoCfg.apiSecret === '••••••••' ? '' : (zohoCfg.apiSecret || prev.integrations.zoho.apiSecret),
                            endpoint: zohoCfg.endpoint || prev.integrations.zoho.endpoint,
                        },
                    },
                };
            });
        }).catch(() => {});
    }, []);
    const [newService, setNewService] = useState<Partial<Service>>({ name: '', type: 'RECURRING', color: '#4F46E5', createTicket: true, defaultLocation: 'ON_SITE' });
    const [newTech, setNewTech] = useState({ name: '', email: '', color: '#10B981' });
    const [newCustomer, setNewCustomer] = useState<Partial<Customer>>({ company: '', name: '', email: '', phone: '', address: '', postcode: '' });

    // State for Service Template Editing
    const [editingServiceId, setEditingServiceId] = useState<string | null>(null);
    const [editingTemplate, setEditingTemplate] = useState<{ subject: string, body: string }>({ subject: '', body: '' });
    const [editingReminderDays, setEditingReminderDays] = useState<number[]>([]);

    // Email logs
    const [emailLogs, setEmailLogs] = useState<Array<{ id: string; recipient: string; subject: string; status: string; method: string; error: string | null; created_at: string }>>([]);
    const [loadingLogs, setLoadingLogs] = useState(false);

    // Test email
    const [testEmailAddress, setTestEmailAddress] = useState('');
    const [sendingTestEmail, setSendingTestEmail] = useState(false);
    const [testEmailResult, setTestEmailResult] = useState<{ success: boolean; message: string } | null>(null);

    // --- REPORTING LOGIC ---
    const reportData = useMemo(() => {
        const currentYear = new Date().getFullYear();
        const flatEvents = events.flatMap(ev =>
            ev.generatedDates.map(dateStr => ({
                ...ev,
                date: new Date(dateStr)
            }))
        ).filter(ev => ev.date.getFullYear() === currentYear);

        const total = flatEvents.length;
        const quarters = { q1: 0, q2: 0, q3: 0, q4: 0 };
        const monthly = Array(12).fill(0);
        flatEvents.forEach(ev => {
            const month = ev.date.getMonth();
            monthly[month]++;
            if (month < 3) quarters.q1++;
            else if (month < 6) quarters.q2++;
            else if (month < 9) quarters.q3++;
            else quarters.q4++;
        });
        const remoteCount = flatEvents.filter(e => e.locationType === 'REMOTE').length;
        const onsiteCount = flatEvents.filter(e => e.locationType === 'ON_SITE').length;
        return { total, quarters, monthly, remoteCount, onsiteCount, currentYear };
    }, [events]);

    // --- PENDING REMINDERS (Email Queue Preview) ---
    const pendingReminders = useMemo(() => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const todayTime = today.getTime();
        const reminders: Array<{
            eventId: string;
            eventTitle: string;
            customerName: string;
            customerEmail: string;
            targetDate: string;
            daysUntil: number;
            emailSubject: string;
            emailBody: string;
        }> = [];

        events.forEach(event => {
            const customer = customers.find(c => c.id === event.customerId);
            const service = services.find(s => s.id === event.serviceId);
            if (!customer || !service) return;

            const reminderDays = (service.reminderDays && service.reminderDays.length > 0)
                ? service.reminderDays
                : settings.reminders.days;

            const template = (service.emailTemplate && service.emailTemplate.body)
                ? service.emailTemplate
                : settings.templates.reminder;

            event.generatedDates.forEach(dateStr => {
                const eventTime = new Date(dateStr).getTime();
                const diffDays = Math.ceil((eventTime - todayTime) / (1000 * 60 * 60 * 24));

                if (reminderDays.includes(diffDays) || diffDays === 0) {
                    let subject = template.subject;
                    let body = template.body;
                    const replacements: Record<string, string> = {
                        '{customer_name}': customer.name,
                        '{service_name}': service.name,
                        '{date}': dateStr,
                        '{company_name}': customer.company || '',
                    };
                    for (const [key, val] of Object.entries(replacements)) {
                        subject = subject.split(key).join(val);
                        body = body.split(key).join(val);
                    }

                    reminders.push({
                        eventId: event.id,
                        eventTitle: event.title,
                        customerName: customer.name,
                        customerEmail: customer.email,
                        targetDate: dateStr,
                        daysUntil: diffDays,
                        emailSubject: subject,
                        emailBody: body,
                    });
                }
            });
        });

        return reminders.sort((a, b) => a.daysUntil - b.daysUntil);
    }, [events, customers, services, settings]);

    // Syncro MSP Import via PHP backend proxy
    const handleSyncroImport = async () => {
        try {
            // First save any updated config
            await api.saveIntegrationConfig('syncro', {
                apiKey: localSettings.integrations.syncroApiKey,
                subdomain: localSettings.integrations.syncroSubdomain,
            });

            const data = await api.syncroImportCustomers();
            const newCusts = data.customers.filter((c: Customer) => !customers.some(ex => ex.syncroId === c.syncroId));

            // Save imported customers to database
            for (const c of newCusts) {
                await api.createCustomer(c);
            }

            onUpdateCustomers([...customers, ...newCusts]);
            toast.success(`Successfully imported ${newCusts.length} new customers from SyncroMSP.`);
        } catch (e: any) {
            toast.error(`Import failed: ${e.message}`);
        }
    };

    const [syncingInvoiceNinja, setSyncingInvoiceNinja] = useState(false);

    const handleInvoiceNinjaSync = async () => {
        setSyncingInvoiceNinja(true);
        try {
            await api.saveIntegrationConfig('invoiceninja', {
                apiKey: localSettings.integrations.invoiceNinja.apiKey,
                endpoint: localSettings.integrations.invoiceNinja.endpoint,
            });
            const result = await api.invoiceNinjaSyncCustomers();
            toast.success(result.message);
            const data = await api.getCustomers();
            onUpdateCustomers(data.customers || []);
        } catch (e: any) {
            toast.error(`Sync failed: ${e.message}`);
        } finally {
            setSyncingInvoiceNinja(false);
        }
    };

    const handleOAuthConnect = async () => {
        try {
            // Save config first (include clientSecret and redirectUri)
            const o365Save: Record<string, any> = {
                clientId: localSettings.office365.clientId,
                tenantId: localSettings.office365.tenantId,
                redirectUri: (localSettings.office365 as any).redirectUri || (window.location.origin + '/api/integrations/office365/callback'),
            };
            if ((localSettings.office365 as any).clientSecret) {
                o365Save.clientSecret = (localSettings.office365 as any).clientSecret;
            }
            await api.saveIntegrationConfig('office365', o365Save);

            const { url } = await api.getOffice365AuthUrl();
            const width = 500, height = 600;
            const left = window.screen.width / 2 - width / 2;
            const top = window.screen.height / 2 - height / 2;
            const popup = window.open(url, 'Office 365 Login', `width=${width},height=${height},top=${top},left=${left}`);

            if (!popup) { toast.error('Popup blocked. Please allow popups.'); return; }

            const handler = (event: MessageEvent) => {
                if (event.origin !== window.location.origin) return;
                if (event.data.type === 'OAUTH_SUCCESS') {
                    window.removeEventListener('message', handler);
                    popup.close();
                    setLocalSettings(prev => ({
                        ...prev,
                        office365: { ...prev.office365, auth: { isConnected: true, userEmail: event.data.email } }
                    }));
                    setIntegrationStatus(prev => ({ ...prev, office365: { isConnected: true, lastChecked: new Date().toISOString() } }));
                    toast.success('Connected to Office 365!');
                } else if (event.data.type === 'OAUTH_ERROR') {
                    window.removeEventListener('message', handler);
                    popup.close();
                    toast.error(`Auth failed: ${event.data.error}`);
                }
            };
            window.addEventListener('message', handler);
        } catch (e: any) {
            toast.error(`Auth failed: ${e.message}`);
        }
    };

    const handleBackup = async () => {
        try {
            const data = await api.exportBackup();
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `smartrecur_backup_${new Date().toISOString().split('T')[0]}.json`;
            a.click();
            URL.revokeObjectURL(url);
        } catch (e: any) {
            toast.error(`Backup failed: ${e.message}`);
        }
    };

    const handleRestore = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = async (event) => {
            try {
                const json = JSON.parse(event.target?.result as string);
                await api.importBackup(json);
                toast.success("Database restored successfully! Reloading...");
                setTimeout(() => window.location.reload(), 1500);
            } catch (err) {
                toast.error("Failed to restore backup. Invalid file format.");
            }
        };
        reader.readAsText(file);
    };

    const testIntegration = async (type: string) => {
        setTestingIntegration(type);
        try {
            // Save config before testing
            if (type === 'invoiceninja') {
                await api.saveIntegrationConfig('invoiceninja', {
                    apiKey: localSettings.integrations.invoiceNinja.apiKey,
                    endpoint: localSettings.integrations.invoiceNinja.endpoint,
                });
            } else if (type === 'zoho') {
                await api.saveIntegrationConfig('zoho', {
                    apiKey: localSettings.integrations.zoho.apiKey,
                    apiSecret: localSettings.integrations.zoho.apiSecret,
                    endpoint: localSettings.integrations.zoho.endpoint,
                });
            } else if (type === 'syncro') {
                await api.saveIntegrationConfig('syncro', {
                    apiKey: localSettings.integrations.syncroApiKey,
                    subdomain: localSettings.integrations.syncroSubdomain,
                });
            } else if (type === 'office365') {
                await api.saveIntegrationConfig('office365', {
                    clientId: localSettings.office365.clientId,
                    tenantId: localSettings.office365.tenantId,
                });
            } else if (type === 'smtp') {
                const smtpSave: Record<string, any> = {
                    host: localSettings.smtp.host,
                    port: localSettings.smtp.port,
                    username: localSettings.smtp.username,
                    fromEmail: localSettings.smtp.fromEmail,
                    fromName: localSettings.smtp.fromName,
                    encryption: localSettings.smtp.encryption,
                };
                if (localSettings.smtp.password) smtpSave.password = localSettings.smtp.password;
                await api.saveIntegrationConfig('smtp', smtpSave);
            }

            const result = await api.testIntegration(type);
            setIntegrationStatus(prev => ({
                ...prev,
                [type]: { isConnected: result.isConnected, lastChecked: new Date().toISOString(), message: result.message }
            }));
            if (result.isConnected) {
                toast.success(result.message);
            } else {
                toast.error(result.message);
            }
        } catch (e: any) {
            setIntegrationStatus(prev => ({
                ...prev,
                [type]: { isConnected: false, lastChecked: new Date().toISOString(), message: e.message }
            }));
            toast.error(`Connection test failed: ${e.message}`);
        } finally {
            setTestingIntegration(null);
        }
    };

    const handleAddService = async () => {
        if (!newService.name) return;
        const s: Service = {
            id: crypto.randomUUID(),
            name: newService.name!,
            type: newService.type as 'RECURRING' | 'ONE_TIME',
            defaultDurationMin: 60,
            defaultLocation: newService.defaultLocation as 'ON_SITE' | 'REMOTE',
            color: newService.color!,
            createTicket: newService.createTicket!
        };
        try {
            await api.createService(s);
        } catch (e: any) {
            toast.error(`Failed to save service: ${e.message}`);
            return;
        }
        onUpdateServices([...services, s]);
        setNewService({ name: '', type: 'RECURRING', color: '#4F46E5', createTicket: true, defaultLocation: 'ON_SITE' });
    };

    const handleAddTech = async () => {
        if (!newTech.name) return;
        const t: Technician = {
            id: crypto.randomUUID(),
            name: newTech.name,
            email: newTech.email,
            color: newTech.color,
            skills: []
        };
        try {
            await api.createTechnician(t);
        } catch (e: any) {
            toast.error(`Failed to save technician: ${e.message}`);
            return;
        }
        onUpdateTechnicians([...technicians, t]);
        setNewTech({ name: '', email: '', color: '#10B981' });
    }

    const handleAddCustomer = async () => {
        if (!newCustomer.name) {
            toast.error("Contact Name is required.");
            return;
        }
        const c: Customer = {
            id: crypto.randomUUID(),
            company: newCustomer.company || '',
            name: newCustomer.name!,
            email: newCustomer.email || '',
            phone: newCustomer.phone || '',
            address: newCustomer.address || '',
            postcode: newCustomer.postcode || '',
            assets: []
        };
        try {
            await api.createCustomer(c);
        } catch (e: any) {
            toast.error(`Failed to save customer: ${e.message}`);
            return;
        }
        onUpdateCustomers([...customers, c]);
        setNewCustomer({ company: '', name: '', email: '', phone: '', address: '', postcode: '' });
    };

    const handleSaveSettings = async () => {
        try {
            // Save app settings to database
            await api.updateSettings({
                branding: localSettings.branding,
                security: localSettings.security,
                reminders: localSettings.reminders,
                holidays: localSettings.holidays,
                manualClosures: localSettings.manualClosures,
                businessHours: localSettings.businessHours,
                templates: localSettings.templates,
                preferredMailMethod: localSettings.preferredMailMethod,
            });

            // Save integration configs to database
            const o365Payload: Record<string, any> = {
                clientId: localSettings.office365.clientId,
                tenantId: localSettings.office365.tenantId,
                redirectUri: (localSettings.office365 as any).redirectUri || (window.location.origin + '/api/integrations/office365/callback'),
            };
            if ((localSettings.office365 as any).clientSecret) {
                o365Payload.clientSecret = (localSettings.office365 as any).clientSecret;
            }
            await api.saveIntegrationConfig('office365', o365Payload);
            await api.saveIntegrationConfig('syncro', {
                apiKey: localSettings.integrations.syncroApiKey,
                subdomain: localSettings.integrations.syncroSubdomain,
            });
            await api.saveIntegrationConfig('invoiceninja', {
                apiKey: localSettings.integrations.invoiceNinja.apiKey,
                endpoint: localSettings.integrations.invoiceNinja.endpoint,
            });
            await api.saveIntegrationConfig('zoho', {
                apiKey: localSettings.integrations.zoho.apiKey,
                apiSecret: localSettings.integrations.zoho.apiSecret,
                endpoint: localSettings.integrations.zoho.endpoint,
            });
            const smtpPayload: Record<string, any> = {
                host: localSettings.smtp.host,
                port: localSettings.smtp.port,
                username: localSettings.smtp.username,
                fromEmail: localSettings.smtp.fromEmail,
                fromName: localSettings.smtp.fromName,
                encryption: localSettings.smtp.encryption,
            };
            // Only send password if user entered a new one (don't overwrite stored password with empty string)
            if (localSettings.smtp.password) {
                smtpPayload.password = localSettings.smtp.password;
            }
            await api.saveIntegrationConfig('smtp', smtpPayload);

            onUpdateSettings(localSettings);
            toast.success("Configuration saved.");
        } catch (e: any) {
            toast.error(`Save failed: ${e.message}`);
        }
    };

    const [availableCalendars, setAvailableCalendars] = useState<{id: string; name: string}[]>([]);
    const [loadingCalendars, setLoadingCalendars] = useState(false);

    // Fetch real calendars when Office 365 is connected
    useEffect(() => {
        if (localSettings.office365.auth.isConnected && integrationStatus.office365?.isConnected) {
            setLoadingCalendars(true);
            api.getOffice365Calendars().then(res => {
                setAvailableCalendars(res.calendars || []);
            }).catch(() => {
                setAvailableCalendars([]);
            }).finally(() => setLoadingCalendars(false));
        }
    }, [localSettings.office365.auth.isConnected, integrationStatus.office365?.isConnected]);

    const navBtn = (tab: Tab, icon: React.ReactNode, label: string, onClick?: () => void, badge?: React.ReactNode) => (
        <button
            onClick={onClick || (() => setActiveTab(tab))}
            className={`w-full flex items-center gap-3 px-3 py-2 rounded-xl text-[13px] font-medium transition-all duration-200 group ${
                activeTab === tab
                    ? 'bg-primary-600 text-white shadow-sm shadow-primary-600/20'
                    : 'text-gray-600 dark:text-slate-400 hover:bg-gray-100/80 dark:hover:bg-slate-800/60 hover:text-gray-900 dark:hover:text-slate-200'
            }`}
        >
            <span className={`flex-shrink-0 ${activeTab === tab ? 'text-white/90' : 'text-gray-400 dark:text-slate-500 group-hover:text-gray-600 dark:group-hover:text-slate-300'}`}>{icon}</span>
            <span className="truncate">{label}</span>
            {badge && <span className="ml-auto flex-shrink-0">{badge}</span>}
        </button>
    );

    const sectionLabel = (text: string) => (
        <div className="px-3 pb-1 pt-5 text-[10px] font-bold text-gray-400/80 dark:text-slate-600 uppercase tracking-[0.08em] first:pt-0">{text}</div>
    );

    return (
        <div className="flex h-full bg-white dark:bg-slate-900 rounded-2xl overflow-hidden shadow-elevated border border-gray-200/60 dark:border-slate-800/60 text-gray-800 dark:text-slate-300">
            {/* Sidebar */}
            <div className="w-60 bg-gray-50/80 dark:bg-slate-950/80 flex flex-col border-r border-gray-200/60 dark:border-slate-800/60 overflow-y-auto">
                <div className="px-5 py-5 border-b border-gray-200/60 dark:border-slate-800/60">
                    <h2 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                        <div className="w-7 h-7 rounded-lg bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-sm shadow-primary-600/20">
                            <Key className="w-3.5 h-3.5 text-white" />
                        </div>
                        Admin
                    </h2>
                </div>
                <nav className="flex-1 px-3 py-3 space-y-0.5 text-sm">
                    {sectionLabel('General')}
                    {navBtn('REPORTS', <BarChart3 className="w-4 h-4" />, 'Reporting')}
                    {navBtn('BRANDING', <Palette className="w-4 h-4" />, 'Branding & UI')}
                    {navBtn('SECURITY', <ShieldCheck className="w-4 h-4" />, 'Security (2FA)')}

                    {sectionLabel('Operations')}
                    {navBtn('CUSTOMERS', <Users className="w-4 h-4" />, 'Customers')}
                    {navBtn('SERVICES', <Briefcase className="w-4 h-4" />, 'Services')}
                    {navBtn('TECHS', <UserCog className="w-4 h-4" />, 'Technicians')}
                    {navBtn('BUSINESS', <Clock className="w-4 h-4" />, 'Business Hours')}

                    {sectionLabel('Integrations')}
                    {navBtn('OAUTH', <ShieldCheck className="w-4 h-4" />, 'Office 365', undefined,
                        integrationStatus.office365?.isConnected ? <CheckCircle2 className="w-3.5 h-3.5 text-green-500" /> : undefined)}
                    {navBtn('SMTP', <Mail className="w-4 h-4" />, 'SMTP Email', undefined,
                        integrationStatus.smtp?.isConnected ? <CheckCircle2 className="w-3.5 h-3.5 text-green-500" /> : undefined)}
                    {navBtn('INTEGRATIONS', <RefreshCw className="w-4 h-4" />, 'Syncro MSP', undefined,
                        integrationStatus.syncro?.isConnected ? <CheckCircle2 className="w-3.5 h-3.5 text-green-500" /> : undefined)}
                    {navBtn('INVOICENINJA', <Activity className="w-4 h-4" />, 'InvoiceNinja', undefined,
                        integrationStatus.invoiceninja?.isConnected ? <CheckCircle2 className="w-3.5 h-3.5 text-green-500" /> : undefined)}
                    {navBtn('ZOHO', <Activity className="w-4 h-4" />, 'Zoho', undefined,
                        integrationStatus.zoho?.isConnected ? <CheckCircle2 className="w-3.5 h-3.5 text-green-500" /> : undefined)}

                    {sectionLabel('System')}
                    {navBtn('NOTIFICATIONS', <Bell className="w-4 h-4" />, 'Email & Reminders')}
                    {navBtn('PUSH_NOTIFICATIONS', <BellRing className="w-4 h-4" />, 'Push Notifications')}
                    {navBtn('EMAIL_LOGS', <FileText className="w-4 h-4" />, 'Email Logs',
                        () => { setActiveTab('EMAIL_LOGS'); if (emailLogs.length === 0) { setLoadingLogs(true); api.getEmailLogs().then(r => setEmailLogs(r.logs || [])).catch(() => {}).finally(() => setLoadingLogs(false)); } })}
                    {navBtn('BACKUP', <Database className="w-4 h-4" />, 'Backup & Restore')}
                </nav>
                <div className="px-3 py-3 border-t border-gray-200/60 dark:border-slate-800/60">
                    <button onClick={onClose} className="w-full py-2 text-[13px] text-center text-gray-500 hover:text-gray-900 dark:text-slate-500 dark:hover:text-white rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 transition-all duration-200">
                        Sluiten
                    </button>
                </div>
            </div>

            {/* Content Area */}
            <div className="flex-1 overflow-y-auto bg-white dark:bg-slate-900 p-6 sm:p-8">

                {/* REPORTING DASHBOARD */}
                {activeTab === 'REPORTS' && (
                    <div className="space-y-8">
                        <div className="flex justify-between items-center border-b border-gray-200 dark:border-slate-700 pb-4">
                            <h3 className="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                <PieChart className="w-6 h-6 text-primary-600" />
                                Jaaroverzicht {reportData.currentYear}
                            </h3>
                            <span className="text-sm bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200 px-3 py-1 rounded-full font-medium">Real-time Data</span>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="bg-gradient-to-br from-primary-600 to-primary-800 rounded-xl p-6 text-white shadow-lg border border-primary-500/30">
                                <p className="text-primary-200 text-sm font-medium uppercase mb-1">Totaal Afspraken</p>
                                <h4 className="text-4xl font-bold">{reportData.total}</h4>
                                <p className="text-xs text-primary-200 mt-2">Geplande bezoeken in {reportData.currentYear}</p>
                            </div>
                            <div className="bg-gray-50 dark:bg-slate-800 rounded-xl p-6 shadow border border-gray-200 dark:border-slate-700 flex flex-col justify-center">
                                <div className="flex items-center gap-2 mb-2">
                                    <MapPin className="w-5 h-5 text-blue-500" />
                                    <span className="text-gray-600 dark:text-slate-300 font-medium">Op Locatie</span>
                                </div>
                                <div className="text-2xl font-bold text-gray-900 dark:text-white">{reportData.onsiteCount} <span className="text-sm text-gray-500 dark:text-slate-500 font-normal">bezoeken</span></div>
                                <div className="w-full bg-gray-200 dark:bg-slate-700 h-1.5 rounded-full mt-3 overflow-hidden">
                                    <div className="bg-blue-500 h-full rounded-full" style={{ width: `${(reportData.onsiteCount / (reportData.total || 1)) * 100}%` }}></div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* SECURITY TAB */}
                {activeTab === 'SECURITY' && (
                    <div className="space-y-6 max-w-2xl">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4 flex items-center gap-2">
                            <ShieldCheck className="w-6 h-6 text-green-500" />
                            Security Settings
                        </h3>

                        <div className="bg-gray-50 dark:bg-slate-800 p-6 rounded-xl border border-gray-200 dark:border-slate-700">
                            <div className="flex items-center justify-between mb-4">
                                <div>
                                    <h4 className="font-bold text-gray-800 dark:text-white text-lg">Two-Factor Authentication (2FA)</h4>
                                    <p className="text-sm text-gray-500 dark:text-slate-400">Secure the admin panel with a second verification step.</p>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer">
                                    <input
                                        type="checkbox"
                                        className="sr-only peer"
                                        checked={localSettings.security.twoFactorEnabled}
                                        onChange={(e) => {
                                            const isEnabled = e.target.checked;
                                            let currentSecret = localSettings.security.twoFactorSecret;

                                            // Generate new secret if enabling and (no secret exists OR it's the mock default)
                                            if (isEnabled && (!currentSecret || currentSecret === 'JBSWY3DPEHPK3PXP')) {
                                                currentSecret = generateSecret();
                                            }

                                            setLocalSettings(prev => ({
                                                ...prev,
                                                security: {
                                                    ...prev.security,
                                                    twoFactorEnabled: isEnabled,
                                                    twoFactorSecret: currentSecret
                                                }
                                            }));
                                        }}
                                    />
                                    <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                                </label>
                            </div>

                            {localSettings.security.twoFactorEnabled && (
                                <div className="mt-6 pt-6 border-t border-gray-200 dark:border-slate-700 animate-in fade-in slide-in-from-top-2">
                                    <div className="flex gap-6 items-center">
                                        <div className="bg-white p-2 rounded-lg shadow-sm border border-gray-200">
                                            <QRCodeSVG
                                                value={generateTotpUri(localSettings.security.twoFactorSecret)}
                                                size={140}
                                                level="M"
                                            />
                                        </div>
                                        <div className="flex-1">
                                            <p className="text-sm font-bold text-gray-700 dark:text-slate-300 mb-2">Setup Instructions</p>
                                            <ol className="list-decimal list-inside text-sm text-gray-600 dark:text-slate-400 space-y-1">
                                                <li>Download Google Authenticator or Authy.</li>
                                                <li>Scan the QR code to the left.</li>
                                                <li>Or enter the secret key manually:</li>
                                            </ol>
                                            <div className="mt-3 p-3 bg-gray-200 dark:bg-slate-900 rounded font-mono text-center tracking-widest font-bold text-gray-800 dark:text-slate-200 border border-gray-300 dark:border-slate-700 select-all">
                                                {localSettings.security.twoFactorSecret}
                                            </div>
                                            <p className="text-xs text-yellow-600 dark:text-yellow-500 mt-2 flex items-center gap-1">
                                                <Lock className="w-3 h-3" /> Warning: Save this key. It will be hidden if you navigate away.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* BACKUP TAB */}
                {activeTab === 'BACKUP' && (
                    <div className="space-y-6 max-w-2xl">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4 flex items-center gap-2">
                            <Database className="w-6 h-6 text-purple-500" />
                            Backup & Restore
                        </h3>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="p-6 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 flex flex-col items-center text-center">
                                <div className="w-12 h-12 bg-primary-100 dark:bg-primary-900/30 text-primary-600 rounded-full flex items-center justify-center mb-4">
                                    <Download className="w-6 h-6" />
                                </div>
                                <h4 className="font-bold text-gray-800 dark:text-white mb-2">Export Database</h4>
                                <p className="text-sm text-gray-500 dark:text-slate-400 mb-6">Create a full JSON dump of customers, services, events, and settings.</p>
                                <button onClick={handleBackup} className="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2 px-6 rounded-lg w-full transition-colors shadow-lg shadow-primary-900/20">
                                    Download Backup
                                </button>
                            </div>

                            <div className="p-6 bg-gray-50 dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 flex flex-col items-center text-center">
                                <div className="w-12 h-12 bg-green-100 dark:bg-green-900/30 text-green-600 rounded-full flex items-center justify-center mb-4">
                                    <Upload className="w-6 h-6" />
                                </div>
                                <h4 className="font-bold text-gray-800 dark:text-white mb-2">Restore Database</h4>
                                <p className="text-sm text-gray-500 dark:text-slate-400 mb-6">Upload a previously exported JSON file to overwrite current data.</p>
                                <label className="bg-white dark:bg-slate-700 hover:bg-gray-50 dark:hover:bg-slate-600 text-gray-700 dark:text-white font-medium py-2 px-6 rounded-lg w-full transition-colors border border-gray-300 dark:border-slate-600 cursor-pointer">
                                    <input type="file" accept=".json" onChange={handleRestore} className="hidden" />
                                    Select File
                                </label>
                            </div>
                        </div>
                    </div>
                )}

                {/* INVOICENINJA TAB */}
                {activeTab === 'INVOICENINJA' && (
                    <div className="space-y-6 max-w-2xl">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4 flex items-center gap-2">
                            <Activity className="w-6 h-6 text-black dark:text-white" />
                            InvoiceNinja Integration
                        </h3>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">API Endpoint URL</label>
                                <input className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    value={localSettings.integrations.invoiceNinja.endpoint}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, integrations: { ...prev.integrations, invoiceNinja: { ...prev.integrations.invoiceNinja, endpoint: e.target.value } } }))}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">API Key</label>
                                <input type="password" className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    value={localSettings.integrations.invoiceNinja.apiKey}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, integrations: { ...prev.integrations, invoiceNinja: { ...prev.integrations.invoiceNinja, apiKey: e.target.value } } }))}
                                />
                            </div>

                            <div className="flex items-center justify-between p-4 bg-gray-100 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700">
                                <div className="flex items-center gap-3">
                                    <div className={`w-3 h-3 rounded-full ${integrationStatus.invoiceninja?.isConnected ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]' : 'bg-red-500'}`}></div>
                                    <div>
                                        <p className="font-bold text-sm text-gray-800 dark:text-white">{integrationStatus.invoiceninja?.isConnected ? 'Active Connection' : 'Disconnected'}</p>
                                        {integrationStatus.invoiceninja?.lastChecked && (
                                            <p className="text-xs text-gray-500">Last checked: {new Date(integrationStatus.invoiceninja.lastChecked).toLocaleString()}</p>
                                        )}
                                        {integrationStatus.invoiceninja?.message && (
                                            <p className="text-xs text-gray-500">{integrationStatus.invoiceninja.message}</p>
                                        )}
                                    </div>
                                </div>
                                <button onClick={() => testIntegration('invoiceninja')} disabled={testingIntegration === 'invoiceninja'} className="bg-gray-800 dark:bg-white text-white dark:text-black px-4 py-2 rounded text-sm font-medium disabled:opacity-50 flex items-center gap-2">
                                    {testingIntegration === 'invoiceninja' ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                                    Test Connection
                                </button>
                            </div>

                            <div className="bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                <h4 className="font-bold text-gray-800 dark:text-white mb-2 flex items-center gap-2">
                                    <Users className="w-4 h-4 text-green-500" />
                                    Customer Sync
                                </h4>
                                <p className="text-sm text-gray-500 dark:text-slate-400 mb-3">
                                    Import clients from InvoiceNinja into SmartRecur. Existing customers (already imported) will be skipped.
                                </p>
                                <button
                                    onClick={handleInvoiceNinjaSync}
                                    disabled={syncingInvoiceNinja}
                                    className="bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg font-medium flex items-center gap-2"
                                >
                                    {syncingInvoiceNinja ? <Loader2 className="w-4 h-4 animate-spin" /> : <RefreshCw className="w-4 h-4" />}
                                    Sync Customers from InvoiceNinja
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* ZOHO TAB */}
                {activeTab === 'ZOHO' && (
                    <div className="space-y-6 max-w-2xl">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4 flex items-center gap-2">
                            <Activity className="w-6 h-6 text-yellow-500" />
                            Zoho Books/CRM Integration
                        </h3>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Client ID / API Key</label>
                                <input className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    value={localSettings.integrations.zoho.apiKey}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, integrations: { ...prev.integrations, zoho: { ...prev.integrations.zoho, apiKey: e.target.value } } }))}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Client Secret</label>
                                <input type="password" className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    value={localSettings.integrations.zoho.apiSecret}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, integrations: { ...prev.integrations, zoho: { ...prev.integrations.zoho, apiSecret: e.target.value } } }))}
                                />
                            </div>

                            <div className="flex items-center justify-between p-4 bg-gray-100 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700">
                                <div className="flex items-center gap-3">
                                    <div className={`w-3 h-3 rounded-full ${integrationStatus.zoho?.isConnected ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]' : 'bg-red-500'}`}></div>
                                    <div>
                                        <p className="font-bold text-sm text-gray-800 dark:text-white">{integrationStatus.zoho?.isConnected ? 'Active Connection' : 'Disconnected'}</p>
                                        {integrationStatus.zoho?.lastChecked && (
                                            <p className="text-xs text-gray-500">Last checked: {new Date(integrationStatus.zoho.lastChecked).toLocaleString()}</p>
                                        )}
                                        {integrationStatus.zoho?.message && (
                                            <p className="text-xs text-gray-500">{integrationStatus.zoho.message}</p>
                                        )}
                                    </div>
                                </div>
                                <button onClick={() => testIntegration('zoho')} disabled={testingIntegration === 'zoho'} className="bg-yellow-600 text-white px-4 py-2 rounded text-sm font-medium disabled:opacity-50 flex items-center gap-2">
                                    {testingIntegration === 'zoho' ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                                    Test Connection
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* BRANDING TAB */}
                {activeTab === 'BRANDING' && (
                    <div className="space-y-6 max-w-2xl">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4">Theme & Customization</h3>

                        <div className="p-4 bg-gray-50 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700">
                            <h4 className="font-bold text-gray-800 dark:text-white mb-4">Appearance Mode</h4>
                            <div className="flex gap-4">
                                <button
                                    onClick={() => setLocalSettings({ ...localSettings, branding: { ...localSettings.branding, themeMode: 'light' } })}
                                    className={`flex-1 p-4 rounded-lg border-2 flex flex-col items-center gap-2 transition-all ${localSettings.branding.themeMode === 'light' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700' : 'border-gray-200 dark:border-slate-700 text-gray-500'}`}
                                >
                                    <Sun className="w-6 h-6" />
                                    <span className="font-bold">Light Mode</span>
                                </button>
                                <button
                                    onClick={() => setLocalSettings({ ...localSettings, branding: { ...localSettings.branding, themeMode: 'dark' } })}
                                    className={`flex-1 p-4 rounded-lg border-2 flex flex-col items-center gap-2 transition-all ${localSettings.branding.themeMode === 'dark' ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700' : 'border-gray-200 dark:border-slate-700 text-gray-500'}`}
                                >
                                    <Moon className="w-6 h-6" />
                                    <span className="font-bold">Dark Mode</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Primary Brand Color (Hex)</label>
                            <div className="flex gap-2 mt-1">
                                <input
                                    type="color"
                                    className="h-10 w-12 rounded overflow-hidden cursor-pointer"
                                    value={localSettings.branding.primaryColorHex}
                                    onChange={e => setLocalSettings({ ...localSettings, branding: { ...localSettings.branding, primaryColorHex: e.target.value } })}
                                />
                                <input
                                    type="text"
                                    className="flex-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white uppercase"
                                    value={localSettings.branding.primaryColorHex}
                                    onChange={e => setLocalSettings({ ...localSettings, branding: { ...localSettings.branding, primaryColorHex: e.target.value } })}
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Company Logo URL</label>
                            <input
                                type="url"
                                className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                placeholder="https://example.com/logo.png"
                                value={localSettings.branding.logoUrl}
                                onChange={e => setLocalSettings({ ...localSettings, branding: { ...localSettings.branding, logoUrl: e.target.value } })}
                            />
                            <p className="text-xs text-gray-500 dark:text-slate-500 mt-1">Leave empty to use default icon. Supports PNG/SVG.</p>
                        </div>
                    </div>
                )}

                {/* BUSINESS HOURS */}
                {activeTab === 'BUSINESS' && (
                    <div className="space-y-6 max-w-2xl">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4">Business Hours</h3>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Open From</label>
                                <input type="time" className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    value={localSettings.businessHours.start}
                                    onChange={e => setLocalSettings({ ...localSettings, businessHours: { ...localSettings.businessHours, start: e.target.value } })}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Closed At</label>
                                <input type="time" className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    value={localSettings.businessHours.end}
                                    onChange={e => setLocalSettings({ ...localSettings, businessHours: { ...localSettings.businessHours, end: e.target.value } })}
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-2">Closed Days</label>
                            <div className="flex gap-2">
                                {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day, idx) => {
                                    // idx maps directly to JS Date.getDay() (0=Sun)
                                    const isClosed = localSettings.businessHours.closedDays.includes(idx);
                                    return (
                                        <button
                                            key={day}
                                            onClick={() => {
                                                const newClosed = isClosed
                                                    ? localSettings.businessHours.closedDays.filter(d => d !== idx)
                                                    : [...localSettings.businessHours.closedDays, idx];
                                                setLocalSettings({ ...localSettings, businessHours: { ...localSettings.businessHours, closedDays: newClosed } });
                                            }}
                                            className={`px-4 py-2 rounded-lg text-sm font-bold transition-all ${isClosed ? 'bg-red-50 dark:bg-red-900/50 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800' : 'bg-green-50 dark:bg-green-900/50 text-green-600 dark:text-green-400 border border-green-200 dark:border-green-800'}`}
                                        >
                                            {day}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-2">Manual Closures (Holidays)</label>
                            <div className="flex gap-2 mb-2">
                                <input type="date" id="manualDateInput" className="px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white color-scheme-dark" />
                                <button
                                    onClick={() => {
                                        const input = document.getElementById('manualDateInput') as HTMLInputElement;
                                        if (input.value && !localSettings.manualClosures.includes(input.value)) {
                                            setLocalSettings({ ...localSettings, manualClosures: [...localSettings.manualClosures, input.value] });
                                        }
                                    }}
                                    className="bg-primary-600 px-4 py-2 rounded-lg text-white hover:bg-primary-700"
                                >Add Date</button>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {localSettings.manualClosures.map(d => (
                                    <span key={d} className="px-3 py-1 bg-gray-200 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-full text-xs text-gray-700 dark:text-slate-300 flex items-center gap-2">
                                        {d}
                                        <button onClick={() => setLocalSettings({ ...localSettings, manualClosures: localSettings.manualClosures.filter(x => x !== d) })} className="hover:text-red-500"><Lock className="w-3 h-3" /></button>
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {/* OAUTH 365 */}
                {activeTab === 'OAUTH' && (
                    <div className="space-y-6 max-w-2xl">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4 flex items-center gap-2">
                            <ShieldCheck className="w-6 h-6 text-blue-500" />
                            Office 365 Integration
                        </h3>

                        <div className="bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
                            <p className="font-bold mb-1">Setup Instructions</p>
                            <ol className="list-decimal ml-4 space-y-1 text-xs">
                                <li>Go to Azure Portal &rarr; App registrations &rarr; New registration</li>
                                <li>Set Redirect URI to: <code className="bg-blue-100 dark:bg-blue-900/30 px-1 rounded">{window.location.origin}/api/integrations/office365/callback</code></li>
                                <li>Under API Permissions, add: <strong>User.Read, Calendars.ReadWrite, Mail.Send</strong></li>
                                <li>Under Certificates & Secrets, create a new client secret</li>
                                <li>Copy the Application ID, Tenant ID, and Client Secret below</li>
                            </ol>
                        </div>

                        <div className="grid grid-cols-1 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Application (Client) ID</label>
                                <input className="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white" value={localSettings.office365.clientId} onChange={e => setLocalSettings({ ...localSettings, office365: { ...localSettings.office365, clientId: e.target.value } })} />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Directory (Tenant) ID</label>
                                <input className="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white" value={localSettings.office365.tenantId} onChange={e => setLocalSettings({ ...localSettings, office365: { ...localSettings.office365, tenantId: e.target.value } })} />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Client Secret</label>
                                <input type="password" className="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    placeholder="Enter your Azure client secret"
                                    value={(localSettings.office365 as any).clientSecret || ''}
                                    onChange={e => setLocalSettings({ ...localSettings, office365: { ...localSettings.office365, clientSecret: e.target.value } as any })}
                                />
                            </div>
                        </div>

                        <div className="p-4 bg-gray-50 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 flex items-center justify-between">
                            <div>
                                <p className="font-bold text-gray-800 dark:text-white">Connection Status</p>
                                <p className={`text-sm ${localSettings.office365.auth.isConnected ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'}`}>
                                    {localSettings.office365.auth.isConnected ? `Connected as ${localSettings.office365.auth.userEmail}` : 'Not Connected'}
                                </p>
                            </div>
                            {localSettings.office365.auth.isConnected ? (
                                <button onClick={() => setLocalSettings({ ...localSettings, office365: { ...localSettings.office365, auth: { isConnected: false } } })} className="text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300 text-sm font-medium">Disconnect</button>
                            ) : (
                                <button onClick={handleOAuthConnect} className="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                                    <ShieldCheck className="w-4 h-4" /> Connect
                                </button>
                            )}
                        </div>

                        {/* Calendar Sync (Only shows if connected) */}
                        {localSettings.office365.auth.isConnected && (
                            <div className="space-y-4 animate-in fade-in slide-in-from-top-2">
                                {/* Sync Toggle */}
                                <div className="p-4 bg-gray-50 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h4 className="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                                <Calendar className="w-4 h-4 text-blue-500" />
                                                Calendar Sync
                                            </h4>
                                            <p className="text-sm text-gray-500 dark:text-slate-400">Automatically create Outlook calendar events when scheduling appointments.</p>
                                        </div>
                                        <label className="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                className="sr-only peer"
                                                checked={localSettings.office365.calendarSyncEnabled || false}
                                                onChange={(e) => setLocalSettings({
                                                    ...localSettings,
                                                    office365: { ...localSettings.office365, calendarSyncEnabled: e.target.checked }
                                                })}
                                            />
                                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                        </label>
                                    </div>
                                </div>

                                {/* Calendar Selection */}
                                {localSettings.office365.calendarSyncEnabled && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-2">Target Calendar</label>
                                        {loadingCalendars ? (
                                            <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400">
                                                <Loader2 className="w-4 h-4 animate-spin" /> Loading calendars...
                                            </div>
                                        ) : (
                                            <select
                                                className="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                                value={localSettings.office365.selectedCalendarId || ''}
                                                onChange={(e) => setLocalSettings({ ...localSettings, office365: { ...localSettings.office365, selectedCalendarId: e.target.value } })}
                                            >
                                                <option value="">Default Calendar</option>
                                                {availableCalendars.map(cal => (
                                                    <option key={cal.id} value={cal.id}>{cal.name}</option>
                                                ))}
                                            </select>
                                        )}
                                        <p className="text-xs text-gray-500 dark:text-slate-500 mt-1">New appointments in SmartRecur will be synced to this calendar.</p>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}

                {/* SMTP Tab */}
                {activeTab === 'SMTP' && (
                    <div className="space-y-6 max-w-2xl">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4 flex items-center gap-2">
                            <Mail className="w-6 h-6 text-orange-500" />
                            SMTP Email Configuration
                        </h3>
                        <p className="text-sm text-gray-500 dark:text-slate-400">
                            Configure an SMTP server to send reminder emails. This works as an alternative to Office 365 email.
                        </p>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">SMTP Host</label>
                                <input className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    placeholder="smtp.office365.com or smtp.gmail.com"
                                    value={localSettings.smtp.host}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, smtp: { ...prev.smtp, host: e.target.value } }))}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Port</label>
                                <input type="number" className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    value={localSettings.smtp.port}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, smtp: { ...prev.smtp, port: parseInt(e.target.value) || 587 } }))}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Encryption</label>
                                <select className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    value={localSettings.smtp.encryption}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, smtp: { ...prev.smtp, encryption: e.target.value as any } }))}
                                >
                                    <option value="tls">STARTTLS (Port 587)</option>
                                    <option value="ssl">SSL/TLS (Port 465)</option>
                                    <option value="none">None (Port 25)</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Username / Email</label>
                                <input className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    placeholder="user@yourdomain.com"
                                    value={localSettings.smtp.username}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, smtp: { ...prev.smtp, username: e.target.value } }))}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Password</label>
                                <input type="password" className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    placeholder={integrationStatus.smtp?.isConnected ? '(saved — leave empty to keep)' : ''}
                                    value={localSettings.smtp.password}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, smtp: { ...prev.smtp, password: e.target.value } }))}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">From Email</label>
                                <input className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    placeholder="noreply@yourdomain.com"
                                    value={localSettings.smtp.fromEmail}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, smtp: { ...prev.smtp, fromEmail: e.target.value } }))}
                                />
                                <p className="text-xs text-gray-500 dark:text-slate-500 mt-1">Defaults to username if left empty</p>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">From Name</label>
                                <input className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    placeholder="SmartRecur"
                                    value={localSettings.smtp.fromName}
                                    onChange={e => setLocalSettings(prev => ({ ...prev, smtp: { ...prev.smtp, fromName: e.target.value } }))}
                                />
                            </div>
                        </div>

                        <div className="flex items-center justify-between p-4 bg-gray-100 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700">
                            <div className="flex items-center gap-3">
                                <div className={`w-3 h-3 rounded-full ${integrationStatus.smtp?.isConnected ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]' : 'bg-red-500'}`}></div>
                                <div>
                                    <p className="font-bold text-sm text-gray-800 dark:text-white">{integrationStatus.smtp?.isConnected ? 'Connected' : 'Not Connected'}</p>
                                    {integrationStatus.smtp?.message && (
                                        <p className="text-xs text-gray-500">{integrationStatus.smtp.message}</p>
                                    )}
                                </div>
                            </div>
                            <button onClick={() => testIntegration('smtp')} disabled={testingIntegration === 'smtp'} className="bg-orange-600 text-white px-4 py-2 rounded text-sm font-medium disabled:opacity-50 flex items-center gap-2">
                                {testingIntegration === 'smtp' ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
                                Test Connection
                            </button>
                        </div>

                        <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-sm text-blue-700 dark:text-blue-300">
                            <p className="font-bold mb-1">Common SMTP Settings:</p>
                            <ul className="list-disc list-inside space-y-1 text-xs">
                                <li><strong>Office 365:</strong> smtp.office365.com, Port 587, STARTTLS</li>
                                <li><strong>Gmail:</strong> smtp.gmail.com, Port 587, STARTTLS (use App Password)</li>
                                <li><strong>Custom:</strong> Check with your hosting provider</li>
                            </ul>
                        </div>
                    </div>
                )}

                {/* SERVICES & TEMPLATES */}
                {activeTab === 'SERVICES' && (
                    <div className="space-y-6">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4">Service Catalog</h3>

                        {/* Add New Service Form */}
                        <div className="bg-gray-50 dark:bg-slate-800 p-4 rounded-lg border border-gray-200 dark:border-slate-700 flex gap-4 items-end flex-wrap">
                            <div className="flex-1 min-w-[200px]">
                                <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Name</label>
                                <input className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white" value={newService.name} onChange={e => setNewService({ ...newService, name: e.target.value })} placeholder="Service Name" />
                            </div>
                            <div className="w-32">
                                <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Type</label>
                                <select className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white" value={newService.type} onChange={e => setNewService({ ...newService, type: e.target.value as any })}>
                                    <option value="RECURRING">Recurring</option>
                                    <option value="ONE_TIME">One Time</option>
                                </select>
                            </div>
                            <div className="w-32">
                                <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Loc</label>
                                <select className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white" value={newService.defaultLocation} onChange={e => setNewService({ ...newService, defaultLocation: e.target.value as any })}>
                                    <option value="ON_SITE">On Site</option>
                                    <option value="REMOTE">Remote</option>
                                </select>
                            </div>
                            <button onClick={handleAddService} className="bg-primary-600 text-white px-4 py-2 rounded hover:bg-primary-700 h-[42px]">Add</button>
                        </div>

                        {/* List */}
                        <div className="grid grid-cols-1 gap-3">
                            {services.map(s => (
                                <div key={s.id} className="bg-gray-50 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg p-4">
                                    <div className="flex justify-between items-center mb-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-4 h-4 rounded-full" style={{ backgroundColor: s.color }}></div>
                                            <div>
                                                <h4 className="font-bold text-gray-800 dark:text-white">{s.name}</h4>
                                                <div className="flex gap-2">
                                                    <span className="text-xs bg-gray-200 dark:bg-slate-700 px-2 py-0.5 rounded text-gray-600 dark:text-slate-300">{s.type}</span>
                                                    <span className="text-xs bg-gray-200 dark:bg-slate-700 px-2 py-0.5 rounded text-gray-600 dark:text-slate-300 flex items-center gap-1">
                                                        {s.defaultLocation === 'ON_SITE' ? <MapPin className="w-3 h-3" /> : <Headset className="w-3 h-3" />}
                                                        {s.defaultLocation === 'ON_SITE' ? 'On Site' : 'Remote'}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            <button
                                                onClick={async () => {
                                                    if (editingServiceId === s.id) {
                                                        // Save Template + reminderDays to DB and state
                                                        const updatedService = {
                                                            ...s,
                                                            emailTemplate: editingTemplate,
                                                            reminderDays: editingReminderDays.length > 0 ? editingReminderDays : undefined,
                                                        };
                                                        try { await api.updateService(s.id, updatedService); } catch {}
                                                        const updated = services.map(svc => svc.id === s.id ? updatedService : svc);
                                                        onUpdateServices(updated);
                                                        setEditingServiceId(null);
                                                    } else {
                                                        // Open editor
                                                        setEditingServiceId(s.id);
                                                        setEditingTemplate(s.emailTemplate || { subject: '', body: '' });
                                                        setEditingReminderDays(s.reminderDays || []);
                                                    }
                                                }}
                                                className={`text-sm px-3 py-1 rounded border ${editingServiceId === s.id ? 'bg-green-600 border-green-500 text-white' : 'border-gray-300 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'}`}
                                            >
                                                {editingServiceId === s.id ? 'Save Changes' : 'Edit Settings'}
                                            </button>
                                            <button onClick={async () => {
                                                try { await api.deleteService(s.id); } catch {}
                                                onUpdateServices(services.filter(x => x.id !== s.id));
                                            }} className="text-red-500 hover:bg-gray-200 dark:hover:bg-slate-700 p-2 rounded">
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                    {/* Inline Service Settings Editor */}
                                    {editingServiceId === s.id && (
                                        <div className="mt-4 p-4 bg-white dark:bg-slate-900 rounded border border-gray-200 dark:border-slate-700 animate-in fade-in slide-in-from-top-2 space-y-4">
                                            {/* Per-service Reminder Schedule */}
                                            <div>
                                                <h5 className="text-xs font-bold text-orange-500 uppercase mb-2 flex items-center gap-1">
                                                    <Bell className="w-3 h-3" /> Reminder Schedule for {s.name}
                                                </h5>
                                                <p className="text-xs text-gray-500 dark:text-slate-500 mb-2">
                                                    Set custom reminder days for this service. Leave empty to use the global schedule ({localSettings.reminders.days.sort((a,b) => b-a).join(', ')} days).
                                                </p>
                                                <div className="flex flex-wrap gap-1.5 mb-2">
                                                    {editingReminderDays.sort((a, b) => b - a).map(day => (
                                                        <span key={day} className="inline-flex items-center gap-1 px-2 py-0.5 bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300 rounded-full text-xs font-medium border border-orange-200 dark:border-orange-800">
                                                            {day}d
                                                            <button onClick={() => setEditingReminderDays(editingReminderDays.filter(d => d !== day))} className="text-orange-500 hover:text-red-500">
                                                                <XCircle className="w-3 h-3" />
                                                            </button>
                                                        </span>
                                                    ))}
                                                    {editingReminderDays.length === 0 && (
                                                        <span className="text-xs text-gray-400 dark:text-slate-500 italic">Using global schedule</span>
                                                    )}
                                                </div>
                                                <div className="flex gap-2">
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="90"
                                                        id={`svcReminder-${s.id}`}
                                                        className="w-20 px-2 py-1 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded text-sm text-gray-900 dark:text-white"
                                                        placeholder="Days"
                                                    />
                                                    <button
                                                        onClick={() => {
                                                            const input = document.getElementById(`svcReminder-${s.id}`) as HTMLInputElement;
                                                            const val = parseInt(input.value);
                                                            if (!isNaN(val) && val >= 0 && val <= 90 && !editingReminderDays.includes(val)) {
                                                                setEditingReminderDays([...editingReminderDays, val]);
                                                                input.value = '';
                                                            }
                                                        }}
                                                        className="bg-orange-600 text-white px-2 py-1 rounded text-xs font-medium hover:bg-orange-700 flex items-center gap-1"
                                                    >
                                                        <Plus className="w-3 h-3" /> Add
                                                    </button>
                                                    {editingReminderDays.length > 0 && (
                                                        <button
                                                            onClick={() => setEditingReminderDays([])}
                                                            className="text-xs text-gray-500 dark:text-slate-400 hover:text-red-500 px-2"
                                                        >
                                                            Clear (use global)
                                                        </button>
                                                    )}
                                                </div>
                                            </div>

                                            {/* Email Template */}
                                            <div>
                                                <h5 className="text-xs font-bold text-primary-500 uppercase mb-2">Custom Email Template for {s.name}</h5>
                                                <div className="space-y-2">
                                                    <input
                                                        className="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded text-sm text-gray-900 dark:text-white"
                                                        placeholder="Subject (Leave empty to use global default)"
                                                        value={editingTemplate.subject}
                                                        onChange={e => setEditingTemplate({ ...editingTemplate, subject: e.target.value })}
                                                    />
                                                    <textarea
                                                        className="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded text-sm text-gray-900 dark:text-white h-24 font-mono"
                                                        placeholder="Body content..."
                                                        value={editingTemplate.body}
                                                        onChange={e => setEditingTemplate({ ...editingTemplate, body: e.target.value })}
                                                    />
                                                    <p className="text-xs text-gray-500 dark:text-slate-500">Overrides global template if set. Variables: {'{customer_name}, {service_name}, {date}, {tech_name}'}</p>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Global Notifications Tab (Fallback) */}
                {activeTab === 'NOTIFICATIONS' && (
                    <div className="space-y-6 max-w-3xl">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4">Email & Reminders</h3>

                        {/* Outgoing Mail Method */}
                        <div className="bg-gray-50 dark:bg-slate-800 p-5 rounded-xl border border-gray-200 dark:border-slate-700">
                            <h4 className="font-bold text-gray-800 dark:text-white mb-1 flex items-center gap-2">
                                <Mail className="w-4 h-4 text-blue-500" />
                                Outgoing Mail Method
                            </h4>
                            <p className="text-sm text-gray-500 dark:text-slate-400 mb-4">Choose which service to use for sending reminder emails.</p>
                            <div className="grid grid-cols-3 gap-3">
                                {(['auto', 'smtp', 'office365'] as const).map(method => (
                                    <button
                                        key={method}
                                        onClick={() => setLocalSettings(prev => ({ ...prev, preferredMailMethod: method }))}
                                        className={`p-3 rounded-lg border-2 text-center transition-all ${
                                            localSettings.preferredMailMethod === method
                                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300'
                                                : 'border-gray-200 dark:border-slate-700 text-gray-500 dark:text-slate-400 hover:border-gray-300 dark:hover:border-slate-600'
                                        }`}
                                    >
                                        <div className="font-bold text-sm">{method === 'auto' ? 'Auto' : method === 'smtp' ? 'SMTP' : 'Office 365'}</div>
                                        <div className="text-xs mt-1 opacity-75">
                                            {method === 'auto' ? 'SMTP first, then O365' : method === 'smtp' ? 'SMTP server only' : 'Microsoft Graph API'}
                                        </div>
                                    </button>
                                ))}
                            </div>
                            <p className="text-xs text-gray-500 dark:text-slate-500 mt-2">
                                Current: <span className="font-medium">
                                    {localSettings.preferredMailMethod === 'auto' ? 'Auto (tries SMTP first, falls back to Office 365)'
                                        : localSettings.preferredMailMethod === 'smtp' ? 'SMTP only'
                                        : 'Office 365 only'}
                                </span>
                            </p>
                        </div>

                        {/* Reminder Frequency */}
                        <div className="bg-gray-50 dark:bg-slate-800 p-5 rounded-xl border border-gray-200 dark:border-slate-700">
                            <h4 className="font-bold text-gray-800 dark:text-white mb-1">Reminder Schedule</h4>
                            <p className="text-sm text-gray-500 dark:text-slate-400 mb-4">Configure how many days before an appointment a reminder email is sent. Add multiple to send reminders at different intervals.</p>

                            <div className="flex flex-wrap gap-2 mb-4">
                                {localSettings.reminders.days
                                    .sort((a, b) => b - a)
                                    .map(day => (
                                    <span key={day} className="inline-flex items-center gap-2 px-3 py-1.5 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full text-sm font-medium border border-primary-200 dark:border-primary-800">
                                        {day} {day === 1 ? 'day' : 'days'} before
                                        <button
                                            onClick={() => setLocalSettings({
                                                ...localSettings,
                                                reminders: { ...localSettings.reminders, days: localSettings.reminders.days.filter(d => d !== day) }
                                            })}
                                            className="text-primary-500 hover:text-red-500 transition-colors"
                                        >
                                            <XCircle className="w-4 h-4" />
                                        </button>
                                    </span>
                                ))}
                                {localSettings.reminders.days.length === 0 && (
                                    <span className="text-sm text-gray-400 dark:text-slate-500 italic">No reminders configured</span>
                                )}
                            </div>

                            <div className="flex gap-2 items-end">
                                <div className="flex-1">
                                    <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Days before appointment</label>
                                    <input
                                        type="number"
                                        min="0"
                                        max="90"
                                        id="newReminderDay"
                                        className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white"
                                        placeholder="e.g. 7"
                                    />
                                </div>
                                <button
                                    onClick={() => {
                                        const input = document.getElementById('newReminderDay') as HTMLInputElement;
                                        const val = parseInt(input.value);
                                        if (!isNaN(val) && val >= 0 && val <= 90 && !localSettings.reminders.days.includes(val)) {
                                            setLocalSettings({
                                                ...localSettings,
                                                reminders: { ...localSettings.reminders, days: [...localSettings.reminders.days, val] }
                                            });
                                            input.value = '';
                                        }
                                    }}
                                    className="bg-primary-600 text-white px-4 py-2 rounded hover:bg-primary-700 h-[42px] text-sm font-medium"
                                >
                                    Add
                                </button>
                            </div>

                            <div className="mt-3 flex gap-2">
                                <button
                                    onClick={() => setLocalSettings({
                                        ...localSettings,
                                        reminders: { ...localSettings.reminders, days: [14, 7, 1] }
                                    })}
                                    className="text-xs text-primary-600 dark:text-primary-400 hover:underline"
                                >
                                    Reset to default (14, 7, 1 days)
                                </button>
                            </div>
                        </div>

                        {/* Email Template */}
                        <div className="bg-gray-50 dark:bg-slate-800 p-5 rounded-xl border border-gray-200 dark:border-slate-700">
                            <h4 className="font-bold text-gray-800 dark:text-white mb-1">Global Email Template</h4>
                            <p className="text-sm text-gray-500 dark:text-slate-400 mb-4">This template is used if a specific service does not have its own template.</p>
                            <div className="space-y-3">
                                <div>
                                    <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Subject</label>
                                    <input
                                        className="w-full px-4 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-900 dark:text-white"
                                        value={localSettings.templates.reminder.subject}
                                        onChange={e => setLocalSettings({
                                            ...localSettings,
                                            templates: { ...localSettings.templates, reminder: { ...localSettings.templates.reminder, subject: e.target.value } }
                                        })}
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Body</label>
                                    <textarea
                                        className="w-full px-4 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded-lg font-mono text-sm h-40 text-gray-900 dark:text-white"
                                        value={localSettings.templates.reminder.body}
                                        onChange={e => setLocalSettings({
                                            ...localSettings,
                                            templates: { ...localSettings.templates, reminder: { ...localSettings.templates.reminder, body: e.target.value } }
                                        })}
                                    />
                                    <p className="text-xs text-gray-500 dark:text-slate-500 mt-1">Variables: {'{customer_name}, {service_name}, {date}, {company_name}, {tech_name}, {link}'}</p>
                                </div>
                            </div>
                        </div>

                        {/* Send Test Email */}
                        <div className="bg-gray-50 dark:bg-slate-800 p-5 rounded-xl border border-gray-200 dark:border-slate-700">
                            <h4 className="font-bold text-gray-800 dark:text-white mb-1 flex items-center gap-2">
                                <Send className="w-4 h-4 text-green-500" />
                                Send Test Email
                            </h4>
                            <p className="text-sm text-gray-500 dark:text-slate-400 mb-4">
                                Send the global template with sample data to verify your email setup works. Uses your selected outgoing mail method.
                            </p>
                            <div className="flex gap-2 items-end">
                                <div className="flex-1">
                                    <label className="block text-xs font-bold text-gray-500 dark:text-slate-500 uppercase mb-1">Recipient email</label>
                                    <input
                                        type="email"
                                        className="w-full px-4 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-900 dark:text-white"
                                        placeholder="you@example.com"
                                        value={testEmailAddress}
                                        onChange={e => { setTestEmailAddress(e.target.value); setTestEmailResult(null); }}
                                    />
                                </div>
                                <button
                                    disabled={sendingTestEmail || !testEmailAddress}
                                    onClick={async () => {
                                        setSendingTestEmail(true);
                                        setTestEmailResult(null);
                                        try {
                                            // Fill template with sample data
                                            let subject = localSettings.templates.reminder.subject;
                                            let body = localSettings.templates.reminder.body;
                                            const sampleData: Record<string, string> = {
                                                '{customer_name}': 'Jan de Vries',
                                                '{service_name}': 'Backup Controle',
                                                '{date}': new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0],
                                                '{company_name}': 'SmartRecur',
                                                '{tech_name}': 'Admin',
                                                '{location_type}': 'On Site',
                                                '{link}': 'https://example.com/confirm',
                                            };
                                            for (const [key, val] of Object.entries(sampleData)) {
                                                subject = subject.split(key).join(val);
                                                body = body.split(key).join(val);
                                            }
                                            subject = '[TEST] ' + subject;

                                            const result = await api.sendEmail({ to: testEmailAddress, subject, body });
                                            setTestEmailResult({ success: true, message: `Test email sent via ${result.method === 'smtp' ? 'SMTP' : 'Office 365'}` });
                                        } catch (e: any) {
                                            setTestEmailResult({ success: false, message: e.message || 'Failed to send test email' });
                                        } finally {
                                            setSendingTestEmail(false);
                                        }
                                    }}
                                    className="bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white px-5 py-2 rounded-lg font-medium flex items-center gap-2 h-[42px]"
                                >
                                    {sendingTestEmail ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
                                    Send Test
                                </button>
                            </div>
                            {testEmailResult && (
                                <div className={`mt-3 p-3 rounded-lg text-sm flex items-center gap-2 ${
                                    testEmailResult.success
                                        ? 'bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300'
                                        : 'bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300'
                                }`}>
                                    {testEmailResult.success
                                        ? <CheckCircle2 className="w-4 h-4 flex-shrink-0" />
                                        : <XCircle className="w-4 h-4 flex-shrink-0" />}
                                    {testEmailResult.message}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* PUSH NOTIFICATIONS Tab */}
                {activeTab === 'PUSH_NOTIFICATIONS' && <PushNotificationsTab />}

                {/* EMAIL LOGS Tab */}
                {activeTab === 'EMAIL_LOGS' && (
                    <div className="space-y-6">
                        <div className="flex justify-between items-center border-b border-gray-200 dark:border-slate-700 pb-4">
                            <h3 className="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                <FileText className="w-6 h-6 text-blue-500" />
                                Email Logs
                            </h3>
                            <button
                                onClick={() => { setLoadingLogs(true); api.getEmailLogs().then(r => setEmailLogs(r.logs || [])).catch(() => {}).finally(() => setLoadingLogs(false)); }}
                                className="text-sm bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-600 dark:text-slate-300 px-3 py-1.5 rounded-lg flex items-center gap-2 border border-gray-200 dark:border-slate-700"
                            >
                                <RefreshCw className={`w-3.5 h-3.5 ${loadingLogs ? 'animate-spin' : ''}`} /> Refresh
                            </button>
                        </div>

                        {/* Pending Reminder Queue */}
                        {pendingReminders.length > 0 && (
                            <div className="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-xl p-4">
                                <h4 className="font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                                    <Bell className="w-4 h-4 text-amber-500" />
                                    Pending Reminders ({pendingReminders.length})
                                </h4>
                                <div className="space-y-2 max-h-[300px] overflow-y-auto custom-scrollbar">
                                    {pendingReminders.map((r, idx) => (
                                        <div key={`${r.eventId}-${r.targetDate}-${idx}`} className="bg-white dark:bg-slate-800 p-3 rounded-lg border border-gray-200 dark:border-slate-700">
                                            <div className="flex items-center justify-between mb-1">
                                                <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded border ${
                                                    r.daysUntil === 0 ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 border-red-200 dark:border-red-800'
                                                    : r.daysUntil <= 1 ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 border-orange-200 dark:border-orange-800'
                                                    : 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800'
                                                }`}>
                                                    {r.daysUntil === 0 ? 'TODAY' : `${r.daysUntil} day${r.daysUntil > 1 ? 's' : ''} out`}
                                                </span>
                                                <span className="text-xs text-gray-500 dark:text-slate-400 font-mono">{r.targetDate}</span>
                                            </div>
                                            <p className="text-sm font-medium text-gray-800 dark:text-slate-200">{r.eventTitle}</p>
                                            <p className="text-xs text-gray-500 dark:text-slate-400">To: {r.customerEmail || r.customerName}</p>
                                            <details className="mt-2">
                                                <summary className="text-xs text-indigo-600 dark:text-indigo-400 cursor-pointer hover:underline">Preview email</summary>
                                                <div className="mt-2 bg-gray-50 dark:bg-slate-900 p-2 rounded border border-gray-200 dark:border-slate-700 text-xs">
                                                    <p className="font-bold text-gray-700 dark:text-slate-300 mb-1">Subject: {r.emailSubject}</p>
                                                    <pre className="text-gray-600 dark:text-slate-400 whitespace-pre-wrap font-mono text-[11px]">{r.emailBody}</pre>
                                                </div>
                                            </details>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Sent Email Logs */}
                        <h4 className="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                            <Mail className="w-4 h-4 text-blue-500" />
                            Sent Emails
                        </h4>

                        {loadingLogs ? (
                            <div className="text-center py-12 text-gray-400 dark:text-slate-500 flex flex-col items-center gap-2">
                                <Loader2 className="w-8 h-8 animate-spin" />
                                Loading logs...
                            </div>
                        ) : emailLogs.length === 0 ? (
                            <div className="text-center py-12 text-gray-400 dark:text-slate-500">
                                <Mail className="w-12 h-12 mx-auto mb-2 opacity-20" />
                                <p>No emails sent yet. Send a test email or reminder to see logs here.</p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto border border-gray-200 dark:border-slate-700 rounded-lg">
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                                    <thead className="bg-gray-50 dark:bg-slate-800">
                                        <tr>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Time</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Recipient</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Subject</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Method</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
                                        {emailLogs.map(log => (
                                            <tr key={log.id} className={log.status === 'failed' ? 'bg-red-50 dark:bg-red-900/10' : ''}>
                                                <td className="px-4 py-3 whitespace-nowrap text-xs text-gray-500 dark:text-slate-400 font-mono">
                                                    {new Date(log.created_at).toLocaleString()}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-slate-300">{log.recipient}</td>
                                                <td className="px-4 py-3 text-sm text-gray-700 dark:text-slate-300 max-w-[250px] truncate">{log.subject}</td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${log.method === 'smtp' ? 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300' : 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'}`}>
                                                        {log.method === 'smtp' ? 'SMTP' : 'Office 365'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {log.status === 'sent' ? (
                                                        <span className="text-xs font-medium text-green-600 dark:text-green-400 flex items-center gap-1">
                                                            <CheckCircle2 className="w-3.5 h-3.5" /> Sent
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs font-medium text-red-600 dark:text-red-400 flex items-center gap-1" title={log.error || 'Unknown error'}>
                                                            <XCircle className="w-3.5 h-3.5" /> Failed
                                                            {log.error && <span className="text-[10px] text-red-400 dark:text-red-500 ml-1 max-w-[150px] truncate">({log.error})</span>}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <p className="text-xs text-gray-400 dark:text-slate-600">Showing last 100 emails. Logs are stored in the database.</p>
                    </div>
                )}

                {/* Other Tabs (Syncro, Techs, Customers) - minimal dark mode style application */}
                {activeTab === 'INTEGRATIONS' && (
                    <div className="space-y-6 max-w-2xl text-gray-800 dark:text-slate-300">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4">Syncro MSP</h3>
                        {/* ... existing content with dark mode classes ... */}
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">API Key</label>
                                <input className="w-full mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white" value={localSettings.integrations.syncroApiKey} onChange={e => setLocalSettings({ ...localSettings, integrations: { ...localSettings.integrations, syncroApiKey: e.target.value } })} type="password" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Subdomain</label>
                                <div className="flex items-center">
                                    <input className="flex-1 mt-1 px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-l-lg text-gray-900 dark:text-white" value={localSettings.integrations.syncroSubdomain} onChange={e => setLocalSettings({ ...localSettings, integrations: { ...localSettings.integrations, syncroSubdomain: e.target.value } })} />
                                    <span className="mt-1 px-4 py-2 bg-gray-200 dark:bg-slate-700 border border-gray-300 dark:border-slate-600 border-l-0 rounded-r-lg text-gray-600 dark:text-slate-300">.syncromsp.com</span>
                                </div>
                            </div>
                            <div className="flex items-center justify-between p-4 bg-gray-100 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700">
                                <div className="flex items-center gap-3">
                                    <div className={`w-3 h-3 rounded-full ${integrationStatus.syncro?.isConnected ? 'bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.6)]' : 'bg-red-500'}`}></div>
                                    <div>
                                        <p className="font-bold text-sm text-gray-800 dark:text-white">{integrationStatus.syncro?.isConnected ? 'Connected' : 'Disconnected'}</p>
                                        {integrationStatus.syncro?.message && (
                                            <p className="text-xs text-gray-500">{integrationStatus.syncro.message}</p>
                                        )}
                                    </div>
                                </div>
                                <button onClick={() => testIntegration('syncro')} disabled={testingIntegration === 'syncro'} className="bg-blue-600 text-white px-4 py-2 rounded text-sm font-medium disabled:opacity-50 flex items-center gap-2">
                                    {testingIntegration === 'syncro' ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                                    Test
                                </button>
                            </div>
                            <button onClick={handleSyncroImport} className="bg-green-600 text-white px-6 py-2 rounded-lg flex items-center gap-2 hover:bg-green-700">
                                <RefreshCw className="w-4 h-4" /> Import Customers
                            </button>
                        </div>
                    </div>
                )}

                {activeTab === 'TECHS' && (
                    <div className="space-y-6">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4">Technicians</h3>
                        <div className="flex gap-4 items-end bg-gray-50 dark:bg-slate-800 p-4 rounded-lg border border-gray-200 dark:border-slate-700">
                            <div className="flex-1">
                                <input className="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white" value={newTech.name} onChange={e => setNewTech({ ...newTech, name: e.target.value })} placeholder="Name" />
                            </div>
                            <div className="flex-1">
                                <input className="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white" value={newTech.email} onChange={e => setNewTech({ ...newTech, email: e.target.value })} placeholder="Email" />
                            </div>
                            <button onClick={handleAddTech} className="bg-primary-600 px-4 py-2 rounded text-white">Add</button>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {technicians.map(t => (
                                <div key={t.id} className="p-4 border border-gray-200 dark:border-slate-700 rounded-lg flex justify-between bg-gray-50 dark:bg-slate-800">
                                    <div className="flex gap-3 items-center">
                                        <div className="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold" style={{ backgroundColor: t.color }}>{t.name.substring(0, 2).toUpperCase()}</div>
                                        <div><h4 className="font-bold text-gray-800 dark:text-white">{t.name}</h4><p className="text-sm text-gray-500 dark:text-slate-400">{t.email}</p></div>
                                    </div>
                                    <button onClick={async () => {
                                        try { await api.deleteTechnician(t.id); } catch {}
                                        onUpdateTechnicians(technicians.filter(x => x.id !== t.id));
                                    }} className="text-red-500"><Trash2 className="w-4 h-4" /></button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {activeTab === 'CUSTOMERS' && (
                    <div className="space-y-6">
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4">Customers</h3>

                        {/* Add Customer Form */}
                        <div className="bg-gray-50 dark:bg-slate-800 p-5 rounded-lg border border-gray-200 dark:border-slate-700">
                            <h4 className="font-bold text-gray-700 dark:text-slate-200 mb-3 text-sm uppercase">Add New Customer</h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Company Name</label>
                                    <input className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white text-sm"
                                        value={newCustomer.company} onChange={e => setNewCustomer({ ...newCustomer, company: e.target.value })} placeholder="Tech Corp BV (leave empty for private)" />
                                </div>
                                <div>
                                    <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Contact Person *</label>
                                    <input className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white text-sm"
                                        value={newCustomer.name} onChange={e => setNewCustomer({ ...newCustomer, name: e.target.value })} placeholder="John Doe" />
                                </div>
                                <div>
                                    <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Email</label>
                                    <input className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white text-sm"
                                        value={newCustomer.email} onChange={e => setNewCustomer({ ...newCustomer, email: e.target.value })} placeholder="john@example.com" />
                                </div>
                                <div>
                                    <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Phone</label>
                                    <input className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white text-sm"
                                        value={newCustomer.phone} onChange={e => setNewCustomer({ ...newCustomer, phone: e.target.value })} placeholder="06-12345678" />
                                </div>
                                <div>
                                    <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Address</label>
                                    <input className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white text-sm"
                                        value={newCustomer.address} onChange={e => setNewCustomer({ ...newCustomer, address: e.target.value })} placeholder="Main St 1" />
                                </div>
                                <div>
                                    <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Postcode</label>
                                    <input className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white text-sm"
                                        value={newCustomer.postcode} onChange={e => setNewCustomer({ ...newCustomer, postcode: e.target.value })} placeholder="1234 AB" />
                                </div>
                            </div>
                            <button onClick={handleAddCustomer} className="bg-primary-600 text-white px-4 py-2 rounded hover:bg-primary-700 text-sm font-bold">Add Customer</button>
                        </div>

                        <div className="overflow-x-auto border border-gray-200 dark:border-slate-700 rounded-lg">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                                <thead className="bg-gray-50 dark:bg-slate-800">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Company</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Contact</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Details</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-slate-400 uppercase">Assets</th>
                                        <th className="px-6 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-slate-900 divide-y divide-gray-200 dark:divide-slate-700">
                                    {customers.map(c => (
                                        <tr key={c.id}>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{c.company || <span className="text-gray-400 dark:text-slate-500 italic">Private</span>}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                <div>{c.name}</div>
                                                <div className="text-xs opacity-75">{c.email}</div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">
                                                <div className="text-xs">{c.phone}</div>
                                                {c.address && <div className="text-xs text-gray-400">{c.address}, {c.postcode}</div>}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-slate-400">{c.assets?.length || 0}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button onClick={async () => {
                                                    try { await api.deleteCustomer(c.id); } catch {}
                                                    onUpdateCustomers(customers.filter(cust => cust.id !== c.id));
                                                }} className="text-red-600 hover:text-red-900 dark:hover:text-red-400"><Trash2 className="w-4 h-4" /></button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* Floating Save Button */}
                <div className="fixed bottom-8 right-8">
                    <button
                        onClick={handleSaveSettings}
                        className="bg-primary-600 text-white p-4 rounded-full shadow-lg hover:bg-primary-700 transition-transform hover:scale-105 flex items-center gap-2 shadow-primary-900/50"
                    >
                        <Save className="w-6 h-6" />
                        <span className="font-bold">Save Changes</span>
                    </button>
                </div>
            </div>
        </div>
    );
};
