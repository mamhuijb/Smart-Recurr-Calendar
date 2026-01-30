import React from 'react';
import { RecurrenceEvent, Technician, BusinessHours } from '../types';
import { ChevronLeft, ChevronRight, Plus, AlertCircle, MapPin, Headphones, Lock } from 'lucide-react';

interface CalendarGridProps {
  events: RecurrenceEvent[];
  technicians: Technician[];
  displayDate: Date;
  holidays: string[];
  manualClosures?: string[];
  businessHours?: BusinessHours;
  onPrevMonth: () => void;
  onNextMonth: () => void;
  onDayClick: (date: Date) => void;
}

export const CalendarGrid: React.FC<CalendarGridProps> = ({ 
  events, 
  technicians, 
  displayDate, 
  holidays,
  manualClosures = [],
  businessHours = { start: '09:00', end: '17:00', closedDays: [0] },
  onPrevMonth, 
  onNextMonth, 
  onDayClick 
}) => {
  const getDaysInMonth = (year: number, month: number) => new Date(year, month + 1, 0).getDate();
  const getFirstDayOfMonth = (year: number, month: number) => {
      // JS getDay(): 0=Sun, 1=Mon ... 6=Sat
      // We want Mon=0 ... Sun=6
      const day = new Date(year, month, 1).getDay();
      return (day + 6) % 7;
  };

  const locale = 'nl-NL';
  const year = displayDate.getFullYear();
  const month = displayDate.getMonth();
  const daysInMonth = getDaysInMonth(year, month);
  const startDay = getFirstDayOfMonth(year, month);
  
  const days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
  const padding = Array.from({ length: startDay }, (_, i) => i);

  const getEventsForDay = (day: number) => {
    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    return events.filter(e => e.generatedDates.includes(dateStr));
  };

  const checkStatus = (day: number) => {
      const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
      const jsDay = new Date(year, month, day).getDay(); // 0=Sun
      
      const isHoliday = holidays.includes(dateStr);
      const isManualClosed = manualClosures.includes(dateStr);
      const isClosedDay = businessHours.closedDays.includes(jsDay);

      return { isHoliday, isManualClosed, isClosedDay };
  }

  const getTechColor = (id?: string) => {
      const t = technicians.find(tech => tech.id === id);
      return t ? t.color : '#475569';
  }

  // Weekdays header starting Monday
  const weekDays = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];

  return (
    <div className="bg-slate-900 rounded-xl shadow-lg border border-slate-800 p-6 h-full flex flex-col">
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-xl font-bold text-slate-100 capitalize flex items-center gap-3">
          {displayDate.toLocaleDateString(locale, { month: 'long', year: 'numeric' })}
          <span className="text-xs bg-slate-800 text-slate-400 px-2 py-1 rounded border border-slate-700 font-normal">
              {businessHours.start} - {businessHours.end}
          </span>
        </h2>
        <div className="flex gap-2">
          <button onClick={onPrevMonth} className="p-2 hover:bg-slate-800 rounded-full transition-colors text-slate-400 hover:text-white">
            <ChevronLeft className="w-5 h-5" />
          </button>
          <button onClick={onNextMonth} className="p-2 hover:bg-slate-800 rounded-full transition-colors text-slate-400 hover:text-white">
            <ChevronRight className="w-5 h-5" />
          </button>
        </div>
      </div>

      <div className="grid grid-cols-7 gap-1 mb-2">
        {weekDays.map(d => (
          <div key={d} className="text-center text-xs font-bold text-slate-500 uppercase tracking-wider py-2">
            {d}
          </div>
        ))}
      </div>

      <div className="grid grid-cols-7 gap-1 sm:gap-2 flex-1">
        {padding.map((_, i) => (
          <div key={`pad-${i}`} className="aspect-square bg-slate-950/50 rounded-lg border border-transparent"></div>
        ))}
        {days.map(day => {
          const dayEvents = getEventsForDay(day);
          const currentDayDate = new Date(year, month, day);
          const isToday = new Date().toDateString() === currentDayDate.toDateString();
          const { isHoliday, isManualClosed, isClosedDay } = checkStatus(day);
          const isClosed = isHoliday || isManualClosed || isClosedDay;
          
          return (
            <div 
              key={day} 
              onClick={() => !isClosed && onDayClick(currentDayDate)}
              className={`group relative aspect-square p-1 sm:p-2 border rounded-lg flex flex-col items-start transition-all 
                ${isClosed 
                    ? 'bg-slate-950/50 border-slate-800 cursor-not-allowed opacity-60' 
                    : 'bg-slate-800 border-slate-700 cursor-pointer hover:border-indigo-500 hover:shadow-md hover:shadow-indigo-900/20'
                }
                ${isToday ? 'ring-2 ring-indigo-500 ring-offset-2 ring-offset-slate-900' : ''}
                ${isClosed ? 'bg-[url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0IiBoZWlnaHQ9IjQiPgo8cmVjdCB3aWR0aD0iNCIgaGVpZ2h0PSI0IiBmaWxsPSIjMGUxNzJhIi8+CjxwYXRoIGQ9Ik0wIDBMNCA0IiBzdHJva2U9IiMxZTI5M2IiIHN0cm9rZS13aWR0aD0iMSIvPgo8L3N2Zz4=")]' : ''}
              `}
            >
              <div className="flex justify-between w-full">
                  <span className={`text-sm font-medium w-6 h-6 flex items-center justify-center rounded-full ${
                    isToday ? 'bg-indigo-600 text-white' : isClosed ? 'text-slate-600' : 'text-slate-300 group-hover:bg-slate-700'
                  }`}>
                    {day}
                  </span>
                  {isHoliday && <span title="Holiday"><AlertCircle className="w-4 h-4 text-red-500" /></span>}
                  {(isManualClosed || isClosedDay) && !isHoliday && <span title="Closed"><Lock className="w-3 h-3 text-slate-600" /></span>}
              </div>
              
              {!isClosed && (
                  <div className="flex-1 w-full flex flex-col gap-1 mt-1 overflow-hidden">
                    {dayEvents.slice(0, 3).map(ev => (
                    <div 
                        key={ev.id} 
                        className="flex items-center gap-1 text-[10px] leading-tight truncate px-1.5 py-0.5 rounded bg-slate-700 text-slate-300 font-medium w-full border-l-2"
                        style={{ borderLeftColor: getTechColor(ev.technicianId)}}
                        title={ev.title}
                    >
                        {ev.locationType === 'REMOTE' ? (
                            <Headphones className="w-3 h-3 text-slate-400 flex-shrink-0" />
                        ) : (
                            <MapPin className="w-3 h-3 text-indigo-400 flex-shrink-0" />
                        )}
                        <span className="truncate">{ev.title}</span>
                    </div>
                    ))}
                    {dayEvents.length > 3 && (
                    <div className="text-[9px] text-slate-500 pl-1">+{dayEvents.length - 3} more</div>
                    )}
                </div>
              )}

              {/* Hover Plus Icon */}
              {!isClosed && (
                <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 bg-slate-900/60 backdrop-blur-[1px] rounded-lg transition-opacity pointer-events-none">
                    <Plus className="w-6 h-6 text-indigo-400 drop-shadow-sm" />
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
};