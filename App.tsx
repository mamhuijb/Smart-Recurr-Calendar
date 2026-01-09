
import React, { useState, useEffect } from 'react';
import { RecurrenceEvent, ViewMode, AppSettings, Customer, Service, Technician, DEFAULT_SETTINGS } from './types';
import { EventCreator } from './components/EventCreator';
import { CalendarGrid } from './components/CalendarGrid';
import { ReminderDashboard } from './components/ReminderDashboard';
import { LoginScreen } from './components/LoginScreen';
import { AdminPanel } from './components/AdminPanel';
import { SecureStorage } from './utils/secureStorage';
import { Plus, Calendar as CalendarIcon, Clock, Trash2, LogOut, Settings, Download, MapPin, Headset, Sun, Moon } from 'lucide-react';
import { hexToRgb } from './utils/colorUtils'; // We'll create this helper inline if needed or assume logic here

const App: React.FC = () => {
  // Auth State
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  // App Data State
  const [events, setEvents] = useState<RecurrenceEvent[]>([]);
  const [settings, setSettings] = useState<AppSettings>(DEFAULT_SETTINGS);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [services, setServices] = useState<Service[]>([]);
  const [technicians, setTechnicians] = useState<Technician[]>([]);

  // View State
  const [viewMode, setViewMode] = useState<ViewMode>(ViewMode.CALENDAR);
  const [currentDate, setCurrentDate] = useState(new Date()); 
  const [simulatedDate, setSimulatedDate] = useState(new Date());
  const [initialRule, setInitialRule] = useState<string>('');

  // --- Theme & Branding Engine ---
  useEffect(() => {
    // 1. Handle Dark/Light Mode
    const root = window.document.documentElement;
    if (settings.branding.themeMode === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }

    // 2. Handle Dynamic Primary Color
    // We generated Tailwind shades based on the HEX provided in settings
    const baseColor = settings.branding.primaryColorHex;
    // Simple shade generation logic (just passing the base color for now to variable 600)
    // In a real production app, we would calculate lighter/darker shades. 
    // For V1.0, we just set the main brand color.
    root.style.setProperty('--color-primary-600', baseColor);
    // Setting a mock palette for the other shades to avoid breaking, just fading opacity or similar would be better
    // But for this XML constraint, we rely on the user picking a good color.
    
  }, [settings.branding]);

  // Initialization: Load from SecureStorage
  useEffect(() => {
    const auth = localStorage.getItem('smart_recur_auth');
    if (auth === 'true') setIsAuthenticated(true);

    const loadedEvents = SecureStorage.getItem<RecurrenceEvent[]>('sr_events', []);
    const loadedSettings = SecureStorage.getItem<AppSettings>('sr_settings', DEFAULT_SETTINGS);
    const loadedCustomers = SecureStorage.getItem<Customer[]>('sr_customers', []);
    const loadedServices = SecureStorage.getItem<Service[]>('sr_services', [
        { id: 'srv_1', name: 'General Consultation', type: 'ONE_TIME', defaultDurationMin: 30, color: '#3B82F6', createTicket: false, defaultLocation: 'REMOTE'},
        { id: 'srv_2', name: 'Annual Maintenance', type: 'RECURRING', defaultDurationMin: 120, color: '#10B981', createTicket: true, defaultLocation: 'ON_SITE'}
    ]);
    const loadedTechs = SecureStorage.getItem<Technician[]>('sr_techs', [
        { id: 't1', name: 'Admin Tech', email: 'admin@msp.nl', color: '#6366f1', skills: ['General']}
    ]);

    // Ensure backwards compatibility for new settings fields
    const mergedSettings = { 
        ...DEFAULT_SETTINGS, 
        ...loadedSettings, 
        businessHours: loadedSettings.businessHours || DEFAULT_SETTINGS.businessHours,
        manualClosures: loadedSettings.manualClosures || [],
        branding: loadedSettings.branding || DEFAULT_SETTINGS.branding,
        security: { ...DEFAULT_SETTINGS.security, ...loadedSettings.security },
        integrations: { ...DEFAULT_SETTINGS.integrations, ...loadedSettings.integrations }
    };

    setEvents(loadedEvents);
    setSettings(mergedSettings);
    setCustomers(loadedCustomers);
    setServices(loadedServices);
    setTechnicians(loadedTechs);
  }, []);

  // Persist Data on Change
  useEffect(() => SecureStorage.setItem('sr_events', events), [events]);
  useEffect(() => SecureStorage.setItem('sr_settings', settings), [settings]);
  useEffect(() => SecureStorage.setItem('sr_customers', customers), [customers]);
  useEffect(() => SecureStorage.setItem('sr_services', services), [services]);
  useEffect(() => SecureStorage.setItem('sr_techs', technicians), [technicians]);

  const handleLogin = () => {
      setIsAuthenticated(true);
      localStorage.setItem('smart_recur_auth', 'true');
  };

  const handleLogout = () => {
      setIsAuthenticated(false);
      localStorage.removeItem('smart_recur_auth');
  };

  const handleSaveEvent = (event: RecurrenceEvent) => {
    setEvents([...events, event]);
    setViewMode(ViewMode.CALENDAR);
    setInitialRule('');
  };

  const handleDeleteEvent = (id: string) => {
    if(window.confirm('Are you sure you want to delete this appointment?')) {
        setEvents(events.filter(e => e.id !== id));
    }
  };

  const handleDayClick = (date: Date) => {
      const options: Intl.DateTimeFormatOptions = { month: 'long', day: 'numeric', year: 'numeric' };
      const rule = date.toLocaleDateString('en-US', options); 
      setInitialRule(rule);
      setViewMode(ViewMode.CREATE);
  };

  const toggleTheme = () => {
      setSettings(prev => ({
          ...prev,
          branding: {
              ...prev.branding,
              themeMode: prev.branding.themeMode === 'light' ? 'dark' : 'light'
          }
      }));
  };

  const exportCSV = () => {
      const headers = "ID,Title,Date,Customer,Service,Tech,Location,SyncroTicket\n";
      const rows = events.flatMap(ev => 
          ev.generatedDates.map(date => {
              const cust = customers.find(c => c.id === ev.customerId)?.company || 'Unknown';
              const serv = services.find(s => s.id === ev.serviceId)?.name || 'Unknown';
              const tech = technicians.find(t => t.id === ev.technicianId)?.name || 'Unassigned';
              return `${ev.id},"${ev.title}",${date},"${cust}","${serv}","${tech}","${ev.locationType}","${ev.syncroTicketId || ''}"`;
          })
      ).join("\n");
      
      const blob = new Blob([headers + rows], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `smartrecur_export_${new Date().toISOString().split('T')[0]}.csv`;
      a.click();
  };

  if (!isAuthenticated) {
      return <LoginScreen onLogin={handleLogin} branding={settings.branding} security={settings.security} />;
  }

  return (
    <div className="min-h-screen pb-12 bg-gray-50 dark:bg-slate-950 flex flex-col font-sans text-gray-900 dark:text-slate-200 transition-colors duration-200">
      {/* Header */}
      <header className="bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800 shadow-sm sticky top-0 z-20 backdrop-blur-sm bg-opacity-90 dark:bg-opacity-90">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-col sm:flex-row justify-between items-center gap-4 w-full">
          <div className="flex items-center gap-3">
            <div className="bg-primary-600 text-white p-2 rounded-lg shadow-sm shadow-primary-600/50">
                {settings.branding.logoUrl ? (
                    <img src={settings.branding.logoUrl} className="w-5 h-5 object-contain" alt="Logo" />
                ) : (
                    <CalendarIcon className="w-5 h-5" />
                )}
            </div>
            <div>
              <h1 className="text-lg font-bold text-gray-800 dark:text-slate-100 leading-tight">SmartRecur</h1>
              <p className="text-xs text-gray-500 dark:text-slate-400">MSP Scheduler (v1.0)</p>
            </div>
          </div>
          
          <div className="flex items-center gap-4">
             {viewMode !== ViewMode.ADMIN && (
                 <div className="flex items-center gap-2 bg-gray-100 dark:bg-slate-800 p-1 rounded-lg border border-gray-200 dark:border-slate-700">
                    <button
                        onClick={() => setViewMode(ViewMode.CALENDAR)}
                        className={`px-3 py-1.5 rounded-md text-sm font-medium transition-all ${
                        viewMode === ViewMode.CALENDAR ? 'bg-white dark:bg-primary-600 text-primary-600 dark:text-white shadow' : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
                        }`}
                    >
                        Overview
                    </button>
                    <button
                        onClick={() => {
                            setInitialRule('');
                            setViewMode(ViewMode.CREATE);
                        }}
                        className={`px-3 py-1.5 rounded-md text-sm font-medium flex items-center gap-2 transition-all ${
                        viewMode === ViewMode.CREATE ? 'bg-white dark:bg-primary-600 text-primary-600 dark:text-white shadow' : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
                        }`}
                    >
                        <Plus className="w-3.5 h-3.5" />
                        New
                    </button>
                 </div>
             )}

             <div className="h-6 w-px bg-gray-200 dark:bg-slate-700"></div>
            
             <button onClick={exportCSV} className="text-gray-500 dark:text-slate-500 hover:text-green-600 dark:hover:text-green-400 transition-colors" title="Export CSV">
                 <Download className="w-5 h-5" />
             </button>

             <button 
                onClick={() => setViewMode(ViewMode.ADMIN)} 
                className={`flex items-center gap-2 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors ${viewMode === ViewMode.ADMIN ? 'bg-gray-200 dark:bg-slate-700 text-gray-900 dark:text-white' : 'text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'}`}
             >
                 <Settings className="w-4 h-4" />
                 Admin
             </button>

             <div className="h-6 w-px bg-gray-200 dark:bg-slate-700"></div>

             <button onClick={toggleTheme} className="text-gray-500 dark:text-slate-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors" title="Toggle Theme">
                 {settings.branding.themeMode === 'dark' ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
             </button>

             <button onClick={handleLogout} className="text-gray-500 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 transition-colors" title="Logout">
                 <LogOut className="w-5 h-5" />
             </button>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
        
        {viewMode === ViewMode.ADMIN ? (
            <AdminPanel 
                settings={settings}
                onUpdateSettings={setSettings}
                customers={customers}
                onUpdateCustomers={setCustomers}
                services={services}
                onUpdateServices={setServices}
                technicians={technicians}
                onUpdateTechnicians={setTechnicians}
                events={events}
                onClose={() => setViewMode(ViewMode.CALENDAR)}
            />
        ) : viewMode === ViewMode.CREATE ? (
          <EventCreator 
            initialRule={initialRule}
            customers={customers}
            services={services}
            technicians={technicians}
            onSave={handleSaveEvent} 
            onCancel={() => setViewMode(ViewMode.CALENDAR)} 
          />
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 h-[calc(100vh-140px)]">
            
            {/* Left Column: Calendar */}
            <div className="lg:col-span-8 flex flex-col gap-6 h-full overflow-hidden">
               <div className="flex-1 min-h-[500px]">
                   <CalendarGrid 
                        events={events}
                        technicians={technicians}
                        holidays={settings.holidays}
                        manualClosures={settings.manualClosures}
                        businessHours={settings.businessHours}
                        displayDate={currentDate}
                        onPrevMonth={() => setCurrentDate(new Date(currentDate.setMonth(currentDate.getMonth() - 1)))}
                        onNextMonth={() => setCurrentDate(new Date(currentDate.setMonth(currentDate.getMonth() + 1)))}
                        onDayClick={handleDayClick}
                   />
               </div>

               <div className="bg-white dark:bg-slate-900 rounded-xl shadow-lg border border-gray-200 dark:border-slate-800 p-5 h-[200px] overflow-y-auto custom-scrollbar">
                 <h3 className="text-sm font-bold text-gray-800 dark:text-slate-100 mb-3 sticky top-0 bg-white dark:bg-slate-900 pb-2 border-b border-gray-200 dark:border-slate-800 flex items-center gap-2">
                    <CalendarIcon className="w-4 h-4 text-primary-600 dark:text-primary-500"/> Scheduled Jobs
                 </h3>
                 {events.length === 0 ? (
                   <p className="text-sm text-gray-500 dark:text-slate-500 italic">Click a date on the calendar to schedule an appointment.</p>
                 ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {events.map(event => (
                            <div key={event.id} className="flex justify-between items-start p-3 bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-750 hover:border-gray-300 dark:hover:border-slate-600 rounded-lg border border-gray-200 dark:border-slate-700 transition-all group">
                                <div>
                                    <h4 className="font-semibold text-gray-800 dark:text-slate-200 text-sm">{event.title}</h4>
                                    <p className="text-xs text-primary-600 dark:text-primary-400 font-medium truncate">{event.recurrenceRule}</p>
                                    <div className="flex gap-2 mt-1">
                                        {event.syncroTicketId && (
                                            <span className="text-[10px] bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 px-1.5 py-0.5 rounded border border-green-200 dark:border-green-800 inline-block">
                                                T: {event.syncroTicketId}
                                            </span>
                                        )}
                                        <span className="text-[10px] bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-300 px-1.5 py-0.5 rounded border border-gray-300 dark:border-slate-600 inline-flex items-center gap-1">
                                            {event.locationType === 'ON_SITE' ? <MapPin className="w-2 h-2"/> : <Headset className="w-2 h-2"/>}
                                            {event.locationType === 'ON_SITE' ? 'On Site' : 'Remote'}
                                        </span>
                                    </div>
                                </div>
                                <button 
                                    onClick={() => handleDeleteEvent(event.id)}
                                    className="text-gray-400 dark:text-slate-600 hover:text-red-500 dark:hover:text-red-400 opacity-0 group-hover:opacity-100 transition-all"
                                >
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                 )}
               </div>
            </div>

            {/* Right Column: Simulator & Reminders */}
            <div className="lg:col-span-4 flex flex-col gap-6 h-full">
                <div className="bg-gradient-to-br from-primary-900 to-slate-900 border border-slate-700 text-white rounded-xl shadow-lg p-5">
                    <h3 className="text-base font-bold mb-2 flex items-center gap-2 text-primary-200">
                        <Clock className="w-4 h-4" />
                        Date Simulator
                    </h3>
                    <div className="flex gap-2 items-center">
                        <input 
                            type="date"
                            className="flex-1 px-3 py-1.5 bg-white/10 border border-white/20 rounded text-sm text-white focus:outline-none focus:ring-1 focus:ring-primary-500 color-scheme-dark"
                            value={simulatedDate.toISOString().split('T')[0]}
                            onChange={(e) => {
                                if(e.target.value) setSimulatedDate(new Date(e.target.value));
                            }}
                        />
                         <button 
                            onClick={() => setSimulatedDate(new Date())}
                            className="px-3 py-1.5 text-xs bg-white/10 hover:bg-white/20 rounded border border-white/20 transition-colors"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <div className="flex-1 overflow-hidden">
                    <ReminderDashboard 
                        events={events} 
                        currentDate={simulatedDate} 
                        settings={settings}
                        customers={customers}
                        services={services}
                    />
                </div>
            </div>
          </div>
        )}
      </main>
    </div>
  );
};

export default App;
