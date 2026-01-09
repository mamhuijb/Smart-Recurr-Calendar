import React, { useMemo } from 'react';
import { RecurrenceEvent, Reminder, AppSettings, Customer, Service } from '../types';
import { Bell, CheckCircle2, Mail } from 'lucide-react';

interface ReminderDashboardProps {
  events: RecurrenceEvent[];
  currentDate: Date;
  settings: AppSettings;
  customers: Customer[];
  services: Service[];
}

export const ReminderDashboard: React.FC<ReminderDashboardProps> = ({ 
  events, 
  currentDate, 
  settings,
  customers,
  services
}) => {
  const activeReminders = useMemo(() => {
    const reminders: Reminder[] = [];
    const todayStr = currentDate.toISOString().split('T')[0];
    const todayTime = new Date(todayStr).getTime();

    events.forEach(event => {
      // Find linked data
      const customer = customers.find(c => c.id === event.customerId);
      const service = services.find(s => s.id === event.serviceId);
      
      if (!customer || !service) return; // Skip broken links

      event.generatedDates.forEach(dateStr => {
        const eventTime = new Date(dateStr).getTime();
        const diffTime = eventTime - todayTime;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 

        // Check against dynamic rules from settings
        if (settings.reminders.days.includes(diffDays) || diffDays === 0) {
            
            // Compile Email Preview
            // Check for service-specific template, fallback to global
            const template = (service.emailTemplate && service.emailTemplate.body) 
                ? service.emailTemplate 
                : settings.templates.reminder;

            let body = template.body;
            body = body.replace('{customer_name}', customer.name);
            body = body.replace('{service_name}', service.name);
            body = body.replace('{date}', dateStr);
            body = body.replace('{company_name}', customer.company);

            reminders.push({
                eventId: event.id,
                eventTitle: event.title,
                customerName: customer.company,
                targetDate: dateStr,
                daysUntil: diffDays,
                emailBody: body
            });
        }
      });
    });

    return reminders.sort((a, b) => a.daysUntil - b.daysUntil);
  }, [events, currentDate, settings, customers, services]);

  const getReminderColor = (days: number) => {
      if (days === 0) return 'bg-red-900/20 border-red-500';
      if (days === 1) return 'bg-orange-900/20 border-orange-500';
      if (days <= 7) return 'bg-yellow-900/20 border-yellow-500';
      return 'bg-blue-900/20 border-blue-500';
  };

  return (
    <div className="bg-slate-900 rounded-xl shadow-sm border border-slate-800 overflow-hidden h-full flex flex-col">
      <div className="p-4 bg-slate-950 border-b border-slate-800 flex items-center justify-between">
        <h3 className="font-semibold text-slate-100 flex items-center gap-2">
          <Bell className="w-4 h-4 text-indigo-500" />
          Queue ({activeReminders.length})
        </h3>
        <span className="text-xs text-slate-500">
          {currentDate.toLocaleDateString()}
        </span>
      </div>
      
      <div className="p-4 space-y-3 h-[400px] overflow-y-auto custom-scrollbar">
        {activeReminders.length === 0 ? (
          <div className="text-center py-12 text-slate-600">
            <CheckCircle2 className="w-12 h-12 mx-auto mb-2 opacity-20" />
            <p>No automated emails scheduled for today.</p>
          </div>
        ) : (
          activeReminders.map((reminder, idx) => (
            <div 
              key={`${reminder.eventId}-${idx}`}
              className={`p-4 rounded-lg border-l-4 shadow-sm ${getReminderColor(reminder.daysUntil)}`}
            >
              <div className="flex justify-between items-start mb-2">
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-900/50 border border-slate-700 text-slate-300">
                  {reminder.daysUntil === 0 ? 'TODAY' : `${reminder.daysUntil} DAYS OUT`}
                </span>
                <span className="text-xs text-slate-500 font-mono">
                  {reminder.targetDate}
                </span>
              </div>
              <h4 className="font-bold text-slate-200">{reminder.eventTitle}</h4>
              
              <div className="mt-3 bg-slate-950 p-2 rounded border border-slate-800 text-xs text-slate-400 font-mono whitespace-pre-wrap">
                {reminder.emailBody}
              </div>

              <div className="mt-2 flex items-center gap-2 text-[10px] text-slate-500 uppercase font-bold tracking-wider">
                  <Mail className="w-3 h-3" />
                  Sent via {settings.office365.auth.isConnected ? 'Office 365' : 'System Mail'}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
};