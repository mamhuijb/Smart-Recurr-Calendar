import React, { useMemo } from 'react';
import { RecurrenceEvent, Customer, Service, Technician, AppSettings } from '../types';
import { Briefcase, Clock, MapPin, Headset, Pencil, CalendarDays } from 'lucide-react';

interface ScheduledJobsProps {
  events: RecurrenceEvent[];
  customers: Customer[];
  services: Service[];
  technicians: Technician[];
  settings: AppSettings;
  onEditEvent: (event: RecurrenceEvent) => void;
}

interface JobOccurrence {
  event: RecurrenceEvent;
  date: string;
  customer?: Customer;
  service?: Service;
  technician?: Technician;
  daysFromNow: number;
}

export const ScheduledJobs: React.FC<ScheduledJobsProps> = ({
  events, customers, services, technicians, settings, onEditEvent,
}) => {
  const upcomingJobs = useMemo(() => {
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const todayTime = today.getTime();
    const jobs: JobOccurrence[] = [];

    events.forEach(event => {
      const customer = customers.find(c => c.id === event.customerId);
      const service = services.find(s => s.id === event.serviceId);
      const technician = technicians.find(t => t.id === event.technicianId);

      event.generatedDates.forEach(dateStr => {
        const eventDate = new Date(dateStr); eventDate.setHours(0, 0, 0, 0);
        const diffDays = Math.round((eventDate.getTime() - todayTime) / (1000 * 60 * 60 * 24));
        if (diffDays >= 0 && diffDays <= 90) {
          jobs.push({ event, date: dateStr, customer, service, technician, daysFromNow: diffDays });
        }
      });
    });

    return jobs.sort((a, b) => a.daysFromNow - b.daysFromNow).slice(0, 30);
  }, [events, customers, services, technicians]);

  const getDayLabel = (days: number) => {
    if (days === 0) return 'Vandaag';
    if (days === 1) return 'Morgen';
    return `${days}d`;
  };

  const getUrgencyStyles = (days: number) => {
    if (days === 0) return 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-200/60 dark:border-red-500/20';
    if (days === 1) return 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-200/60 dark:border-orange-500/20';
    if (days <= 7) return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-200/60 dark:border-amber-500/20';
    return 'bg-gray-100/80 dark:bg-slate-800/50 text-gray-500 dark:text-slate-400 border-gray-200/60 dark:border-slate-700/40';
  };

  return (
    <div className="surface-raised rounded-2xl overflow-hidden h-full flex flex-col">
      {/* Header */}
      <div className="px-4 sm:px-5 py-3.5 border-b border-gray-100/80 dark:border-slate-800/60 flex items-center justify-between">
        <h3 className="font-semibold text-sm text-gray-800 dark:text-slate-100 flex items-center gap-2">
          <Briefcase className="w-4 h-4 text-primary-500" />
          Upcoming
          <span className="text-xs bg-primary-100/60 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 px-2 py-0.5 rounded-lg font-medium">
            {upcomingJobs.length}
          </span>
        </h3>
        <span className="text-[11px] text-gray-400 dark:text-slate-500 font-medium">90 dagen</span>
      </div>

      {/* Job list */}
      <div className="p-3 sm:p-4 space-y-2 flex-1 overflow-y-auto">
        {upcomingJobs.length === 0 ? (
          <div className="text-center py-16 text-gray-400 dark:text-slate-600">
            <CalendarDays className="w-12 h-12 mx-auto mb-3 opacity-15" />
            <p className="text-sm font-medium">Geen aankomende afspraken</p>
            <p className="text-xs mt-1 opacity-60">Klik op een datum om te plannen</p>
          </div>
        ) : (
          upcomingJobs.map((job, idx) => (
            <div
              key={`${job.event.id}-${job.date}-${idx}`}
              onClick={() => onEditEvent(job.event)}
              className="group p-3.5 rounded-xl transition-all duration-200 cursor-pointer
                bg-white/60 dark:bg-slate-800/20
                border border-gray-100/80 dark:border-slate-700/30
                hover:border-primary-200/60 dark:hover:border-primary-600/30
                hover:bg-white dark:hover:bg-slate-800/40
                hover:shadow-md active:scale-[0.99]"
            >
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                  {/* Date badges */}
                  <div className="flex items-center gap-2 mb-2">
                    <span className={`text-[10px] font-bold px-2 py-[3px] rounded-md border ${getUrgencyStyles(job.daysFromNow)}`}>
                      {getDayLabel(job.daysFromNow)}
                    </span>
                    <span className="text-[11px] text-gray-400 dark:text-slate-500 font-mono">{job.date}</span>
                    <span className="text-[11px] text-gray-400 dark:text-slate-500 capitalize">
                      {new Date(job.date).toLocaleDateString('nl-NL', { weekday: 'short' })}
                    </span>
                  </div>

                  {/* Customer & service */}
                  <h4 className="font-semibold text-gray-800 dark:text-slate-200 text-[13px] truncate leading-tight">
                    {job.customer?.company || job.customer?.name || 'Onbekend'}
                  </h4>
                  <p className="text-[11px] text-gray-400 dark:text-slate-500 truncate mt-0.5">{job.service?.name || 'Onbekende service'}</p>

                  {/* Meta info */}
                  <div className="flex items-center gap-3 mt-2 flex-wrap">
                    <span className="inline-flex items-center gap-1 text-[10px] text-gray-400 dark:text-slate-500 bg-gray-50 dark:bg-slate-800/50 px-2 py-0.5 rounded-md">
                      <Clock className="w-3 h-3" />
                      {job.event.startTime || settings.businessHours.start}{job.event.endTime ? ` – ${job.event.endTime}` : ''} ({job.service?.defaultDurationMin || 60}min)
                    </span>
                    <span className="inline-flex items-center gap-1 text-[10px] text-gray-400 dark:text-slate-500">
                      {job.event.locationType === 'ON_SITE' ? (
                        <><MapPin className="w-3 h-3 text-primary-400" /> Op locatie</>
                      ) : (
                        <><Headset className="w-3 h-3 text-emerald-400" /> Remote</>
                      )}
                    </span>
                    {job.technician && (
                      <span className="inline-flex items-center gap-1 text-[10px] text-gray-400 dark:text-slate-500">
                        <span className="w-2 h-2 rounded-full ring-1 ring-white/50 dark:ring-slate-900/50" style={{ backgroundColor: job.technician.color }} />
                        {job.technician.name}
                      </span>
                    )}
                  </div>
                </div>

                {/* Edit button */}
                <button
                  onClick={(e) => { e.stopPropagation(); onEditEvent(job.event); }}
                  className="text-gray-200 dark:text-slate-700 group-hover:text-primary-500 transition-all duration-200 p-1.5 rounded-lg hover:bg-primary-50 dark:hover:bg-primary-900/20 flex-shrink-0"
                  title="Bewerken"
                  aria-label="Edit appointment"
                >
                  <Pencil className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
};
