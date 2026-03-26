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
    if (days === 0) return 'Today';
    if (days === 1) return 'Tomorrow';
    return `${days}d`;
  };

  const getUrgencyColor = (days: number) => {
    if (days === 0) return 'bg-red-50 dark:bg-red-900/15 text-red-500 border-red-200 dark:border-red-700/30';
    if (days === 1) return 'bg-orange-50 dark:bg-orange-900/15 text-orange-500 border-orange-200 dark:border-orange-700/30';
    if (days <= 7) return 'bg-amber-50 dark:bg-amber-900/15 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-700/30';
    return 'bg-gray-100 dark:bg-slate-800/50 text-gray-500 dark:text-slate-400 border-gray-200 dark:border-slate-700';
  };

  return (
    <div className="surface-raised rounded-2xl overflow-hidden h-full flex flex-col">
      <div className="px-4 py-3 border-b border-gray-100 dark:border-slate-800/60 flex items-center justify-between">
        <h3 className="font-semibold text-[13px] text-gray-700 dark:text-slate-200 flex items-center gap-2">
          <Briefcase className="w-4 h-4 text-primary-500" />
          Upcoming ({upcomingJobs.length})
        </h3>
        <span className="text-[11px] text-gray-400 dark:text-slate-500 font-medium">Next 90 days</span>
      </div>

      <div className="p-3 space-y-2 flex-1 overflow-y-auto">
        {upcomingJobs.length === 0 ? (
          <div className="text-center py-12 text-gray-400 dark:text-slate-600">
            <CalendarDays className="w-10 h-10 mx-auto mb-2 opacity-20" />
            <p className="text-sm">No upcoming appointments.</p>
          </div>
        ) : (
          upcomingJobs.map((job, idx) => (
            <div
              key={`${job.event.id}-${job.date}-${idx}`}
              onClick={() => onEditEvent(job.event)}
              className="p-3 rounded-xl bg-gray-50/80 dark:bg-slate-800/30 border border-gray-100 dark:border-slate-700/40 hover:border-primary-200 dark:hover:border-primary-600/30 cursor-pointer transition-all group hover-lift"
            >
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 mb-1.5">
                    <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded-md border ${getUrgencyColor(job.daysFromNow)}`}>
                      {getDayLabel(job.daysFromNow)}
                    </span>
                    <span className="text-[11px] text-gray-400 dark:text-slate-500 font-mono">{job.date}</span>
                    <span className="text-[11px] text-gray-400 dark:text-slate-500 capitalize">
                      {new Date(job.date).toLocaleDateString('nl-NL', { weekday: 'short' })}
                    </span>
                  </div>

                  <h4 className="font-semibold text-gray-700 dark:text-slate-200 text-[13px] truncate">
                    {job.customer?.company || job.customer?.name || 'Unknown'}
                  </h4>
                  <p className="text-[11px] text-gray-400 dark:text-slate-500 truncate">{job.service?.name || 'Unknown service'}</p>

                  <div className="flex items-center gap-3 mt-1.5 flex-wrap">
                    <span className="text-[10px] text-gray-400 dark:text-slate-500 flex items-center gap-1">
                      <Clock className="w-3 h-3" />
                      {job.event.startTime || settings.businessHours.start} – {job.event.endTime || ''} ({job.service?.defaultDurationMin || 60}min)
                    </span>
                    <span className="text-[10px] text-gray-400 dark:text-slate-500 flex items-center gap-1">
                      {job.event.locationType === 'ON_SITE' ? (
                        <><MapPin className="w-3 h-3 text-primary-400" /> On Site</>
                      ) : (
                        <><Headset className="w-3 h-3 text-emerald-400" /> Remote</>
                      )}
                    </span>
                    {job.technician && (
                      <span className="text-[10px] text-gray-400 dark:text-slate-500 flex items-center gap-1">
                        <span className="w-2 h-2 rounded-full" style={{ backgroundColor: job.technician.color }} />
                        {job.technician.name}
                      </span>
                    )}
                  </div>
                </div>

                <button
                  onClick={(e) => { e.stopPropagation(); onEditEvent(job.event); }}
                  className="text-gray-300 dark:text-slate-600 group-hover:text-primary-500 transition-colors p-1 flex-shrink-0"
                  title="Edit appointment"
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
