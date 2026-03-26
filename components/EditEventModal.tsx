import React, { useState, useMemo } from 'react';
import { RecurrenceEvent, Customer, Service, Technician, LocationType, BusinessHours } from '../types';
import { X, Save, Trash2, MapPin, Headset, UserCog, Calendar, XCircle, Clock } from 'lucide-react';

interface EditEventModalProps {
  event: RecurrenceEvent;
  customers: Customer[];
  services: Service[];
  technicians: Technician[];
  businessHours: BusinessHours;
  onSave: (event: RecurrenceEvent) => void;
  onDelete: (id: string) => void;
  onClose: () => void;
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

export const EditEventModal: React.FC<EditEventModalProps> = ({
  event, customers, services, technicians, businessHours, onSave, onDelete, onClose,
}) => {
  const [title, setTitle] = useState(event.title);
  const [customerId, setCustomerId] = useState(event.customerId);
  const [serviceId, setServiceId] = useState(event.serviceId);
  const [technicianId, setTechnicianId] = useState(event.technicianId || '');
  const [locationType, setLocationType] = useState<LocationType>(event.locationType);
  const [description, setDescription] = useState(event.description || '');
  const [dates, setDates] = useState<string[]>([...event.generatedDates]);
  const [newDate, setNewDate] = useState('');
  const [startTime, setStartTime] = useState(event.startTime || businessHours.start);
  const [saving, setSaving] = useState(false);

  const currentService = services.find(s => s.id === serviceId);
  const durationMin = currentService?.defaultDurationMin || 60;
  const endTime = calcEndTime(startTime, durationMin);
  const timeSlots = useMemo(() => generateTimeSlots(businessHours.start, businessHours.end), [businessHours.start, businessHours.end]);

  const handleSave = async () => {
    setSaving(true);
    onSave({ ...event, title, customerId, serviceId, technicianId: technicianId || undefined, locationType, description, generatedDates: dates.sort(), startTime, endTime });
  };

  const handleRemoveDate = (dateStr: string) => setDates(dates.filter(d => d !== dateStr));
  const handleAddDate = () => {
    if (newDate && !dates.includes(newDate)) { setDates([...dates, newDate].sort()); setNewDate(''); }
  };

  const inputCls = "w-full px-3 py-2 bg-gray-50/80 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 outline-none transition-all";

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-md p-4 animate-fade-in" onClick={onClose}>
      <div
        className="glass rounded-2xl shadow-glass-lg dark:shadow-glass-dark-lg border border-white/20 dark:border-slate-700/30 w-full max-w-lg max-h-[90vh] overflow-y-auto animate-scale-in"
        onClick={e => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200/50 dark:border-slate-700/30">
          <h2 className="text-[15px] font-semibold text-gray-800 dark:text-white flex items-center gap-2">
            <Calendar className="w-4.5 h-4.5 text-primary-500" />
            Afspraak bewerken
          </h2>
          <button onClick={onClose} className="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-800 transition-smooth">
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Body */}
        <div className="p-5 space-y-4">
          <div>
            <label className="block text-[13px] font-medium text-gray-500 dark:text-slate-400 mb-1.5">Titel</label>
            <input className={inputCls} value={title} onChange={e => setTitle(e.target.value)} />
          </div>

          <div>
            <label className="block text-[13px] font-medium text-gray-500 dark:text-slate-400 mb-1.5">Klant</label>
            <select className={inputCls} value={customerId} onChange={e => setCustomerId(e.target.value)}>
              <option value="">Selecteer klant...</option>
              {customers.map(c => <option key={c.id} value={c.id}>{c.company ? `${c.company} (${c.name})` : c.name}</option>)}
            </select>
          </div>

          <div>
            <label className="block text-[13px] font-medium text-gray-500 dark:text-slate-400 mb-1.5">Service</label>
            <select className={inputCls} value={serviceId} onChange={e => setServiceId(e.target.value)}>
              <option value="">Selecteer service...</option>
              {services.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </div>

          <div>
            <label className="block text-[13px] font-medium text-gray-500 dark:text-slate-400 mb-1.5 flex items-center gap-1">
              <UserCog className="w-3.5 h-3.5" /> Technicus
            </label>
            <select className={inputCls} value={technicianId} onChange={e => setTechnicianId(e.target.value)}>
              <option value="">Niet toegewezen</option>
              {technicians.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
            </select>
          </div>

          {/* Location toggle */}
          <div>
            <label className="block text-[13px] font-medium text-gray-500 dark:text-slate-400 mb-1.5">Locatie</label>
            <div className="flex gap-1.5 p-1 bg-gray-100/80 dark:bg-slate-800/60 rounded-xl border border-gray-200/50 dark:border-slate-700/50">
              <button
                onClick={() => setLocationType('ON_SITE')}
                className={`flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-semibold transition-all duration-200 ${
                  locationType === 'ON_SITE' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-slate-500 hover:text-gray-600'
                }`}
              >
                <MapPin className="w-3.5 h-3.5" /> Op locatie
              </button>
              <button
                onClick={() => setLocationType('REMOTE')}
                className={`flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg text-xs font-semibold transition-all duration-200 ${
                  locationType === 'REMOTE' ? 'bg-white dark:bg-slate-700 shadow-sm text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-slate-500 hover:text-gray-600'
                }`}
              >
                <Headset className="w-3.5 h-3.5" /> Remote
              </button>
            </div>
          </div>

          {/* Time */}
          <div>
            <label className="block text-[13px] font-medium text-gray-500 dark:text-slate-400 mb-1.5 flex items-center gap-1">
              <Clock className="w-3.5 h-3.5" /> Tijdstip
            </label>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-[11px] text-gray-400 mb-1">Starttijd</label>
                <select className={inputCls} value={startTime} onChange={e => setStartTime(e.target.value)}>
                  {timeSlots.map(t => <option key={t} value={t}>{t}</option>)}
                </select>
              </div>
              <div>
                <label className="block text-[11px] text-gray-400 mb-1">Eindtijd</label>
                <div className="w-full px-3 py-2 bg-gray-100/60 dark:bg-slate-900/40 border border-gray-200/50 dark:border-slate-700/50 rounded-xl text-gray-500 dark:text-slate-400 text-sm">
                  {endTime} <span className="text-[11px] text-gray-400 dark:text-slate-600 ml-1">({durationMin} min)</span>
                </div>
              </div>
            </div>
          </div>

          {/* Notes */}
          <div>
            <label className="block text-[13px] font-medium text-gray-500 dark:text-slate-400 mb-1.5">Notities</label>
            <textarea className={`${inputCls} h-20 resize-none`} value={description} onChange={e => setDescription(e.target.value)} placeholder="Optionele notities..." />
          </div>

          {/* Dates */}
          <div>
            <label className="block text-[13px] font-medium text-gray-500 dark:text-slate-400 mb-2">
              Geplande datums ({dates.length})
            </label>
            <div className="max-h-40 overflow-y-auto space-y-1 mb-3">
              {dates.map(d => (
                <div key={d} className="flex items-center justify-between px-3 py-1.5 bg-gray-50/80 dark:bg-slate-800/40 rounded-lg border border-gray-100 dark:border-slate-700/50 text-sm">
                  <span className="font-mono text-gray-600 dark:text-slate-300 text-[13px]">{d}</span>
                  <span className="flex items-center gap-2">
                    <span className="text-[11px] text-gray-400 dark:text-slate-500 capitalize">
                      {new Date(d).toLocaleDateString('nl-NL', { weekday: 'short' })}
                    </span>
                    <button onClick={() => handleRemoveDate(d)} className="text-gray-300 dark:text-slate-600 hover:text-red-500 dark:hover:text-red-400 transition-colors">
                      <XCircle className="w-4 h-4" />
                    </button>
                  </span>
                </div>
              ))}
            </div>
            <div className="flex gap-2">
              <input type="date" className={`flex-1 ${inputCls}`} value={newDate} onChange={e => setNewDate(e.target.value)} />
              <button onClick={handleAddDate} disabled={!newDate} className="px-4 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 disabled:opacity-50 transition-all press-effect">
                Toevoegen
              </button>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between px-5 py-4 border-t border-gray-200/50 dark:border-slate-700/30">
          <button onClick={() => onDelete(event.id)} className="flex items-center gap-1.5 text-red-500 hover:text-red-600 text-[13px] font-medium transition-colors">
            <Trash2 className="w-4 h-4" /> Verwijderen
          </button>
          <div className="flex gap-2">
            <button onClick={onClose} className="px-4 py-2 text-[13px] font-medium text-gray-500 dark:text-slate-400 hover:text-gray-700 bg-gray-100 dark:bg-slate-800 rounded-xl transition-colors">
              Annuleren
            </button>
            <button
              onClick={handleSave}
              disabled={saving || !customerId || !serviceId || dates.length === 0}
              className="px-4 py-2 text-[13px] font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-xl flex items-center gap-1.5 disabled:opacity-50 transition-all press-effect shadow-sm shadow-primary-600/20"
            >
              <Save className="w-4 h-4" />
              {saving ? 'Opslaan...' : 'Opslaan'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
