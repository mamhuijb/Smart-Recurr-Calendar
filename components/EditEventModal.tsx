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
  event,
  customers,
  services,
  technicians,
  businessHours,
  onSave,
  onDelete,
  onClose,
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

  const timeSlots = useMemo(
    () => generateTimeSlots(businessHours.start, businessHours.end),
    [businessHours.start, businessHours.end]
  );

  const handleSave = async () => {
    setSaving(true);
    const updated: RecurrenceEvent = {
      ...event,
      title,
      customerId,
      serviceId,
      technicianId: technicianId || undefined,
      locationType,
      description,
      generatedDates: dates.sort(),
      startTime,
      endTime,
    };
    onSave(updated);
  };

  const handleRemoveDate = (dateStr: string) => {
    setDates(dates.filter(d => d !== dateStr));
  };

  const handleAddDate = () => {
    if (newDate && !dates.includes(newDate)) {
      setDates([...dates, newDate].sort());
      setNewDate('');
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" onClick={onClose}>
      <div
        className="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-gray-200 dark:border-slate-700 w-full max-w-lg max-h-[90vh] overflow-y-auto"
        onClick={e => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between p-5 border-b border-gray-200 dark:border-slate-700">
          <h2 className="text-lg font-bold text-gray-800 dark:text-white flex items-center gap-2">
            <Calendar className="w-5 h-5 text-indigo-500" />
            Afspraak bewerken
          </h2>
          <button onClick={onClose} className="text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition-colors">
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Body */}
        <div className="p-5 space-y-4">
          {/* Title */}
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-1">Titel</label>
            <input
              className="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-900 dark:text-white text-sm"
              value={title}
              onChange={e => setTitle(e.target.value)}
            />
          </div>

          {/* Customer */}
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-1">Klant</label>
            <select
              className="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-900 dark:text-white text-sm"
              value={customerId}
              onChange={e => setCustomerId(e.target.value)}
            >
              <option value="">Selecteer klant...</option>
              {customers.map(c => (
                <option key={c.id} value={c.id}>{c.company ? `${c.company} (${c.name})` : c.name}</option>
              ))}
            </select>
          </div>

          {/* Service */}
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-1">Service</label>
            <select
              className="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-900 dark:text-white text-sm"
              value={serviceId}
              onChange={e => setServiceId(e.target.value)}
            >
              <option value="">Selecteer service...</option>
              {services.map(s => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </select>
          </div>

          {/* Technician */}
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-1 flex items-center gap-1">
              <UserCog className="w-3.5 h-3.5" /> Technicus
            </label>
            <select
              className="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-900 dark:text-white text-sm"
              value={technicianId}
              onChange={e => setTechnicianId(e.target.value)}
            >
              <option value="">Niet toegewezen</option>
              {technicians.map(t => (
                <option key={t.id} value={t.id}>{t.name}</option>
              ))}
            </select>
          </div>

          {/* Location */}
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-1">Locatie</label>
            <div className="flex gap-2 p-1 bg-gray-100 dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700">
              <button
                onClick={() => setLocationType('ON_SITE')}
                className={`flex-1 flex items-center justify-center gap-2 py-2 rounded-md text-xs font-bold transition-all ${
                  locationType === 'ON_SITE'
                    ? 'bg-white dark:bg-slate-700 shadow text-indigo-600 dark:text-indigo-400'
                    : 'text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300'
                }`}
              >
                <MapPin className="w-3.5 h-3.5" /> Op locatie
              </button>
              <button
                onClick={() => setLocationType('REMOTE')}
                className={`flex-1 flex items-center justify-center gap-2 py-2 rounded-md text-xs font-bold transition-all ${
                  locationType === 'REMOTE'
                    ? 'bg-white dark:bg-slate-700 shadow text-indigo-600 dark:text-indigo-400'
                    : 'text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300'
                }`}
              >
                <Headset className="w-3.5 h-3.5" /> Remote
              </button>
            </div>
          </div>

          {/* Time Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-1 flex items-center gap-1">
              <Clock className="w-3.5 h-3.5" /> Tijdstip
            </label>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs text-gray-500 dark:text-slate-500 mb-1">Starttijd</label>
                <select
                  className="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-900 dark:text-white text-sm"
                  value={startTime}
                  onChange={e => setStartTime(e.target.value)}
                >
                  {timeSlots.map(t => (
                    <option key={t} value={t}>{t}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs text-gray-500 dark:text-slate-500 mb-1">Eindtijd</label>
                <div className="w-full px-3 py-2 bg-gray-100 dark:bg-slate-950 border border-gray-200 dark:border-slate-600 rounded-lg text-gray-500 dark:text-slate-400 text-sm">
                  {endTime}
                  <span className="text-xs text-gray-400 dark:text-slate-600 ml-2">({durationMin} min)</span>
                </div>
              </div>
            </div>
            <p className="text-[10px] text-gray-400 dark:text-slate-600 mt-1">
              Werktijden: {businessHours.start} – {businessHours.end}
            </p>
          </div>

          {/* Description */}
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-1">Notities</label>
            <textarea
              className="w-full px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-900 dark:text-white text-sm h-20 resize-none"
              value={description}
              onChange={e => setDescription(e.target.value)}
              placeholder="Optionele notities..."
            />
          </div>

          {/* Dates */}
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-2">
              Geplande datums ({dates.length})
            </label>
            <div className="max-h-40 overflow-y-auto space-y-1 mb-3 custom-scrollbar">
              {dates.map(d => (
                <div key={d} className="flex items-center justify-between px-3 py-1.5 bg-gray-50 dark:bg-slate-800 rounded border border-gray-200 dark:border-slate-700 text-sm">
                  <span className="font-mono text-gray-700 dark:text-slate-300">{d}</span>
                  <span className="flex items-center gap-2">
                    <span className="text-xs text-gray-400 dark:text-slate-500 capitalize">
                      {new Date(d).toLocaleDateString('nl-NL', { weekday: 'short' })}
                    </span>
                    <button
                      onClick={() => handleRemoveDate(d)}
                      className="text-gray-400 dark:text-slate-600 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                      title="Datum verwijderen"
                    >
                      <XCircle className="w-4 h-4" />
                    </button>
                  </span>
                </div>
              ))}
            </div>
            <div className="flex gap-2">
              <input
                type="date"
                className="flex-1 px-3 py-2 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-900 dark:text-white text-sm"
                value={newDate}
                onChange={e => setNewDate(e.target.value)}
              />
              <button
                onClick={handleAddDate}
                disabled={!newDate}
                className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
              >
                Toevoegen
              </button>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between p-5 border-t border-gray-200 dark:border-slate-700">
          <button
            onClick={() => onDelete(event.id)}
            className="flex items-center gap-2 text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium transition-colors"
          >
            <Trash2 className="w-4 h-4" /> Verwijderen
          </button>
          <div className="flex gap-2">
            <button
              onClick={onClose}
              className="px-4 py-2 text-sm font-medium text-gray-600 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200 bg-gray-100 dark:bg-slate-800 rounded-lg transition-colors"
            >
              Annuleren
            </button>
            <button
              onClick={handleSave}
              disabled={saving || !customerId || !serviceId || dates.length === 0}
              className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg flex items-center gap-2 disabled:opacity-50 transition-colors"
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
