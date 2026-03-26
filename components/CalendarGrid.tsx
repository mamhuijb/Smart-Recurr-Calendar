import React from 'react';
import { RecurrenceEvent, Technician, BusinessHours } from '../types';
import { ChevronLeft, ChevronRight, Plus, AlertCircle, MapPin, Headset, Lock, Calendar, List } from 'lucide-react';

interface CalendarGridProps {
  events: RecurrenceEvent[];
  technicians: Technician[];
  displayDate: Date;
  holidays: string[];
  manualClosures?: string[];
  businessHours?: BusinessHours;
  calendarView: 'month' | 'week';
  onCalendarViewChange: (view: 'month' | 'week') => void;
  onPrev: () => void;
  onNext: () => void;
  onDayClick: (date: Date) => void;
  onEventClick: (event: RecurrenceEvent) => void;
}

export const CalendarGrid: React.FC<CalendarGridProps> = ({
  events, technicians, displayDate, holidays, manualClosures = [],
  businessHours = { start: '09:00', end: '17:00', closedDays: [0] },
  calendarView, onCalendarViewChange, onPrev, onNext, onDayClick, onEventClick
}) => {
  const getDaysInMonth = (year: number, month: number) => new Date(year, month + 1, 0).getDate();
  const getFirstDayOfMonth = (year: number, month: number) => {
    const day = new Date(year, month, 1).getDay();
    return (day + 6) % 7;
  };

  const locale = 'nl-NL';
  const year = displayDate.getFullYear();
  const month = displayDate.getMonth();

  const getEventsForDay = (y: number, m: number, d: number) => {
    const dateStr = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    return events.filter(e => e.generatedDates.includes(dateStr));
  };

  const checkStatus = (y: number, m: number, d: number) => {
    const dateStr = `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const jsDay = new Date(y, m, d).getDay();
    return {
      isHoliday: holidays.includes(dateStr),
      isManualClosed: manualClosures.includes(dateStr),
      isClosedDay: businessHours.closedDays.includes(jsDay),
    };
  };

  const getTechColor = (id?: string) => {
    const t = technicians.find(tech => tech.id === id);
    return t ? t.color : '#94a3b8';
  };

  const weekDaysFull = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
  const weekDaysMobile = ['M', 'D', 'W', 'D', 'V', 'Z', 'Z'];

  const getWeekStart = (date: Date) => {
    const d = new Date(date);
    const day = d.getDay();
    d.setDate(d.getDate() + ((day === 0 ? -6 : 1) - day));
    d.setHours(0, 0, 0, 0);
    return d;
  };

  const weekStart = getWeekStart(displayDate);
  const weekDays = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(weekStart);
    d.setDate(d.getDate() + i);
    return d;
  });

  const headerText = calendarView === 'month'
    ? displayDate.toLocaleDateString(locale, { month: 'long', year: 'numeric' })
    : `${weekDays[0].toLocaleDateString(locale, { day: 'numeric', month: 'short' })} – ${weekDays[6].toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' })}`;

  // Month day cell
  const renderMonthDay = (day: number) => {
    const dayEvents = getEventsForDay(year, month, day);
    const currentDayDate = new Date(year, month, day);
    const isToday = new Date().toDateString() === currentDayDate.toDateString();
    const { isHoliday, isManualClosed, isClosedDay } = checkStatus(year, month, day);
    const isClosed = isHoliday || isManualClosed || isClosedDay;

    return (
      <div
        key={day}
        onClick={() => !isClosed && onDayClick(currentDayDate)}
        className={`group relative min-h-[38px] sm:min-h-[52px] md:aspect-square p-0.5 sm:p-1.5 md:p-2 rounded-xl flex flex-col items-start transition-all duration-200
          ${isClosed
            ? 'bg-gray-100/60 dark:bg-slate-900/40 cursor-not-allowed opacity-50'
            : 'bg-white dark:bg-slate-800/40 cursor-pointer hover:bg-primary-50/50 dark:hover:bg-primary-900/10 hover:shadow-card-hover border border-transparent hover:border-primary-200/60 dark:hover:border-primary-700/30'
          }
          ${!isClosed ? 'border border-gray-100 dark:border-slate-800/60' : 'border border-transparent'}
          ${isToday ? 'ring-2 ring-primary-500 ring-offset-1 ring-offset-gray-50 dark:ring-offset-slate-900 shadow-sm' : ''}
        `}
      >
        <div className="flex justify-between w-full">
          <span className={`text-xs sm:text-[13px] font-medium w-5 h-5 sm:w-6 sm:h-6 flex items-center justify-center rounded-lg
            ${isToday ? 'bg-primary-600 text-white font-semibold' : isClosed ? 'text-gray-400 dark:text-slate-600' : 'text-gray-700 dark:text-slate-300'}
          `}>
            {day}
          </span>
          {isHoliday && <AlertCircle className="w-3 h-3 sm:w-3.5 sm:h-3.5 text-red-400" />}
          {(isManualClosed || isClosedDay) && !isHoliday && <Lock className="w-2.5 h-2.5 text-gray-400 dark:text-slate-600" />}
        </div>

        {!isClosed && dayEvents.length > 0 && (
          <>
            {/* Mobile dots */}
            <div className="flex gap-0.5 mt-1 flex-wrap sm:hidden">
              {dayEvents.slice(0, 4).map(ev => (
                <div key={ev.id} className="w-1.5 h-1.5 rounded-full" style={{ backgroundColor: getTechColor(ev.technicianId) }} title={ev.title} />
              ))}
              {dayEvents.length > 4 && <span className="text-[8px] text-gray-400">+{dayEvents.length - 4}</span>}
            </div>

            {/* Desktop cards */}
            <div className="hidden sm:flex flex-1 w-full flex-col gap-0.5 md:gap-1 mt-1 overflow-hidden">
              {dayEvents.slice(0, 3).map(ev => (
                <div
                  key={ev.id}
                  onClick={(e) => { e.stopPropagation(); onEventClick(ev); }}
                  className="flex items-center gap-1 text-[9px] md:text-[10px] leading-tight truncate px-1.5 py-0.5 rounded-md bg-gray-50 dark:bg-slate-700/50 hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-600 dark:text-slate-300 font-medium w-full border-l-2 cursor-pointer transition-colors"
                  style={{ borderLeftColor: getTechColor(ev.technicianId) }}
                  title={`Click to edit: ${ev.title}`}
                >
                  {ev.locationType === 'REMOTE' ? (
                    <Headset className="w-2.5 h-2.5 text-gray-400 dark:text-slate-400 flex-shrink-0" />
                  ) : (
                    <MapPin className="w-2.5 h-2.5 text-primary-400 flex-shrink-0" />
                  )}
                  <span className="truncate">{ev.title}</span>
                </div>
              ))}
              {dayEvents.length > 3 && (
                <div className="text-[8px] md:text-[9px] text-gray-400 dark:text-slate-500 pl-1">+{dayEvents.length - 3} more</div>
              )}
            </div>
          </>
        )}

        {/* Hover plus icon */}
        {!isClosed && dayEvents.length === 0 && (
          <div className="absolute inset-0 hidden sm:flex items-center justify-center opacity-0 group-hover:opacity-100 bg-white/60 dark:bg-slate-900/40 backdrop-blur-[2px] rounded-xl transition-opacity pointer-events-none">
            <Plus className="w-5 h-5 text-primary-400 dark:text-primary-500" />
          </div>
        )}
      </div>
    );
  };

  // Week day column
  const renderWeekDay = (date: Date) => {
    const y = date.getFullYear(), m = date.getMonth(), d = date.getDate();
    const dayEvents = getEventsForDay(y, m, d);
    const isToday = new Date().toDateString() === date.toDateString();
    const { isHoliday, isManualClosed, isClosedDay } = checkStatus(y, m, d);
    const isClosed = isHoliday || isManualClosed || isClosedDay;

    return (
      <div
        key={date.toISOString()}
        className={`flex flex-col rounded-xl transition-all min-h-[200px] sm:min-h-[350px] border
          ${isClosed
            ? 'bg-gray-50/50 dark:bg-slate-900/30 border-gray-100 dark:border-slate-800/50 opacity-60'
            : 'bg-white dark:bg-slate-800/30 border-gray-100 dark:border-slate-800/60'
          }
          ${isToday ? 'ring-2 ring-primary-500 ring-offset-1 ring-offset-gray-50 dark:ring-offset-slate-900' : ''}
        `}
      >
        <div
          className={`p-2.5 border-b border-gray-100 dark:border-slate-800/60 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors rounded-t-xl ${isClosed ? 'cursor-not-allowed' : ''}`}
          onClick={() => !isClosed && onDayClick(date)}
        >
          <div className="text-center">
            <div className="text-[10px] sm:text-[11px] text-gray-400 dark:text-slate-500 uppercase font-semibold tracking-wider">
              {date.toLocaleDateString(locale, { weekday: 'short' })}
            </div>
            <div className={`text-base sm:text-xl font-bold mt-0.5 ${isToday ? 'text-primary-600 dark:text-primary-400' : 'text-gray-800 dark:text-slate-200'}`}>
              {d}
            </div>
            {isHoliday && <AlertCircle className="w-3 h-3 text-red-400 mx-auto mt-0.5" />}
            {(isManualClosed || isClosedDay) && !isHoliday && <Lock className="w-3 h-3 text-gray-400 dark:text-slate-600 mx-auto mt-0.5" />}
          </div>
        </div>

        <div className="flex-1 p-2 space-y-1.5 overflow-y-auto">
          {dayEvents.map(ev => (
            <div
              key={ev.id}
              onClick={() => onEventClick(ev)}
              className="flex items-start gap-1.5 text-[10px] sm:text-xs p-2 rounded-lg bg-gray-50 dark:bg-slate-700/40 hover:bg-gray-100 dark:hover:bg-slate-700/60 text-gray-600 dark:text-slate-300 font-medium border-l-2 cursor-pointer transition-colors"
              style={{ borderLeftColor: getTechColor(ev.technicianId) }}
            >
              <div className="min-w-0 flex-1">
                <div className="font-semibold truncate text-gray-800 dark:text-slate-200 text-[11px] sm:text-[13px]">{ev.title}</div>
                <div className="flex items-center gap-1.5 mt-0.5 text-gray-400 dark:text-slate-400 flex-wrap">
                  {ev.startTime && (
                    <span className="text-[10px] font-mono text-primary-500 dark:text-primary-400">{ev.startTime}{ev.endTime ? `–${ev.endTime}` : ''}</span>
                  )}
                  {ev.locationType === 'REMOTE' ? (
                    <span className="flex items-center gap-0.5"><Headset className="w-3 h-3" /> Remote</span>
                  ) : (
                    <span className="flex items-center gap-0.5"><MapPin className="w-3 h-3 text-primary-400" /> Op locatie</span>
                  )}
                </div>
              </div>
            </div>
          ))}
          {dayEvents.length === 0 && !isClosed && (
            <div
              className="flex items-center justify-center h-full min-h-[40px] text-gray-300 dark:text-slate-600 hover:text-primary-400 cursor-pointer transition-colors rounded-lg hover:bg-gray-50 dark:hover:bg-slate-800/50"
              onClick={() => onDayClick(date)}
            >
              <Plus className="w-4 h-4" />
            </div>
          )}
        </div>
      </div>
    );
  };

  const daysInMonth = getDaysInMonth(year, month);
  const startDay = getFirstDayOfMonth(year, month);
  const days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
  const padding = Array.from({ length: startDay }, (_, i) => i);

  return (
    <div className="surface-raised rounded-2xl p-3 sm:p-4 md:p-5 h-full flex flex-col">
      {/* Header */}
      <div className="flex items-center justify-between mb-3 sm:mb-4">
        <div className="flex items-center gap-2.5">
          <h2 className="text-base sm:text-lg font-bold text-gray-800 dark:text-slate-100 capitalize tracking-tight">
            {headerText}
          </h2>
          <span className="hidden sm:inline text-[11px] bg-gray-100 dark:bg-slate-800 text-gray-400 dark:text-slate-500 px-2 py-0.5 rounded-lg font-medium">
            {businessHours.start} - {businessHours.end}
          </span>
        </div>
        <div className="flex items-center gap-2">
          {/* View toggle — pill */}
          <div className="flex p-0.5 rounded-lg bg-gray-100 dark:bg-slate-800 border border-gray-200/50 dark:border-slate-700/50">
            <button
              onClick={() => onCalendarViewChange('month')}
              className={`px-2 py-1 rounded-md text-[10px] sm:text-[11px] font-semibold transition-all duration-200 flex items-center gap-1 ${
                calendarView === 'month' ? 'bg-white dark:bg-slate-700 text-gray-800 dark:text-white shadow-sm' : 'text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300'
              }`}
            >
              <Calendar className="w-3 h-3" />
              <span className="hidden sm:inline">Month</span>
            </button>
            <button
              onClick={() => onCalendarViewChange('week')}
              className={`px-2 py-1 rounded-md text-[10px] sm:text-[11px] font-semibold transition-all duration-200 flex items-center gap-1 ${
                calendarView === 'week' ? 'bg-white dark:bg-slate-700 text-gray-800 dark:text-white shadow-sm' : 'text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300'
              }`}
            >
              <List className="w-3 h-3" />
              <span className="hidden sm:inline">Week</span>
            </button>
          </div>

          {/* Nav arrows */}
          <div className="flex gap-1">
            <button onClick={onPrev} className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300">
              <ChevronLeft className="w-4 h-4 sm:w-5 sm:h-5" />
            </button>
            <button onClick={onNext} className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300">
              <ChevronRight className="w-4 h-4 sm:w-5 sm:h-5" />
            </button>
          </div>
        </div>
      </div>

      {calendarView === 'month' ? (
        <>
          {/* Weekday headers */}
          <div className="grid grid-cols-7 gap-0.5 sm:gap-1 mb-1.5">
            {weekDaysFull.map((d, i) => (
              <div key={d} className="text-center text-[10px] sm:text-[11px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider py-1.5">
                <span className="hidden sm:inline">{d}</span>
                <span className="sm:hidden">{weekDaysMobile[i]}</span>
              </div>
            ))}
          </div>

          {/* Month grid */}
          <div className="grid grid-cols-7 gap-0.5 sm:gap-1 md:gap-1.5 flex-1">
            {padding.map((_, i) => (
              <div key={`pad-${i}`} className="min-h-[38px] sm:min-h-[52px] md:aspect-square bg-gray-50/50 dark:bg-transparent rounded-xl"></div>
            ))}
            {days.map(day => renderMonthDay(day))}
          </div>
        </>
      ) : (
        <div className="grid grid-cols-7 gap-1.5 sm:gap-2 flex-1">
          {weekDays.map(date => renderWeekDay(date))}
        </div>
      )}
    </div>
  );
};
