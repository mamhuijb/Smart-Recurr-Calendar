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
  events, currentDate, settings, customers, services
}) => {
  const activeReminders = useMemo(() => {
    const reminders: Reminder[] = [];
    const todayStr = currentDate.toISOString().split('T')[0];
    const todayTime = new Date(todayStr).getTime();

    events.forEach(event => {
      const customer = customers.find(c => c.id === event.customerId);
      const service = services.find(s => s.id === event.serviceId);
      if (!customer || !service) return;

      const reminderDays = (service.reminderDays && service.reminderDays.length > 0)
        ? service.reminderDays : (settings.reminders?.days || [14, 7, 1]);

      if (!event.generatedDates || !Array.isArray(event.generatedDates)) return;
      event.generatedDates.forEach(dateStr => {
        const diffDays = Math.ceil((new Date(dateStr).getTime() - todayTime) / (1000 * 60 * 60 * 24));
        if (reminderDays.includes(diffDays) || diffDays === 0) {
          const template = (service.emailTemplate && service.emailTemplate.body) ? service.emailTemplate : settings.templates.reminder;
          let body = template.body;
          body = body.replace('{customer_name}', customer.name);
          body = body.replace('{service_name}', service.name);
          body = body.replace('{date}', dateStr);
          body = body.replace('{company_name}', customer.company);
          reminders.push({ eventId: event.id, eventTitle: event.title, customerName: customer.name || customer.company, targetDate: dateStr, daysUntil: diffDays, emailBody: body });
        }
      });
    });
    return reminders.sort((a, b) => a.daysUntil - b.daysUntil);
  }, [events, currentDate, settings, customers, services]);

  const mailMethodLabel = useMemo(() => {
    const pref = settings.preferredMailMethod || 'auto';
    const smtpReady = !!settings.smtp?.host;
    const o365Ready = !!settings.office365?.auth?.isConnected;
    if (pref === 'smtp' && smtpReady) return 'SMTP';
    if (pref === 'office365' && o365Ready) return 'Office 365';
    if (pref === 'auto') { if (smtpReady) return 'SMTP'; if (o365Ready) return 'Office 365'; }
    return 'Not configured';
  }, [settings]);

  const getReminderColor = (days: number) => {
    if (days === 0) return 'bg-red-50 dark:bg-red-900/10 border-red-300 dark:border-red-600/40';
    if (days === 1) return 'bg-orange-50 dark:bg-orange-900/10 border-orange-300 dark:border-orange-600/40';
    if (days <= 7) return 'bg-amber-50 dark:bg-amber-900/10 border-amber-300 dark:border-amber-600/40';
    return 'bg-blue-50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-600/40';
  };

  return (
    <div className="surface-raised rounded-2xl overflow-hidden h-full flex flex-col">
      <div className="px-4 py-3 border-b border-gray-100 dark:border-slate-800/60 flex items-center justify-between">
        <h3 className="font-semibold text-[13px] text-gray-700 dark:text-slate-200 flex items-center gap-2">
          <Bell className="w-4 h-4 text-primary-500" />
          Queue ({activeReminders.length})
        </h3>
        <span className="text-[11px] text-gray-400 dark:text-slate-500 font-medium">{currentDate.toLocaleDateString()}</span>
      </div>

      <div className="p-3 space-y-2.5 h-[400px] overflow-y-auto">
        {activeReminders.length === 0 ? (
          <div className="text-center py-12 text-gray-400 dark:text-slate-600">
            <CheckCircle2 className="w-10 h-10 mx-auto mb-2 opacity-20" />
            <p className="text-sm">No automated emails scheduled for today.</p>
          </div>
        ) : (
          activeReminders.map((reminder, idx) => (
            <div key={`${reminder.eventId}-${idx}`} className={`p-3.5 rounded-xl border-l-[3px] ${getReminderColor(reminder.daysUntil)}`}>
              <div className="flex justify-between items-start mb-2">
                <span className="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-white/80 dark:bg-slate-900/40 border border-gray-200/50 dark:border-slate-700/50 text-gray-600 dark:text-slate-300">
                  {reminder.daysUntil === 0 ? 'TODAY' : `${reminder.daysUntil} DAYS OUT`}
                </span>
                <span className="text-[11px] text-gray-400 dark:text-slate-500 font-mono">{reminder.targetDate}</span>
              </div>
              <h4 className="font-semibold text-gray-700 dark:text-slate-200 text-[13px]">{reminder.eventTitle}</h4>
              <div className="mt-2.5 bg-white/60 dark:bg-slate-900/30 p-2.5 rounded-lg border border-gray-100 dark:border-slate-800/50 text-[11px] text-gray-500 dark:text-slate-400 font-mono whitespace-pre-wrap leading-relaxed">
                {reminder.emailBody}
              </div>
              <div className="mt-2 flex items-center gap-1.5 text-[10px] text-gray-400 dark:text-slate-500 uppercase font-bold tracking-wider">
                <Mail className="w-3 h-3" /> Via {mailMethodLabel}
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
};
