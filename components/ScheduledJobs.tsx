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
  events,
  customers,
  services,
  technicians,
  settings,
  onEditEvent,
}) => {
  const upcomingJobs = useMemo(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const todayTime = today.getTime();

    const jobs: JobOccurrence[] = [];

    events.forEach(event => {
      const customer = customers.find(c => c.id === event.customerId);
      const service = services.find(s => s.id === event.serviceId);
      const technician = technicians.find(t => t.id === event.technicianId);

      event.generatedDates.forEach(dateStr => {
        const eventDate = new Date(dateStr);
        eventDate.setHours(0, 0, 0, 0);
        const diffDays = Math.round((eventDate.getTime() - todayTime) / (1000 * 60 * 60 * 24));

        // Show upcoming jobs (from today up to 90 days out)
        if (diffDays >= 0 && diffDays <= 90) {
          jobs.push({
            event,
            date: dateStr,
            customer,
            service,
            technician,
            daysFromNow: diffDays,
          });
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
    if (days === 0) return 'bg-red-500/20 text-red-400 border-red-500/40';
    if (days === 1) return 'bg-orange-500/20 text-orange-400 border-orange-500/40';
    if (days <= 7) return 'bg-yellow-500/20 text-yellow-400 border-yellow-500/40';
    return 'bg-slate-700/50 text-slate-400 border-slate-600';
  };

  return (
    <div className="bg-slate-900 rounded-xl shadow-sm border border-slate-800 overflow-hidden h-full flex flex-col">
      <div className="p-4 bg-slate-950 border-b border-slate-800 flex items-center justify-between">
        <h3 className="font-semibold text-slate-100 flex items-center gap-2">
          <Briefcase className="w-4 h-4 text-indigo-500" />
          Upcoming ({upcomingJobs.length})
        </h3>
        <span className="text-xs text-slate-500">
          Next 90 days
        </span>
      </div>

      <div className="p-3 space-y-2 flex-1 overflow-y-auto custom-scrollbar">
        {upcomingJobs.length === 0 ? (
          <div className="text-center py-12 text-slate-600">
            <CalendarDays className="w-12 h-12 mx-auto mb-2 opacity-20" />
            <p className="text-sm">No upcoming appointments.</p>
          </div>
        ) : (
          upcomingJobs.map((job, idx) => (
            <div
              key={`${job.event.id}-${job.date}-${idx}`}
              onClick={() => onEditEvent(job.event)}
              className="p-3 rounded-lg bg-slate-800 border border-slate-700 hover:border-indigo-500/50 cursor-pointer transition-all group"
            >
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                  {/* Date row */}
                  <div className="flex items-center gap-2 mb-1.5">
                    <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded border ${getUrgencyColor(job.daysFromNow)}`}>
                      {getDayLabel(job.daysFromNow)}
                    </span>
                    <span className="text-xs text-slate-400 font-mono">{job.date}</span>
                    <span className="text-xs text-slate-500 capitalize">
                      {new Date(job.date).toLocaleDateString('nl-NL', { weekday: 'short' })}
                    </span>
                  </div>

                  {/* Title */}
                  <h4 className="font-semibold text-slate-200 text-sm truncate">
                    {job.customer?.company || job.customer?.name || 'Unknown'}
                  </h4>
                  <p className="text-xs text-slate-400 truncate">{job.service?.name || 'Unknown service'}</p>

                  {/* Details row */}
                  <div className="flex items-center gap-3 mt-1.5 flex-wrap">
                    <span className="text-[10px] text-slate-500 flex items-center gap-1">
                      <Clock className="w-3 h-3" />
                      {job.event.startTime || settings.businessHours.start} – {job.event.endTime || ''} ({job.service?.defaultDurationMin || 60}min)
                    </span>
                    <span className="text-[10px] text-slate-500 flex items-center gap-1">
                      {job.event.locationType === 'ON_SITE' ? (
                        <><MapPin className="w-3 h-3 text-indigo-400" /> On Site</>
                      ) : (
                        <><Headset className="w-3 h-3 text-emerald-400" /> Remote</>
                      )}
                    </span>
                    {job.technician && (
                      <span className="text-[10px] text-slate-500 flex items-center gap-1">
                        <span className="w-2 h-2 rounded-full" style={{ backgroundColor: job.technician.color }} />
                        {job.technician.name}
                      </span>
                    )}
                  </div>
                </div>

                <button
                  onClick={(e) => { e.stopPropagation(); onEditEvent(job.event); }}
                  className="text-slate-600 group-hover:text-indigo-400 transition-colors p-1 flex-shrink-0"
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
