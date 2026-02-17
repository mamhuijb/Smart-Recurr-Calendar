
import React, { useState, useEffect } from 'react';
import { RecurrenceEvent, ViewMode, AppSettings, Customer, Service, Technician, DEFAULT_SETTINGS } from './types';
import { EventCreator } from './components/EventCreator';
import { CalendarGrid } from './components/CalendarGrid';
import { ScheduledJobs } from './components/ScheduledJobs';
import { EditEventModal } from './components/EditEventModal';
import { LoginScreen } from './components/LoginScreen';
import { AdminPanel } from './components/AdminPanel';
import { api } from './services/api';
import { Plus, Calendar as CalendarIcon, Clock, Trash2, LogOut, Settings, Download, MapPin, Headset, Sun, Moon, Pencil } from 'lucide-react';

const App: React.FC = () => {
  // Auth State
  const [isAuthenticated, setIsAuthenticated] = useState(api.hasToken());
  const [isLoading, setIsLoading] = useState(true);

  // App Data State
  const [events, setEvents] = useState<RecurrenceEvent[]>([]);
  const [settings, setSettings] = useState<AppSettings>(DEFAULT_SETTINGS);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [services, setServices] = useState<Service[]>([]);
  const [technicians, setTechnicians] = useState<Technician[]>([]);

  // View State
  const [viewMode, setViewMode] = useState<ViewMode>(ViewMode.CALENDAR);
  const [currentDate, setCurrentDate] = useState(new Date());
  const [initialRule, setInitialRule] = useState<string>('');

  // Calendar view state
  const [calendarView, setCalendarView] = useState<'month' | 'week'>('month');

  // Edit modal state
  const [editingEvent, setEditingEvent] = useState<RecurrenceEvent | null>(null);

  // --- Session timeout check (1 day) ---
  useEffect(() => {
    if (!isAuthenticated) return;
    const SESSION_MAX_AGE = 24 * 60 * 60 * 1000;

    const checkSession = () => {
      const ts = parseInt(localStorage.getItem('sr_session_ts') || '0', 10);
      if (ts && Date.now() - ts > SESSION_MAX_AGE) {
        api.clearToken();
        setIsAuthenticated(false);
        setEvents([]);
        setCustomers([]);
        setServices([]);
        setTechnicians([]);
      }
    };

    const interval = setInterval(checkSession, 60000); // Check every minute
    return () => clearInterval(interval);
  }, [isAuthenticated]);

  // --- Theme & Branding Engine ---
  useEffect(() => {
    const root = window.document.documentElement;
    if (settings.branding.themeMode === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
    const baseColor = settings.branding.primaryColorHex;
    root.style.setProperty('--color-primary-600', baseColor);
  }, [settings.branding]);

  // Load data from API on mount
  useEffect(() => {
    if (!api.hasToken()) {
      setIsLoading(false);
      return;
    }

    const loadData = async () => {
      try {
        const [eventsRes, customersRes, servicesRes, techsRes, settingsRes] = await Promise.all([
          api.getEvents(),
          api.getCustomers(),
          api.getServices(),
          api.getTechnicians(),
          api.getSettings(),
        ]);

        setEvents(eventsRes.events || []);
        setCustomers(customersRes.customers || []);
        setServices(servicesRes.services || []);
        setTechnicians(techsRes.technicians || []);

        // Merge API settings with defaults
        const s = settingsRes.settings || {};
        setSettings(prev => ({
          ...prev,
          branding: s.branding || prev.branding,
          security: { ...prev.security, ...s.security },
          reminders: s.reminders || prev.reminders,
          holidays: s.holidays || prev.holidays,
          manualClosures: s.manualClosures || prev.manualClosures,
          businessHours: s.businessHours || prev.businessHours,
          templates: s.templates || prev.templates,
        }));

        setIsAuthenticated(true);
      } catch {
        // Token might be expired
        api.clearToken();
        setIsAuthenticated(false);
      } finally {
        setIsLoading(false);
      }
    };

    loadData();
  }, [isAuthenticated]);

  const handleLogin = (token: string) => {
      api.setToken(token);
      setIsAuthenticated(true);
  };

  const handleLogout = () => {
      api.clearToken();
      setIsAuthenticated(false);
      setEvents([]);
      setCustomers([]);
      setServices([]);
      setTechnicians([]);
  };

  const handleSaveEvent = async (event: RecurrenceEvent) => {
    try {
      await api.createEvent(event);
      setEvents([...events, event]);

      // Sync to Office 365 calendar if enabled
      if (settings.office365.calendarSyncEnabled && settings.office365.auth.isConnected) {
        const customer = customers.find(c => c.id === event.customerId);
        const service = services.find(s => s.id === event.serviceId);
        const tech = technicians.find(t => t.id === event.technicianId);

        for (const dateStr of event.generatedDates) {
          const startTime = `${dateStr}T${settings.businessHours.start}:00`;
          const durationHours = (service?.defaultDurationMin || 60) / 60;
          const endHour = parseInt(settings.businessHours.start.split(':')[0]) + durationHours;
          const endTime = `${dateStr}T${String(Math.floor(endHour)).padStart(2, '0')}:${String(Math.round((endHour % 1) * 60)).padStart(2, '0')}:00`;

          api.createOffice365Event({
            calendarId: settings.office365.selectedCalendarId || '',
            subject: event.title,
            description: `Customer: ${customer?.company || 'N/A'}\nService: ${service?.name || 'N/A'}\nTechnician: ${tech?.name || 'N/A'}\nLocation: ${event.locationType}`,
            startDateTime: startTime,
            endDateTime: endTime,
          }).catch(() => {}); // Don't block on calendar sync failures
        }
      }
    } catch {
      // Still add locally if API fails
      setEvents([...events, event]);
    }
    setViewMode(ViewMode.CALENDAR);
    setInitialRule('');
  };

  const handleUpdateEvent = async (event: RecurrenceEvent) => {
    try {
      await api.updateEvent(event.id, event);
    } catch {
      // Continue with local update even if API fails
    }
    setEvents(events.map(e => e.id === event.id ? event : e));
    setEditingEvent(null);
  };

  const handleDeleteEvent = async (id: string) => {
    if(window.confirm('Are you sure you want to delete this appointment?')) {
        try {
          await api.deleteEvent(id);
        } catch { /* continue anyway */ }
        setEvents(events.filter(e => e.id !== id));
        setEditingEvent(null);
    }
  };

  const handleDayClick = (date: Date) => {
      const options: Intl.DateTimeFormatOptions = { month: 'long', day: 'numeric', year: 'numeric' };
      const rule = date.toLocaleDateString('en-US', options);
      setInitialRule(rule);
      setViewMode(ViewMode.CREATE);
  };

  const handleEventClick = (event: RecurrenceEvent) => {
    setEditingEvent(event);
  };

  // Calendar navigation: month or week depending on view
  const handlePrev = () => {
    if (calendarView === 'month') {
      setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1));
    } else {
      const d = new Date(currentDate);
      d.setDate(d.getDate() - 7);
      setCurrentDate(d);
    }
  };

  const handleNext = () => {
    if (calendarView === 'month') {
      setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1));
    } else {
      const d = new Date(currentDate);
      d.setDate(d.getDate() + 7);
      setCurrentDate(d);
    }
  };

  const toggleTheme = () => {
      const newSettings = {
          ...settings,
          branding: {
              ...settings.branding,
              themeMode: settings.branding.themeMode === 'light' ? 'dark' as const : 'light' as const
          }
      };
      setSettings(newSettings);
      api.updateSettings({ branding: newSettings.branding }).catch(() => {});
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

  // Handle settings updates from AdminPanel and persist to API
  const handleUpdateSettings = (newSettings: AppSettings) => {
    setSettings(newSettings);
  };

  // Handle CRUD updates and persist to API
  const handleUpdateCustomers = async (newCustomers: Customer[]) => {
    setCustomers(newCustomers);
  };

  const handleUpdateServices = async (newServices: Service[]) => {
    setServices(newServices);
  };

  const handleUpdateTechnicians = async (newTechnicians: Technician[]) => {
    setTechnicians(newTechnicians);
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-slate-950 flex items-center justify-center">
        <div className="animate-spin w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full" />
      </div>
    );
  }

  if (!isAuthenticated) {
      return <LoginScreen onLogin={handleLogin} branding={settings.branding} />;
  }

  return (
    <div className="min-h-screen pb-12 bg-gray-50 dark:bg-slate-950 flex flex-col font-sans text-gray-900 dark:text-slate-200 transition-colors duration-200">
      {/* Header */}
      <header className="bg-white dark:bg-slate-900 border-b border-gray-200 dark:border-slate-800 shadow-sm sticky top-0 z-20 backdrop-blur-sm bg-opacity-90 dark:bg-opacity-90">
        <div className="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-2 sm:py-3 flex justify-between items-center gap-2 sm:gap-4 w-full">
          <div className="flex items-center gap-2 sm:gap-3">
            <div className="bg-primary-600 text-white p-1.5 sm:p-2 rounded-lg shadow-sm shadow-primary-600/50">
                {settings.branding.logoUrl ? (
                    <img src={settings.branding.logoUrl} className="w-4 h-4 sm:w-5 sm:h-5 object-contain" alt="Logo" />
                ) : (
                    <CalendarIcon className="w-4 h-4 sm:w-5 sm:h-5" />
                )}
            </div>
            <div>
              <h1 className="text-sm sm:text-lg font-bold text-gray-800 dark:text-slate-100 leading-tight">SmartRecur</h1>
              <p className="text-[10px] sm:text-xs text-gray-500 dark:text-slate-400 hidden sm:block">MSP Scheduler (v2.0)</p>
            </div>
          </div>

          <div className="flex items-center gap-1.5 sm:gap-4">
             {viewMode !== ViewMode.ADMIN && (
                 <div className="flex items-center gap-1 sm:gap-2 bg-gray-100 dark:bg-slate-800 p-0.5 sm:p-1 rounded-lg border border-gray-200 dark:border-slate-700">
                    <button
                        onClick={() => setViewMode(ViewMode.CALENDAR)}
                        className={`px-2 sm:px-3 py-1 sm:py-1.5 rounded-md text-xs sm:text-sm font-medium transition-all ${
                        viewMode === ViewMode.CALENDAR ? 'bg-white dark:bg-primary-600 text-primary-600 dark:text-white shadow' : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
                        }`}
                    >
                        <span className="hidden sm:inline">Overview</span>
                        <CalendarIcon className="w-3.5 h-3.5 sm:hidden" />
                    </button>
                    <button
                        onClick={() => {
                            setInitialRule('');
                            setViewMode(ViewMode.CREATE);
                        }}
                        className={`px-2 sm:px-3 py-1 sm:py-1.5 rounded-md text-xs sm:text-sm font-medium flex items-center gap-1 sm:gap-2 transition-all ${
                        viewMode === ViewMode.CREATE ? 'bg-white dark:bg-primary-600 text-primary-600 dark:text-white shadow' : 'text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
                        }`}
                    >
                        <Plus className="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                        <span className="hidden sm:inline">New</span>
                    </button>
                 </div>
             )}

             <div className="h-5 sm:h-6 w-px bg-gray-200 dark:bg-slate-700 hidden sm:block"></div>

             <button onClick={exportCSV} className="text-gray-500 dark:text-slate-500 hover:text-green-600 dark:hover:text-green-400 transition-colors hidden sm:block" title="Export CSV">
                 <Download className="w-5 h-5" />
             </button>

             <button
                onClick={() => setViewMode(ViewMode.ADMIN)}
                className={`flex items-center gap-1 sm:gap-2 text-xs sm:text-sm font-medium px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg transition-colors ${viewMode === ViewMode.ADMIN ? 'bg-gray-200 dark:bg-slate-700 text-gray-900 dark:text-white' : 'text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white'}`}
             >
                 <Settings className="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                 <span className="hidden sm:inline">Admin</span>
             </button>

             <div className="h-5 sm:h-6 w-px bg-gray-200 dark:bg-slate-700"></div>

             <button onClick={toggleTheme} className="text-gray-500 dark:text-slate-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors" title="Toggle Theme">
                 {settings.branding.themeMode === 'dark' ? <Sun className="w-4 h-4 sm:w-5 sm:h-5" /> : <Moon className="w-4 h-4 sm:w-5 sm:h-5" />}
             </button>

             <button onClick={handleLogout} className="text-gray-500 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 transition-colors" title="Logout">
                 <LogOut className="w-4 h-4 sm:w-5 sm:h-5" />
             </button>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-8 flex-1 w-full">

        {viewMode === ViewMode.ADMIN ? (
            <AdminPanel
                settings={settings}
                onUpdateSettings={handleUpdateSettings}
                customers={customers}
                onUpdateCustomers={handleUpdateCustomers}
                services={services}
                onUpdateServices={handleUpdateServices}
                technicians={technicians}
                onUpdateTechnicians={handleUpdateTechnicians}
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
          <div className="flex flex-col lg:grid lg:grid-cols-12 gap-4 sm:gap-6 lg:gap-8 lg:h-[calc(100vh-140px)]">

            {/* Left Column: Calendar */}
            <div className="lg:col-span-8 flex flex-col gap-4 sm:gap-6 lg:h-full lg:overflow-hidden">
               <div className="flex-1 min-h-[300px] sm:min-h-[400px] lg:min-h-[500px]">
                   <CalendarGrid
                        events={events}
                        technicians={technicians}
                        holidays={settings.holidays}
                        manualClosures={settings.manualClosures}
                        businessHours={settings.businessHours}
                        displayDate={currentDate}
                        calendarView={calendarView}
                        onCalendarViewChange={setCalendarView}
                        onPrev={handlePrev}
                        onNext={handleNext}
                        onDayClick={handleDayClick}
                        onEventClick={handleEventClick}
                   />
               </div>

               <div className="bg-white dark:bg-slate-900 rounded-xl shadow-lg border border-gray-200 dark:border-slate-800 p-3 sm:p-5 max-h-[200px] overflow-y-auto custom-scrollbar">
                 <h3 className="text-xs sm:text-sm font-bold text-gray-800 dark:text-slate-100 mb-2 sm:mb-3 sticky top-0 bg-white dark:bg-slate-900 pb-2 border-b border-gray-200 dark:border-slate-800 flex items-center gap-2">
                    <CalendarIcon className="w-3.5 h-3.5 sm:w-4 sm:h-4 text-primary-600 dark:text-primary-500"/> All Appointments ({events.length})
                 </h3>
                 {events.length === 0 ? (
                   <p className="text-xs sm:text-sm text-gray-500 dark:text-slate-500 italic">Click a date on the calendar to schedule an appointment.</p>
                 ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                        {events.map(event => (
                            <div key={event.id} className="flex justify-between items-start p-2 sm:p-3 bg-gray-50 dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-750 hover:border-gray-300 dark:hover:border-slate-600 rounded-lg border border-gray-200 dark:border-slate-700 transition-all group">
                                <div className="min-w-0 flex-1 cursor-pointer" onClick={() => handleEventClick(event)}>
                                    <h4 className="font-semibold text-gray-800 dark:text-slate-200 text-xs sm:text-sm truncate">{event.title}</h4>
                                    <p className="text-[10px] sm:text-xs text-primary-600 dark:text-primary-400 font-medium truncate">{event.recurrenceRule}</p>
                                    <div className="flex gap-1 sm:gap-2 mt-1 flex-wrap">
                                        {event.syncroTicketId && (
                                            <span className="text-[9px] sm:text-[10px] bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 px-1 sm:px-1.5 py-0.5 rounded border border-green-200 dark:border-green-800 inline-block">
                                                T: {event.syncroTicketId}
                                            </span>
                                        )}
                                        <span className="text-[9px] sm:text-[10px] bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-slate-300 px-1 sm:px-1.5 py-0.5 rounded border border-gray-300 dark:border-slate-600 inline-flex items-center gap-0.5 sm:gap-1">
                                            {event.locationType === 'ON_SITE' ? <MapPin className="w-2 h-2"/> : <Headset className="w-2 h-2"/>}
                                            {event.locationType === 'ON_SITE' ? 'On Site' : 'Remote'}
                                        </span>
                                    </div>
                                </div>
                                <div className="flex items-center gap-1 ml-1 flex-shrink-0">
                                    <button
                                        onClick={() => handleEventClick(event)}
                                        className="text-gray-400 dark:text-slate-600 hover:text-indigo-500 dark:hover:text-indigo-400 sm:opacity-0 sm:group-hover:opacity-100 transition-all"
                                        title="Edit"
                                    >
                                        <Pencil className="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                                    </button>
                                    <button
                                        onClick={() => handleDeleteEvent(event.id)}
                                        className="text-gray-400 dark:text-slate-600 hover:text-red-500 dark:hover:text-red-400 sm:opacity-0 sm:group-hover:opacity-100 transition-all"
                                        title="Delete"
                                    >
                                        <Trash2 className="w-3.5 h-3.5 sm:w-4 sm:h-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                 )}
               </div>
            </div>

            {/* Right Column: Scheduled Jobs */}
            <div className="lg:col-span-4 flex flex-col gap-4 sm:gap-6 lg:h-full">
                <ScheduledJobs
                    events={events}
                    customers={customers}
                    services={services}
                    technicians={technicians}
                    settings={settings}
                    onEditEvent={handleEventClick}
                />
            </div>
          </div>
        )}
      </main>

      {/* Edit Event Modal */}
      {editingEvent && (
        <EditEventModal
          event={editingEvent}
          customers={customers}
          services={services}
          technicians={technicians}
          onSave={handleUpdateEvent}
          onDelete={handleDeleteEvent}
          onClose={() => setEditingEvent(null)}
        />
      )}
    </div>
  );
};

export default App;
