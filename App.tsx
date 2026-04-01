
import React, { useState, useEffect } from 'react';
import { RecurrenceEvent, ViewMode, AppSettings, Customer, Service, Technician, DEFAULT_SETTINGS } from './types';
import { EventCreator } from './components/EventCreator';
import { CalendarGrid } from './components/CalendarGrid';
import { ScheduledJobs } from './components/ScheduledJobs';
import { EditEventModal } from './components/EditEventModal';
import { LoginScreen } from './components/LoginScreen';
import { AdminPanel } from './components/AdminPanel';
import { api } from './services/api';
import { Plus, Calendar as CalendarIcon, Trash2, LogOut, Settings, Download, MapPin, Headset, Sun, Moon, Pencil, LayoutGrid } from 'lucide-react';

const App: React.FC = () => {
  const [isAuthenticated, setIsAuthenticated] = useState(api.hasToken());
  const [isLoading, setIsLoading] = useState(true);

  const [events, setEvents] = useState<RecurrenceEvent[]>([]);
  const [settings, setSettings] = useState<AppSettings>(DEFAULT_SETTINGS);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [services, setServices] = useState<Service[]>([]);
  const [technicians, setTechnicians] = useState<Technician[]>([]);

  const [viewMode, setViewMode] = useState<ViewMode>(ViewMode.CALENDAR);
  const [currentDate, setCurrentDate] = useState(new Date());
  const [initialRule, setInitialRule] = useState<string>('');
  const [calendarView, setCalendarView] = useState<'month' | 'week'>('month');
  const [editingEvent, setEditingEvent] = useState<RecurrenceEvent | null>(null);

  // Session timeout check
  useEffect(() => {
    if (!isAuthenticated) return;
    const SESSION_MAX_AGE = 24 * 60 * 60 * 1000;
    const checkSession = () => {
      const ts = parseInt(localStorage.getItem('sr_session_ts') || '0', 10);
      if (ts && Date.now() - ts > SESSION_MAX_AGE) {
        api.clearToken();
        setIsAuthenticated(false);
        setEvents([]); setCustomers([]); setServices([]); setTechnicians([]);
      }
    };
    const interval = setInterval(checkSession, 60000);
    return () => clearInterval(interval);
  }, [isAuthenticated]);

  // Theme & Branding
  useEffect(() => {
    const root = window.document.documentElement;
    if (settings.branding.themeMode === 'dark') {
      root.classList.add('dark');
    } else {
      root.classList.remove('dark');
    }
    root.style.setProperty('--color-primary-600', settings.branding.primaryColorHex);
  }, [settings.branding]);

  // Load data
  useEffect(() => {
    if (!api.hasToken()) { setIsLoading(false); return; }
    const loadData = async () => {
      try {
        const [eventsRes, customersRes, servicesRes, techsRes, settingsRes] = await Promise.all([
          api.getEvents(), api.getCustomers(), api.getServices(), api.getTechnicians(), api.getSettings(),
        ]);
        setEvents(eventsRes.events || []);
        setCustomers(customersRes.customers || []);
        setServices(servicesRes.services || []);
        setTechnicians(techsRes.technicians || []);
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
        api.clearToken();
        setIsAuthenticated(false);
      } finally {
        setIsLoading(false);
      }
    };
    loadData();
  }, [isAuthenticated]);

  const handleLogin = (token: string) => { api.setToken(token); setIsAuthenticated(true); };
  const handleLogout = () => {
    api.clearToken(); setIsAuthenticated(false);
    setEvents([]); setCustomers([]); setServices([]); setTechnicians([]);
  };

  const handleSaveEvent = async (event: RecurrenceEvent) => {
    try {
      await api.createEvent(event);
      setEvents([...events, event]);
      if (settings.office365.calendarSyncEnabled && settings.office365.auth.isConnected) {
        const customer = customers.find(c => c.id === event.customerId);
        const service = services.find(s => s.id === event.serviceId);
        const tech = technicians.find(t => t.id === event.technicianId);
        for (const dateStr of event.generatedDates) {
          const evStart = event.startTime || settings.businessHours.start;
          const evEnd = event.endTime || settings.businessHours.end;
          api.createOffice365Event({
            calendarId: settings.office365.selectedCalendarId || '',
            subject: event.title,
            description: `Customer: ${customer?.company || 'N/A'}\nService: ${service?.name || 'N/A'}\nTechnician: ${tech?.name || 'N/A'}\nLocation: ${event.locationType}`,
            startDateTime: `${dateStr}T${evStart}:00`,
            endDateTime: `${dateStr}T${evEnd}:00`,
          }).catch((err) => {
            console.error('Office 365 sync failed for date ' + dateStr, err);
          });
        }
      }
    } catch (err) {
      console.error('Failed to save event:', err);
    }
    setViewMode(ViewMode.CALENDAR);
    setInitialRule('');
  };

  const handleUpdateEvent = async (event: RecurrenceEvent) => {
    try {
      await api.updateEvent(event.id, event);
      setEvents(events.map(e => e.id === event.id ? event : e));
    } catch (err) {
      console.error('Failed to update event:', err);
    }
    setEditingEvent(null);
  };

  const handleDeleteEvent = async (id: string) => {
    if (window.confirm('Are you sure you want to delete this appointment?')) {
      try {
        await api.deleteEvent(id);
        setEvents(events.filter(e => e.id !== id));
      } catch (err) {
        console.error('Failed to delete event:', err);
      }
      setEditingEvent(null);
    }
  };

  const handleDayClick = (date: Date) => {
    const y = date.getFullYear(), m = String(date.getMonth() + 1).padStart(2, '0'), d = String(date.getDate()).padStart(2, '0');
    setInitialRule(`${y}-${m}-${d}`);
    setViewMode(ViewMode.CREATE);
  };

  const handleEventClick = (event: RecurrenceEvent) => setEditingEvent(event);

  const handlePrev = () => {
    if (calendarView === 'month') {
      setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1));
    } else {
      const d = new Date(currentDate); d.setDate(d.getDate() - 7); setCurrentDate(d);
    }
  };

  const handleNext = () => {
    if (calendarView === 'month') {
      setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1));
    } else {
      const d = new Date(currentDate); d.setDate(d.getDate() + 7); setCurrentDate(d);
    }
  };

  const toggleTheme = () => {
    const newSettings = {
      ...settings,
      branding: { ...settings.branding, themeMode: settings.branding.themeMode === 'light' ? 'dark' as const : 'light' as const }
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

  const handleUpdateSettings = (newSettings: AppSettings) => setSettings(newSettings);
  const handleUpdateCustomers = async (newCustomers: Customer[]) => setCustomers(newCustomers);
  const handleUpdateServices = async (newServices: Service[]) => setServices(newServices);
  const handleUpdateTechnicians = async (newTechnicians: Technician[]) => setTechnicians(newTechnicians);

  // Loading state — refined Apple-style spinner
  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-slate-950 flex items-center justify-center">
        <div className="flex flex-col items-center gap-4 animate-fade-in">
          <div className="w-10 h-10 rounded-2xl bg-primary-600 flex items-center justify-center shadow-lg shadow-primary-600/30">
            <CalendarIcon className="w-5 h-5 text-white" />
          </div>
          <div className="w-6 h-6 border-2 border-primary-500/30 border-t-primary-500 rounded-full animate-spin" />
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <LoginScreen onLogin={handleLogin} branding={settings.branding} />;
  }

  return (
    <div className="min-h-screen bg-gray-50/80 dark:bg-slate-950 flex flex-col font-sans text-gray-900 dark:text-slate-200 transition-colors duration-300">

      {/* ── Header — Apple-style frosted glass navigation ── */}
      <header className="glass border-b border-gray-200/50 dark:border-slate-800/50 sticky top-0 z-30">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 h-14 sm:h-16 flex justify-between items-center gap-3">

          {/* Logo */}
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-600/25 transition-spring hover:shadow-primary-600/40 hover:scale-105">
              {settings.branding.logoUrl ? (
                <img src={settings.branding.logoUrl} className="w-4 h-4 sm:w-5 sm:h-5 object-contain" alt="Logo" />
              ) : (
                <CalendarIcon className="w-4 h-4 sm:w-5 sm:h-5 text-white" />
              )}
            </div>
            <div className="hidden sm:block">
              <h1 className="text-[15px] font-semibold text-gray-900 dark:text-white leading-tight tracking-tight">SmartRecur</h1>
              <p className="text-[11px] text-gray-400 dark:text-slate-500 font-medium">Calendar</p>
            </div>
          </div>

          {/* Center: View switcher — pill-style segmented control */}
          {viewMode !== ViewMode.ADMIN && (
            <div className="flex items-center gap-1 p-1 rounded-xl bg-gray-100/80 dark:bg-slate-800/80 border border-gray-200/50 dark:border-slate-700/50">
              <button
                onClick={() => setViewMode(ViewMode.CALENDAR)}
                className={`px-3 sm:px-4 py-1.5 rounded-lg text-xs sm:text-[13px] font-medium transition-all duration-200 flex items-center gap-1.5 ${
                  viewMode === ViewMode.CALENDAR
                    ? 'bg-white dark:bg-slate-700 text-gray-900 dark:text-white shadow-sm'
                    : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200'
                }`}
              >
                <LayoutGrid className="w-3.5 h-3.5" />
                <span className="hidden sm:inline">Overview</span>
              </button>
              <button
                onClick={() => { setInitialRule(''); setViewMode(ViewMode.CREATE); }}
                className={`px-3 sm:px-4 py-1.5 rounded-lg text-xs sm:text-[13px] font-medium transition-all duration-200 flex items-center gap-1.5 ${
                  viewMode === ViewMode.CREATE
                    ? 'bg-white dark:bg-slate-700 text-gray-900 dark:text-white shadow-sm'
                    : 'text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-slate-200'
                }`}
              >
                <Plus className="w-3.5 h-3.5" />
                <span className="hidden sm:inline">New</span>
              </button>
            </div>
          )}

          {/* Right: Actions */}
          <div className="flex items-center gap-1 sm:gap-2">
            <button onClick={exportCSV} className="hidden sm:flex w-8 h-8 items-center justify-center rounded-lg text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 transition-smooth" title="Export CSV">
              <Download className="w-[18px] h-[18px]" />
            </button>
            <button
              onClick={() => setViewMode(viewMode === ViewMode.ADMIN ? ViewMode.CALENDAR : ViewMode.ADMIN)}
              className={`w-8 h-8 sm:w-auto sm:h-auto sm:px-3 sm:py-1.5 flex items-center justify-center sm:justify-start gap-1.5 rounded-lg text-[13px] font-medium transition-smooth ${
                viewMode === ViewMode.ADMIN
                  ? 'bg-gray-200 dark:bg-slate-700 text-gray-900 dark:text-white'
                  : 'text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800'
              }`}
            >
              <Settings className="w-[18px] h-[18px]" />
              <span className="hidden sm:inline">Admin</span>
            </button>

            <div className="w-px h-5 bg-gray-200 dark:bg-slate-800 mx-0.5"></div>

            <button onClick={toggleTheme} className="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 dark:text-slate-500 hover:text-amber-500 dark:hover:text-amber-400 hover:bg-gray-100 dark:hover:bg-slate-800 transition-smooth" title="Toggle Theme">
              {settings.branding.themeMode === 'dark' ? <Sun className="w-[18px] h-[18px]" /> : <Moon className="w-[18px] h-[18px]" />}
            </button>
            <button onClick={handleLogout} className="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 dark:text-slate-500 hover:text-red-500 dark:hover:text-red-400 hover:bg-gray-100 dark:hover:bg-slate-800 transition-smooth" title="Logout">
              <LogOut className="w-[18px] h-[18px]" />
            </button>
          </div>
        </div>
      </header>

      {/* ── Main Content ── */}
      <main className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6 flex-1 w-full">

        {viewMode === ViewMode.ADMIN ? (
          <div className="animate-fade-in">
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
          </div>
        ) : viewMode === ViewMode.CREATE ? (
          <div className="animate-slide-up">
            <EventCreator
              initialRule={initialRule}
              customers={customers}
              services={services}
              technicians={technicians}
              businessHours={settings.businessHours}
              onSave={handleSaveEvent}
              onCancel={() => setViewMode(ViewMode.CALENDAR)}
            />
          </div>
        ) : (
          <div className="flex flex-col lg:grid lg:grid-cols-12 gap-5 sm:gap-6 lg:h-[calc(100vh-120px)] animate-fade-in">

            {/* Left: Calendar + Appointments List */}
            <div className="lg:col-span-8 flex flex-col gap-5 sm:gap-6 lg:h-full lg:overflow-hidden">
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

              {/* Appointments summary */}
              <div className="surface-raised rounded-2xl p-4 sm:p-5 max-h-[220px] overflow-y-auto">
                <h3 className="text-[13px] font-semibold text-gray-700 dark:text-slate-300 mb-3 sticky top-0 bg-white dark:bg-transparent pb-2 border-b border-gray-100 dark:border-slate-800 flex items-center gap-2">
                  <CalendarIcon className="w-4 h-4 text-primary-500"/> All Appointments ({events.length})
                </h3>
                {events.length === 0 ? (
                  <p className="text-sm text-gray-400 dark:text-slate-500 py-4 text-center">Click a date on the calendar to schedule an appointment.</p>
                ) : (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    {events.map(event => (
                      <div key={event.id} className="flex justify-between items-start p-3 rounded-xl bg-gray-50 dark:bg-slate-800/50 border border-gray-100 dark:border-slate-700/50 hover:border-primary-300 dark:hover:border-primary-600/40 transition-smooth group cursor-pointer hover-lift">
                        <div className="min-w-0 flex-1" onClick={() => handleEventClick(event)}>
                          <h4 className="font-medium text-gray-800 dark:text-slate-200 text-[13px] truncate">{event.title}</h4>
                          <p className="text-[11px] text-primary-500 dark:text-primary-400 font-medium truncate mt-0.5">{event.recurrenceRule}</p>
                          <div className="flex gap-1.5 mt-1.5 flex-wrap">
                            {event.syncroTicketId && (
                              <span className="text-[10px] bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 rounded-md font-medium">
                                T: {event.syncroTicketId}
                              </span>
                            )}
                            <span className="text-[10px] bg-gray-100 dark:bg-slate-700/50 text-gray-500 dark:text-slate-400 px-1.5 py-0.5 rounded-md font-medium inline-flex items-center gap-1">
                              {event.locationType === 'ON_SITE' ? <MapPin className="w-2.5 h-2.5"/> : <Headset className="w-2.5 h-2.5"/>}
                              {event.locationType === 'ON_SITE' ? 'On Site' : 'Remote'}
                            </span>
                          </div>
                        </div>
                        <div className="flex items-center gap-0.5 ml-2 flex-shrink-0">
                          <button onClick={() => handleEventClick(event)} className="w-7 h-7 flex items-center justify-center rounded-lg text-gray-300 dark:text-slate-600 hover:text-primary-500 dark:hover:text-primary-400 sm:opacity-0 sm:group-hover:opacity-100 transition-smooth" title="Edit">
                            <Pencil className="w-3.5 h-3.5" />
                          </button>
                          <button onClick={() => handleDeleteEvent(event.id)} className="w-7 h-7 flex items-center justify-center rounded-lg text-gray-300 dark:text-slate-600 hover:text-red-500 dark:hover:text-red-400 sm:opacity-0 sm:group-hover:opacity-100 transition-smooth" title="Delete">
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>

            {/* Right: Scheduled Jobs */}
            <div className="lg:col-span-4 flex flex-col gap-5 sm:gap-6 lg:h-full">
              <ScheduledJobs
                events={events} customers={customers} services={services}
                technicians={technicians} settings={settings} onEditEvent={handleEventClick}
              />
            </div>
          </div>
        )}
      </main>

      {/* Edit Event Modal */}
      {editingEvent && (
        <EditEventModal
          event={editingEvent} customers={customers} services={services}
          technicians={technicians} businessHours={settings.businessHours}
          onSave={handleUpdateEvent} onDelete={handleDeleteEvent} onClose={() => setEditingEvent(null)}
        />
      )}
    </div>
  );
};

export default App;
