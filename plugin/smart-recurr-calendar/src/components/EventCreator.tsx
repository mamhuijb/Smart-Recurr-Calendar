
import React, { useState, useEffect, useMemo } from 'react';
import { generateRecurrenceDates, getReadableRule, Frequency, Ordinal, DayOfWeek, RecurrenceType } from '../utils/recurrenceEngine';
import { RecurrenceEvent, Customer, Service, Technician, LocationType, BusinessHours } from '../types';
import { CalendarCheck, ArrowLeft, User, Briefcase, UserCog, Ticket, CalendarDays, MapPin, Headset, Clock, Repeat, Calendar } from 'lucide-react';
import { api } from '../services/api';

interface EventCreatorProps {
  initialRule?: string;
  customers: Customer[];
  services: Service[];
  technicians: Technician[];
  businessHours: BusinessHours;
  onSave: (event: RecurrenceEvent) => void;
  onCancel: () => void;
}

function generateTimeSlots(start: string, end: string): string[] {
  const slots: string[] = [];
  const [sh, sm] = start.split(':').map(Number);
  const [eh, em] = end.split(':').map(Number);
  let h = sh, m = sm;
  while (h < eh || (h === eh && m <= em)) {
    slots.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`);
    m += 30;
    if (m >= 60) { h++; m -= 60; }
  }
  return slots;
}

function calcEndTime(startTime: string, durationMin: number): string {
  const [h, m] = startTime.split(':').map(Number);
  const totalMin = h * 60 + m + durationMin;
  const eh = Math.floor(totalMin / 60);
  const em = totalMin % 60;
  return `${String(Math.min(eh, 23)).padStart(2, '0')}:${String(em).padStart(2, '0')}`;
}

export const EventCreator: React.FC<EventCreatorProps> = ({
  initialRule = '', customers, services, technicians, businessHours, onSave, onCancel
}) => {
  const [selectedCustomer, setSelectedCustomer] = useState('');
  const [selectedService, setSelectedService] = useState('');
  const [selectedTech, setSelectedTech] = useState('');
  const [selectedAsset, setSelectedAsset] = useState('');
  const [locationType, setLocationType] = useState<LocationType>('ON_SITE');
  const [startTime, setStartTime] = useState(businessHours.start);
  const [scheduleType, setScheduleType] = useState<'RECURRING' | 'ONE_TIME'>('RECURRING');
  const [singleDate, setSingleDate] = useState<string>('');
  const [freq, setFreq] = useState<Frequency>('YEARLY');
  const [month, setMonth] = useState<number>(9);
  const [patternType, setPatternType] = useState<RecurrenceType>('RELATIVE');
  const [ordinal, setOrdinal] = useState<Ordinal>('2');
  const [dayOfWeek, setDayOfWeek] = useState<DayOfWeek>(2);
  const [dayOfMonth, setDayOfMonth] = useState<number>(1);
  const [previewDates, setPreviewDates] = useState<string[]>([]);
  const [finalRuleDescription, setFinalRuleDescription] = useState('');
  const [error, setError] = useState<string | null>(null);

  const currentCustomer = customers.find(c => c.id === selectedCustomer);
  const currentService = services.find(s => s.id === selectedService);
  const timeSlots = useMemo(() => generateTimeSlots(businessHours.start, businessHours.end), [businessHours.start, businessHours.end]);
  const durationMin = currentService?.defaultDurationMin || 60;
  const endTime = calcEndTime(startTime, durationMin);

  const inputCls = "w-full px-3 py-2 bg-white/60 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 outline-none transition-all";

  useEffect(() => {
    if (currentService) {
      setLocationType(currentService.defaultLocation);
      setScheduleType(currentService.type === 'ONE_TIME' ? 'ONE_TIME' : 'RECURRING');
    }
  }, [selectedService, services]);

  useEffect(() => {
    if (initialRule) {
      const d = new Date(initialRule);
      if (!isNaN(d.getTime())) { setSingleDate(d.toISOString().split('T')[0]); setScheduleType('ONE_TIME'); }
    } else { handleBuilderCalculate(); }
  }, [initialRule]);

  useEffect(() => {
    if (scheduleType === 'ONE_TIME') {
      if (singleDate) { setPreviewDates([singleDate]); setFinalRuleDescription(`One-time appointment on ${singleDate}`); setError(null); }
      else { setPreviewDates([]); setFinalRuleDescription(''); }
    } else { handleBuilderCalculate(); }
  }, [scheduleType, singleDate, freq, month, ordinal, dayOfWeek, dayOfMonth, patternType]);

  const handleBuilderCalculate = () => {
    try {
      const config = { frequency: freq, type: patternType, startMonth: month, ordinal, dayOfWeek, dayOfMonth, yearsToGenerate: 5 };
      setPreviewDates(generateRecurrenceDates(config));
      setFinalRuleDescription(getReadableRule(config));
      setError(null);
    } catch { setError("Calculation error"); }
  };

  const handleConfirm = async () => {
    if (!selectedCustomer || !selectedService || previewDates.length === 0) return;
    let ticketId: string | undefined;
    if (currentService?.createTicket && currentCustomer) {
      try {
        const res = await api.syncroCreateTicket({
          customerId: currentCustomer.syncroId || currentCustomer.id,
          subject: `${currentCustomer.company} - ${currentService.name}`,
          description: `Recurring appointment: ${finalRuleDescription}`,
        });
        ticketId = res.ticketId;
      } catch {}
    }
    onSave({
      id: crypto.randomUUID(),
      title: `${currentCustomer?.company || currentCustomer?.name} - ${currentService?.name}`,
      customerId: selectedCustomer, serviceId: selectedService, technicianId: selectedTech,
      assetId: selectedAsset, syncroTicketId: ticketId, locationType, description: '',
      recurrenceRule: finalRuleDescription, generatedDates: previewDates,
      startTime, endTime, status: 'SCHEDULED', createdAt: Date.now(),
    });
  };

  return (
    <div className="surface-raised rounded-2xl flex flex-col md:flex-row max-w-5xl mx-auto overflow-hidden min-h-[650px]">

      {/* Left Panel */}
      <div className="flex-1 p-6 md:p-8 space-y-5 overflow-y-auto border-r border-gray-100 dark:border-slate-800/60">
        <div className="flex items-center gap-3 mb-2">
          <button onClick={onCancel} className="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 transition-smooth text-gray-400">
            <ArrowLeft className="w-5 h-5" />
          </button>
          <h2 className="text-xl font-bold text-gray-800 dark:text-white flex items-center gap-2 tracking-tight">
            <Briefcase className="w-5 h-5 text-primary-500" />
            New Appointment
          </h2>
        </div>

        <div className="space-y-4">
          {/* Client */}
          <div className="p-4 bg-gray-50/80 dark:bg-slate-800/30 rounded-xl border border-gray-100 dark:border-slate-700/40 space-y-3">
            <h3 className="text-[11px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider flex items-center gap-1.5"><User className="w-3.5 h-3.5"/> Client</h3>
            <select className={inputCls} value={selectedCustomer} onChange={(e) => setSelectedCustomer(e.target.value)}>
              <option value="">Select Customer...</option>
              {customers.map(c => <option key={c.id} value={c.id}>{c.company ? `${c.company} (${c.name})` : c.name}</option>)}
            </select>
            {currentCustomer?.assets && currentCustomer.assets.length > 0 && (
              <select className={inputCls} value={selectedAsset} onChange={(e) => setSelectedAsset(e.target.value)}>
                <option value="">Select Asset (Optional)...</option>
                {currentCustomer.assets.map(a => <option key={a.id} value={a.id}>{a.name} ({a.type})</option>)}
              </select>
            )}
          </div>

          {/* Service & Tech */}
          <div className="p-4 bg-gray-50/80 dark:bg-slate-800/30 rounded-xl border border-gray-100 dark:border-slate-700/40 space-y-3">
            <h3 className="text-[11px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider flex items-center gap-1.5"><Briefcase className="w-3.5 h-3.5"/> Service & Tech</h3>
            <select className={inputCls} value={selectedService} onChange={(e) => setSelectedService(e.target.value)}>
              <option value="">Select Service...</option>
              {services.map(s => <option key={s.id} value={s.id}>{s.name} ({s.type})</option>)}
            </select>

            <div className="flex gap-1.5 p-1 bg-gray-100/80 dark:bg-slate-800/60 rounded-xl border border-gray-200/50 dark:border-slate-700/50">
              <button onClick={() => setLocationType('ON_SITE')} className={`flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-semibold transition-all ${locationType === 'ON_SITE' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary-600 dark:text-primary-400' : 'text-gray-400 hover:text-gray-600'}`}>
                <MapPin className="w-3.5 h-3.5" /> On Location
              </button>
              <button onClick={() => setLocationType('REMOTE')} className={`flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-semibold transition-all ${locationType === 'REMOTE' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary-600 dark:text-primary-400' : 'text-gray-400 hover:text-gray-600'}`}>
                <Headset className="w-3.5 h-3.5" /> Remote
              </button>
            </div>

            <div className="flex items-center gap-2">
              <UserCog className="w-4 h-4 text-gray-300 dark:text-slate-600 flex-shrink-0" />
              <select className={inputCls} value={selectedTech} onChange={(e) => setSelectedTech(e.target.value)}>
                <option value="">Unassigned (Technician)</option>
                {technicians.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
              </select>
            </div>
          </div>

          {/* Time */}
          <div className="p-4 bg-gray-50/80 dark:bg-slate-800/30 rounded-xl border border-gray-100 dark:border-slate-700/40 space-y-3">
            <h3 className="text-[11px] font-bold text-gray-400 dark:text-slate-500 uppercase tracking-wider flex items-center gap-1.5"><Clock className="w-3.5 h-3.5"/> Tijdstip</h3>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-[11px] text-gray-400 mb-1">Starttijd</label>
                <select className={inputCls} value={startTime} onChange={e => setStartTime(e.target.value)}>
                  {timeSlots.map(t => <option key={t} value={t}>{t}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-[11px] text-gray-400 mb-1">Eindtijd</label>
                <div className="w-full px-3 py-2 bg-gray-100/60 dark:bg-slate-900/40 border border-gray-200/50 dark:border-slate-700/50 rounded-xl text-sm text-gray-500 dark:text-slate-400">
                  {endTime} <span className="text-[11px] text-gray-400 ml-1">({durationMin} min)</span>
                </div>
              </div>
            </div>
            <p className="text-[10px] text-gray-400">Werktijden: {businessHours.start} – {businessHours.end}</p>
          </div>

          {currentService?.createTicket && (
            <div className="p-3 bg-emerald-50 dark:bg-emerald-900/10 border border-emerald-200 dark:border-emerald-800/30 rounded-xl flex items-center gap-2 text-xs font-medium text-emerald-600 dark:text-emerald-400">
              <Ticket className="w-4 h-4" />
              SyncroMSP Ticket will be auto-generated.
            </div>
          )}
        </div>
      </div>

      {/* Right Panel: Schedule Builder */}
      <div className="w-full md:w-[480px] bg-gray-50/50 dark:bg-slate-800/20 p-6 md:p-8 flex flex-col">
        <h3 className="text-base font-bold mb-4 flex items-center gap-2 text-gray-800 dark:text-white tracking-tight">
          <CalendarDays className="w-5 h-5 text-primary-500"/>
          Schedule Builder
        </h3>

        {/* Schedule type toggle */}
        <div className="flex p-1 bg-gray-100/80 dark:bg-slate-800/60 rounded-xl mb-5 border border-gray-200/50 dark:border-slate-700/50">
          <button onClick={() => setScheduleType('ONE_TIME')} className={`flex-1 py-2 text-[11px] font-bold uppercase tracking-wider rounded-lg flex items-center justify-center gap-1.5 transition-all ${scheduleType === 'ONE_TIME' ? 'bg-white dark:bg-slate-700 text-gray-800 dark:text-white shadow-sm' : 'text-gray-400 hover:text-gray-600'}`}>
            <Clock className="w-3.5 h-3.5" /> One Time
          </button>
          <button onClick={() => setScheduleType('RECURRING')} className={`flex-1 py-2 text-[11px] font-bold uppercase tracking-wider rounded-lg flex items-center justify-center gap-1.5 transition-all ${scheduleType === 'RECURRING' ? 'bg-white dark:bg-slate-700 text-gray-800 dark:text-white shadow-sm' : 'text-gray-400 hover:text-gray-600'}`}>
            <Repeat className="w-3.5 h-3.5" /> Recurring
          </button>
        </div>

        {scheduleType === 'ONE_TIME' ? (
          <div className="mb-5 animate-fade-in">
            <div className="p-4 bg-primary-50/50 dark:bg-primary-900/10 rounded-xl border border-primary-200/50 dark:border-primary-700/20">
              <label className="text-[11px] font-bold text-primary-500 dark:text-primary-400 uppercase mb-2 block flex items-center gap-1.5">
                <Calendar className="w-3.5 h-3.5" /> Datum
              </label>
              <input type="date" value={singleDate} onChange={e => setSingleDate(e.target.value)} className={inputCls} />
            </div>
          </div>
        ) : (
          <div className="space-y-4 mb-5 animate-fade-in">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="text-[11px] font-bold text-gray-400 dark:text-slate-500 uppercase mb-1.5 block">Frequentie</label>
                <select className={inputCls} value={freq} onChange={e => setFreq(e.target.value as Frequency)}>
                  <option value="YEARLY">Jaarlijks (1x/jr)</option>
                  <option value="HALF_YEARLY">Halfjaarlijks (2x/jr)</option>
                  <option value="QUARTERLY">Per kwartaal (4x/jr)</option>
                  <option value="MONTHLY">Maandelijks (12x/jr)</option>
                </select>
              </div>
              {freq !== 'MONTHLY' && (
                <div>
                  <label className="text-[11px] font-bold text-gray-400 dark:text-slate-500 uppercase mb-1.5 block">
                    {freq === 'YEARLY' ? 'In maand' : 'Start in'}
                  </label>
                  <select className={inputCls} value={month} onChange={e => setMonth(Number(e.target.value))}>
                    {['Januari','Februari','Maart','April','Mei','Juni','Juli','Augustus','September','Oktober','November','December'].map((m, i) => (
                      <option key={m} value={i}>{m}</option>
                    ))}
                  </select>
                </div>
              )}
            </div>

            <div className="p-4 bg-primary-50/30 dark:bg-primary-900/10 rounded-xl border border-primary-200/40 dark:border-primary-700/20">
              <div className="flex justify-center mb-3">
                <div className="inline-flex p-0.5 bg-gray-100/80 dark:bg-slate-800/80 rounded-lg border border-gray-200/50 dark:border-slate-700/50 w-full">
                  <button onClick={() => setPatternType('RELATIVE')} className={`flex-1 px-3 py-1.5 text-[11px] font-bold rounded-md transition-all ${patternType === 'RELATIVE' ? 'bg-white dark:bg-slate-700 text-gray-800 dark:text-white shadow-sm' : 'text-gray-400 hover:text-gray-600'}`}>Weekdag patroon</button>
                  <button onClick={() => setPatternType('ABSOLUTE')} className={`flex-1 px-3 py-1.5 text-[11px] font-bold rounded-md transition-all ${patternType === 'ABSOLUTE' ? 'bg-white dark:bg-slate-700 text-gray-800 dark:text-white shadow-sm' : 'text-gray-400 hover:text-gray-600'}`}>Vaste datum</button>
                </div>
              </div>

              <label className="text-[11px] font-bold text-primary-500 dark:text-primary-400 uppercase mb-2 block text-center">Regel configuratie</label>

              <div className="flex gap-2 items-center justify-center">
                {patternType === 'RELATIVE' ? (
                  <>
                    <span className="text-sm font-medium text-gray-400 whitespace-nowrap">Op de</span>
                    <select className="w-20 px-2 py-1.5 bg-white/60 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-lg text-sm text-gray-900 dark:text-white" value={ordinal} onChange={e => setOrdinal(e.target.value as Ordinal)}>
                      <option value="1">1e</option><option value="2">2e</option><option value="3">3e</option><option value="4">4e</option><option value="last">Laatste</option>
                    </select>
                    <select className="flex-1 px-2 py-1.5 bg-white/60 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-lg text-sm text-gray-900 dark:text-white" value={dayOfWeek} onChange={e => setDayOfWeek(Number(e.target.value) as DayOfWeek)}>
                      <option value={1}>Maandag</option><option value={2}>Dinsdag</option><option value={3}>Woensdag</option><option value={4}>Donderdag</option><option value={5}>Vrijdag</option><option value={6}>Zaterdag</option><option value={0}>Zondag</option>
                    </select>
                  </>
                ) : (
                  <>
                    <span className="text-sm font-medium text-gray-400 whitespace-nowrap">Op de</span>
                    <select className="w-20 px-2 py-1.5 bg-white/60 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-lg text-sm text-gray-900 dark:text-white" value={dayOfMonth} onChange={e => setDayOfMonth(Number(e.target.value))}>
                      {Array.from({length: 31}, (_, i) => i + 1).map(d => <option key={d} value={d}>{d}e</option>)}
                    </select>
                    <span className="text-sm font-medium text-gray-400">dag van de maand</span>
                  </>
                )}
              </div>
              {patternType === 'RELATIVE' && (
                <p className="text-[11px] text-center text-gray-400 mt-2">
                  {freq === 'MONTHLY' ? 'van elke maand' : `van ${['januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'][month]}`}
                </p>
              )}
            </div>
          </div>
        )}

        {/* Preview */}
        <div className="flex-1 bg-white/60 dark:bg-slate-800/20 rounded-xl p-4 overflow-y-auto border border-gray-100 dark:border-slate-700/40">
          <div className="flex justify-between items-center mb-3">
            <h4 className="text-[11px] font-bold text-gray-400 dark:text-slate-500 uppercase">{scheduleType === 'ONE_TIME' ? 'Geselecteerde datum' : 'Vooruitblik (5 jaar)'}</h4>
            <span className="text-[11px] bg-gray-100 dark:bg-slate-800 text-gray-400 px-2 py-0.5 rounded-lg font-medium">{previewDates.length} afspraken</span>
          </div>

          {error ? (
            <p className="text-red-500 text-sm text-center py-4">{error}</p>
          ) : previewDates.length === 0 ? (
            <div className="text-center py-8 text-gray-400">
              <CalendarDays className="w-8 h-8 mx-auto mb-2 opacity-20" />
              <p className="text-sm">Configureer schema om datums te zien</p>
            </div>
          ) : (
            <div className="space-y-1.5">
              {previewDates.map(d => (
                <div key={d} className="flex justify-between items-center text-sm bg-gray-50 dark:bg-slate-800/40 p-2 rounded-lg border border-gray-100 dark:border-slate-700/40">
                  <div className="flex items-center gap-2">
                    <span className="font-mono text-gray-500 dark:text-slate-400 text-[13px]">{d}</span>
                    <span className="text-[11px] text-primary-500 font-medium">{startTime} – {endTime}</span>
                  </div>
                  <span className="font-medium text-gray-600 dark:text-slate-300 capitalize text-[11px]">
                    {new Date(d).toLocaleDateString('nl-NL', { weekday: 'long', day: 'numeric', month: 'short' })}
                  </span>
                </div>
              ))}
            </div>
          )}
        </div>

        <button
          onClick={handleConfirm}
          disabled={!selectedCustomer || !selectedService || previewDates.length === 0}
          className="mt-5 w-full bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-semibold py-3 rounded-xl shadow-lg shadow-primary-600/25 disabled:opacity-50 disabled:shadow-none flex items-center justify-center gap-2 transition-all press-effect"
        >
          <CalendarCheck className="w-5 h-5" />
          Bevestig planning
        </button>
      </div>
    </div>
  );
};
