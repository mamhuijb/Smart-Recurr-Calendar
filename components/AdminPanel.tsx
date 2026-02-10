
import React, { useState, useMemo, useEffect } from 'react';
import { AppSettings, Customer, Service, Technician, RecurrenceEvent } from '../types';
import { Save, Users, Bell, RefreshCw, Briefcase, Key, ShieldCheck, UserCog, BarChart3, MapPin, Headset, PieChart, Clock, Calendar, Lock, Trash2, Palette, Moon, Sun, Database, Download, Upload, CheckCircle2, XCircle, Activity, Smartphone, Loader2 } from 'lucide-react';
import { api } from '../services/api';
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

type Tab = 'REPORTS' | 'BRANDING' | 'OAUTH' | 'INTEGRATIONS' | 'INVOICENINJA' | 'ZOHO' | 'SERVICES' | 'TECHS' | 'CUSTOMERS' | 'BUSINESS' | 'NOTIFICATIONS' | 'BACKUP' | 'SECURITY';

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
            setIntegrationStatus(res.integrations || {});
        }).catch(() => {});
    }, []);
    const [newService, setNewService] = useState<Partial<Service>>({ name: '', type: 'RECURRING', color: '#4F46E5', createTicket: true, defaultLocation: 'ON_SITE' });
    const [newTech, setNewTech] = useState({ name: '', email: '', color: '#10B981' });
    const [newCustomer, setNewCustomer] = useState<Partial<Customer>>({ company: '', name: '', email: '', phone: '', address: '', postcode: '' });

    // State for Service Template Editing
    const [editingServiceId, setEditingServiceId] = useState<string | null>(null);
    const [editingTemplate, setEditingTemplate] = useState<{ subject: string, body: string }>({ subject: '', body: '' });

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
            alert(`Successfully imported ${newCusts.length} new customers from SyncroMSP.`);
        } catch (e: any) {
            alert(`Import failed: ${e.message}`);
        }
    };

    const handleOAuthConnect = async () => {
        try {
            // Save config first
            await api.saveIntegrationConfig('office365', {
                clientId: localSettings.office365.clientId,
                tenantId: localSettings.office365.tenantId,
            });

            const { url } = await api.getOffice365AuthUrl();
            const width = 500, height = 600;
            const left = window.screen.width / 2 - width / 2;
            const top = window.screen.height / 2 - height / 2;
            const popup = window.open(url, 'Office 365 Login', `width=${width},height=${height},top=${top},left=${left}`);

            if (!popup) { alert('Popup blocked. Please allow popups.'); return; }

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
                    alert('Connected to Office 365!');
                } else if (event.data.type === 'OAUTH_ERROR') {
                    window.removeEventListener('message', handler);
                    popup.close();
                    alert(`Auth failed: ${event.data.error}`);
                }
            };
            window.addEventListener('message', handler);
        } catch (e: any) {
            alert(`Auth failed: ${e.message}`);
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
            alert(`Backup failed: ${e.message}`);
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
                alert("Database restored successfully! The application will now reload.");
                window.location.reload();
            } catch (err) {
                alert("Failed to restore backup. Invalid file format.");
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
            }

            const result = await api.testIntegration(type);
            setIntegrationStatus(prev => ({
                ...prev,
                [type]: { isConnected: result.isConnected, lastChecked: new Date().toISOString(), message: result.message }
            }));
            alert(result.message);
        } catch (e: any) {
            setIntegrationStatus(prev => ({
                ...prev,
                [type]: { isConnected: false, lastChecked: new Date().toISOString(), message: e.message }
            }));
            alert(`Connection test failed: ${e.message}`);
        } finally {
            setTestingIntegration(null);
        }
    };

    const handleAddService = () => {
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
        onUpdateServices([...services, s]);
        setNewService({ name: '', type: 'RECURRING', color: '#4F46E5', createTicket: true, defaultLocation: 'ON_SITE' });
    };

    const handleAddTech = () => {
        if (!newTech.name) return;
        const t: Technician = {
            id: crypto.randomUUID(),
            name: newTech.name,
            email: newTech.email,
            color: newTech.color,
            skills: []
        };
        onUpdateTechnicians([...technicians, t]);
        setNewTech({ name: '', email: '', color: '#10B981' });
    }

    const handleAddCustomer = () => {
        if (!newCustomer.company || !newCustomer.name) {
            alert("Company and Contact Name are required.");
            return;
        }
        const c: Customer = {
            id: crypto.randomUUID(),
            company: newCustomer.company!,
            name: newCustomer.name!,
            email: newCustomer.email || '',
            phone: newCustomer.phone || '',
            address: newCustomer.address || '',
            postcode: newCustomer.postcode || '',
            assets: []
        };
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
            });

            // Save integration configs to database
            await api.saveIntegrationConfig('office365', {
                clientId: localSettings.office365.clientId,
                tenantId: localSettings.office365.tenantId,
            });
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

            onUpdateSettings(localSettings);
            alert("Configuration saved.");
        } catch (e: any) {
            alert(`Save failed: ${e.message}`);
        }
    };

    const availableCalendars = [
        { id: 'cal_default', name: 'Calendar (Default)' },
        { id: 'cal_work', name: 'Work' },
        { id: 'cal_personal', name: 'Personal' }
    ];

    return (
        <div className="flex h-full bg-white dark:bg-slate-900 rounded-xl overflow-hidden shadow-2xl border border-gray-200 dark:border-slate-800 text-gray-800 dark:text-slate-300">
            {/* Sidebar */}
            <div className="w-64 bg-gray-50 dark:bg-slate-950 text-gray-500 dark:text-slate-400 flex flex-col border-r border-gray-200 dark:border-slate-800 overflow-y-auto">
                <div className="p-6 border-b border-gray-200 dark:border-slate-800">
                    <h2 className="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
                        <Key className="w-5 h-5 text-primary-600 dark:text-primary-500" />
                        Admin Panel
                    </h2>
                </div>
                <nav className="flex-1 p-4 space-y-2 text-sm">
                    <div className="px-2 pb-1 text-xs font-bold text-gray-400 dark:text-slate-600 uppercase tracking-wider">General</div>
                    <button onClick={() => setActiveTab('REPORTS')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'REPORTS' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <BarChart3 className="w-4 h-4" /> Reporting
                    </button>
                    <button onClick={() => setActiveTab('BRANDING')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'BRANDING' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <Palette className="w-4 h-4" /> Branding & UI
                    </button>
                    <button onClick={() => setActiveTab('SECURITY')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'SECURITY' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <ShieldCheck className="w-4 h-4" /> Security (2FA)
                    </button>

                    <div className="px-2 pb-1 pt-4 text-xs font-bold text-gray-400 dark:text-slate-600 uppercase tracking-wider">Operations</div>
                    <button onClick={() => setActiveTab('CUSTOMERS')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'CUSTOMERS' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <Users className="w-4 h-4" /> Customers
                    </button>
                    <button onClick={() => setActiveTab('SERVICES')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'SERVICES' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <Briefcase className="w-4 h-4" /> Services
                    </button>
                    <button onClick={() => setActiveTab('TECHS')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'TECHS' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <UserCog className="w-4 h-4" /> Technicians
                    </button>
                    <button onClick={() => setActiveTab('BUSINESS')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'BUSINESS' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <Clock className="w-4 h-4" /> Business Hours
                    </button>

                    <div className="px-2 pb-1 pt-4 text-xs font-bold text-gray-400 dark:text-slate-600 uppercase tracking-wider">Integrations</div>
                    <button onClick={() => setActiveTab('OAUTH')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'OAUTH' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <ShieldCheck className="w-4 h-4" /> Office 365
                        {integrationStatus.office365?.isConnected && <CheckCircle2 className="w-3.5 h-3.5 text-green-500 ml-auto flex-shrink-0" />}
                    </button>
                    <button onClick={() => setActiveTab('INTEGRATIONS')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'INTEGRATIONS' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <RefreshCw className="w-4 h-4" /> Syncro MSP
                        {integrationStatus.syncro?.isConnected && <CheckCircle2 className="w-3.5 h-3.5 text-green-500 ml-auto flex-shrink-0" />}
                    </button>
                    <button onClick={() => setActiveTab('INVOICENINJA')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'INVOICENINJA' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <Activity className="w-4 h-4" /> InvoiceNinja
                        {integrationStatus.invoiceninja?.isConnected && <CheckCircle2 className="w-3.5 h-3.5 text-green-500 ml-auto flex-shrink-0" />}
                    </button>
                    <button onClick={() => setActiveTab('ZOHO')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'ZOHO' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <Activity className="w-4 h-4" /> Zoho
                        {integrationStatus.zoho?.isConnected && <CheckCircle2 className="w-3.5 h-3.5 text-green-500 ml-auto flex-shrink-0" />}
                    </button>

                    <div className="px-2 pb-1 pt-4 text-xs font-bold text-gray-400 dark:text-slate-600 uppercase tracking-wider">System</div>
                    <button onClick={() => setActiveTab('NOTIFICATIONS')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'NOTIFICATIONS' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <Bell className="w-4 h-4" /> Global Templates
                    </button>
                    <button onClick={() => setActiveTab('BACKUP')} className={`w-full flex items-center gap-3 px-4 py-2 rounded-lg transition-colors ${activeTab === 'BACKUP' ? 'bg-primary-600 text-white' : 'hover:bg-gray-200 dark:hover:bg-slate-800'}`}>
                        <Database className="w-4 h-4" /> Backup & Restore
                    </button>
                </nav>
                <div className="p-4 border-t border-gray-200 dark:border-slate-800">
                    <button onClick={onClose} className="w-full py-2 text-sm text-center text-gray-500 hover:text-gray-900 dark:text-slate-500 dark:hover:text-white">Exit Admin</button>
                </div>
            </div>

            {/* Content Area */}
            <div className="flex-1 overflow-y-auto bg-white dark:bg-slate-900 p-8">

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

                        <div className="grid grid-cols-1 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Application (Client) ID</label>
                                <input className="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white" value={localSettings.office365.clientId} onChange={e => setLocalSettings({ ...localSettings, office365: { ...localSettings.office365, clientId: e.target.value } })} />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Directory (Tenant) ID</label>
                                <input className="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white" value={localSettings.office365.tenantId} onChange={e => setLocalSettings({ ...localSettings, office365: { ...localSettings.office365, tenantId: e.target.value } })} />
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

                        {/* Calendar Selection (Only shows if connected) */}
                        {localSettings.office365.auth.isConnected && (
                            <div className="animate-in fade-in slide-in-from-top-2">
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-2 flex items-center gap-2"><Calendar className="w-4 h-4" /> Sync Calendar</label>
                                <select
                                    className="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
                                    value={localSettings.office365.selectedCalendarId || ''}
                                    onChange={(e) => setLocalSettings({ ...localSettings, office365: { ...localSettings.office365, selectedCalendarId: e.target.value } })}
                                >
                                    <option value="">Select an Outlook Calendar...</option>
                                    {availableCalendars.map(cal => (
                                        <option key={cal.id} value={cal.id}>{cal.name}</option>
                                    ))}
                                </select>
                                <p className="text-xs text-gray-500 dark:text-slate-500 mt-1">Appointments created in SmartRecur will be synced to this calendar.</p>
                            </div>
                        )}
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
                                                onClick={() => {
                                                    if (editingServiceId === s.id) {
                                                        // Save Template
                                                        const updated = services.map(svc => svc.id === s.id ? { ...svc, emailTemplate: editingTemplate } : svc);
                                                        onUpdateServices(updated);
                                                        setEditingServiceId(null);
                                                    } else {
                                                        // Edit Template
                                                        setEditingServiceId(s.id);
                                                        setEditingTemplate(s.emailTemplate || { subject: '', body: '' });
                                                    }
                                                }}
                                                className={`text-sm px-3 py-1 rounded border ${editingServiceId === s.id ? 'bg-green-600 border-green-500 text-white' : 'border-gray-300 dark:border-slate-600 text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'}`}
                                            >
                                                {editingServiceId === s.id ? 'Save Template' : 'Edit Email Template'}
                                            </button>
                                            <button onClick={() => onUpdateServices(services.filter(x => x.id !== s.id))} className="text-red-500 hover:bg-gray-200 dark:hover:bg-slate-700 p-2 rounded">
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>

                                    {/* Inline Template Editor */}
                                    {editingServiceId === s.id && (
                                        <div className="mt-4 p-4 bg-white dark:bg-slate-900 rounded border border-gray-200 dark:border-slate-700 animate-in fade-in slide-in-from-top-2">
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
                                                <p className="text-xs text-gray-500 dark:text-slate-500">Overrides global template if set.</p>
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
                        <h3 className="text-2xl font-bold text-gray-800 dark:text-white border-b border-gray-200 dark:border-slate-700 pb-4">Global Email Default</h3>
                        <p className="text-sm text-gray-600 dark:text-slate-400">This template is used if a specific service does not have its own template.</p>
                        <div className="space-y-3">
                            <div>
                                <label className="block text-sm font-medium text-gray-600 dark:text-slate-400">Subject</label>
                                <input
                                    className="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white"
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
                                    className="w-full px-4 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg font-mono text-sm h-40 text-gray-900 dark:text-white"
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
                                    <button onClick={() => onUpdateTechnicians(technicians.filter(x => x.id !== t.id))} className="text-red-500"><Trash2 className="w-4 h-4" /></button>
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
                                    <label className="text-xs font-bold text-gray-500 dark:text-slate-500 uppercase">Company Name *</label>
                                    <input className="w-full mt-1 px-3 py-2 bg-white dark:bg-slate-900 border border-gray-300 dark:border-slate-600 rounded text-gray-900 dark:text-white text-sm"
                                        value={newCustomer.company} onChange={e => setNewCustomer({ ...newCustomer, company: e.target.value })} placeholder="Tech Corp BV" />
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
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{c.company}</td>
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
                                                <button onClick={() => onUpdateCustomers(customers.filter(cust => cust.id !== c.id))} className="text-red-600 hover:text-red-900 dark:hover:text-red-400"><Trash2 className="w-4 h-4" /></button>
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
