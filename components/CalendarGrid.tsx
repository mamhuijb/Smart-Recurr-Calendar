import React from 'react';
import { RecurrenceEvent, Technician, BusinessHours } from '../types';
import { ChevronLeft, ChevronRight, Plus, AlertCircle, MapPin, Headset, Lock, Calendar, LayoutList } from 'lucide-react';

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
        className={`group relative min-h-[40px] sm:min-h-[56px] md:aspect-square p-1 sm:p-1.5 md:p-2 flex flex-col items-start transition-all duration-200 rounded-xl
          ${isClosed
            ? 'bg-gray-50/80 dark:bg-slate-800/20 cursor-not-allowed'
            : 'bg-white/80 dark:bg-slate-800/30 cursor-pointer hover:bg-primary-50/60 dark:hover:bg-primary-950/30 hover:shadow-md'
          }
          ${!isClosed ? 'border border-gray-100/80 dark:border-slate-700/40 hover:border-primary-300/60 dark:hover:border-primary-600/40' : 'border border-transparent'}
          ${isToday ? 'ring-2 ring-primary-500/70 ring-offset-2 ring-offset-white dark:ring-offset-slate-900 shadow-sm' : ''}
        `}
      >
        <div className="flex justify-between items-center w-full">
          <span className={`text-[11px] sm:text-[13px] font-semibold w-6 h-6 sm:w-7 sm:h-7 flex items-center justify-center rounded-lg transition-colors
            ${isToday
              ? 'bg-primary-600 text-white shadow-sm shadow-primary-600/30'
              : isClosed
                ? 'text-gray-300 dark:text-slate-600'
                : 'text-gray-600 dark:text-slate-300 group-hover:text-primary-700 dark:group-hover:text-primary-300'
            }
          `}>
            {day}
          </span>
          {isHoliday && <AlertCircle className="w-3 h-3 sm:w-3.5 sm:h-3.5 text-red-400/80" />}
          {(isManualClosed || isClosedDay) && !isHoliday && <Lock className="w-2.5 h-2.5 text-gray-300 dark:text-slate-600" />}
        </div>

        {!isClosed && dayEvents.length > 0 && (
          <>
            {/* Mobile: colored dots */}
            <div className="flex gap-[3px] mt-1 flex-wrap sm:hidden">
              {dayEvents.slice(0, 4).map(ev => (
                <div key={ev.id} className="w-[5px] h-[5px] rounded-full ring-1 ring-white/50 dark:ring-slate-900/50" style={{ backgroundColor: getTechColor(ev.technicianId) }} />
              ))}
              {dayEvents.length > 4 && <span className="text-[7px] text-gray-400 font-medium">+{dayEvents.length - 4}</span>}
            </div>

            {/* Desktop: event chips */}
            <div className="hidden sm:flex flex-1 w-full flex-col gap-[3px] mt-1.5 overflow-hidden">
              {dayEvents.slice(0, 3).map(ev => (
                <div
                  key={ev.id}
                  onClick={(e) => { e.stopPropagation(); onEventClick(ev); }}
                  className="flex items-center gap-1 text-[9px] md:text-[10px] leading-tight truncate px-1.5 py-[3px] rounded-md transition-all duration-150 cursor-pointer
                    bg-gray-50/90 dark:bg-slate-700/40 hover:bg-gray-100 dark:hover:bg-slate-600/50
                    text-gray-600 dark:text-slate-300 font-medium w-full border-l-[3px] hover:translate-x-[1px]"
                  style={{ borderLeftColor: getTechColor(ev.technicianId) }}
                  title={ev.title}
                >
                  {ev.locationType === 'REMOTE' ? (
                    <Headset className="w-2.5 h-2.5 text-emerald-400 flex-shrink-0" />
                  ) : (
                    <MapPin className="w-2.5 h-2.5 text-primary-400 flex-shrink-0" />
                  )}
                  <span className="truncate">{ev.title}</span>
                </div>
              ))}
              {dayEvents.length > 3 && (
                <div className="text-[8px] md:text-[9px] text-gray-400 dark:text-slate-500 pl-1.5 font-medium">+{dayEvents.length - 3} more</div>
              )}
            </div>
          </>
        )}

        {/* Hover overlay with plus icon for empty days */}
        {!isClosed && dayEvents.length === 0 && (
          <div className="absolute inset-0 hidden sm:flex items-center justify-center opacity-0 group-hover:opacity-100 rounded-xl transition-opacity duration-200 pointer-events-none">
            <div className="w-7 h-7 rounded-lg bg-primary-100/80 dark:bg-primary-900/30 flex items-center justify-center">
              <Plus className="w-4 h-4 text-primary-500 dark:text-primary-400" />
            </div>
          </div>
        )}
      </div>
    );
  };

  const renderWeekDay = (date: Date) => {
    const y = date.getFullYear(), m = date.getMonth(), d = date.getDate();
    const dayEvents = getEventsForDay(y, m, d);
    const isToday = new Date().toDateString() === date.toDateString();
    const { isHoliday, isManualClosed, isClosedDay } = checkStatus(y, m, d);
    const isClosed = isHoliday || isManualClosed || isClosedDay;

    return (
      <div
        key={date.toISOString()}
        className={`flex flex-col rounded-2xl transition-all duration-200 min-h-[200px] sm:min-h-[350px] overflow-hidden
          ${isClosed
            ? 'bg-gray-50/60 dark:bg-slate-800/20 border border-gray-100/50 dark:border-slate-800/30 opacity-60'
            : 'bg-white dark:bg-slate-800/30 border border-gray-100 dark:border-slate-700/40 hover:shadow-md'
          }
          ${isToday ? 'ring-2 ring-primary-500/70 ring-offset-2 ring-offset-white dark:ring-offset-slate-900 shadow-sm' : ''}
        `}
      >
        {/* Day header */}
        <div
          className={`px-3 py-3 border-b border-gray-100/80 dark:border-slate-700/40 cursor-pointer transition-colors
            ${isClosed ? 'cursor-not-allowed' : 'hover:bg-gray-50/80 dark:hover:bg-slate-800/40'}
            ${isToday ? 'bg-primary-50/50 dark:bg-primary-950/20' : ''}
          `}
          onClick={() => !isClosed && onDayClick(date)}
        >
          <div className="text-center">
            <div className="text-[10px] sm:text-[11px] text-gray-400 dark:text-slate-500 uppercase font-semibold tracking-wider">
              {date.toLocaleDateString(locale, { weekday: 'short' })}
            </div>
            <div className={`text-lg sm:text-2xl font-bold mt-0.5 transition-colors
              ${isToday ? 'text-primary-600 dark:text-primary-400' : 'text-gray-800 dark:text-slate-200'}
            `}>
              {d}
            </div>
            {isHoliday && <AlertCircle className="w-3 h-3 text-red-400 mx-auto mt-0.5" />}
            {(isManualClosed || isClosedDay) && !isHoliday && <Lock className="w-3 h-3 text-gray-300 dark:text-slate-600 mx-auto mt-0.5" />}
          </div>
        </div>

        {/* Events list */}
        <div className="flex-1 p-2 space-y-1.5 overflow-y-auto">
          {dayEvents.map(ev => (
            <div
              key={ev.id}
              onClick={() => onEventClick(ev)}
              className="group/event flex items-start gap-2 p-2.5 rounded-xl transition-all duration-200 cursor-pointer
                bg-gray-50/80 dark:bg-slate-700/30 hover:bg-white dark:hover:bg-slate-700/50
                border border-transparent hover:border-gray-200/80 dark:hover:border-slate-600/40
                hover:shadow-sm border-l-[3px]"
              style={{ borderLeftColor: getTechColor(ev.technicianId) }}
            >
              <div className="min-w-0 flex-1">
                <div className="font-semibold truncate text-gray-800 dark:text-slate-200 text-[12px] sm:text-[13px]">{ev.title}</div>
                <div className="flex items-center gap-2 mt-1 text-gray-400 dark:text-slate-500 flex-wrap">
                  {ev.startTime && (
                    <span className="text-[10px] font-mono bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400 px-1.5 py-0.5 rounded-md font-medium">
                      {ev.startTime}{ev.endTime ? ` – ${ev.endTime}` : ''}
                    </span>
                  )}
                  <span className="flex items-center gap-0.5 text-[10px]">
                    {ev.locationType === 'REMOTE' ? (
                      <><Headset className="w-3 h-3 text-emerald-400" /> Remote</>
                    ) : (
                      <><MapPin className="w-3 h-3 text-primary-400" /> Op locatie</>
                    )}
                  </span>
                </div>
              </div>
            </div>
          ))}
          {dayEvents.length === 0 && !isClosed && (
            <div
              className="flex items-center justify-center h-full min-h-[40px] text-gray-200 dark:text-slate-700 hover:text-primary-400 dark:hover:text-primary-500 cursor-pointer transition-colors duration-200 rounded-xl hover:bg-gray-50/80 dark:hover:bg-slate-800/30"
              onClick={() => onDayClick(date)}
            >
              <Plus className="w-5 h-5" />
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
    <div className="surface-raised rounded-2xl p-3 sm:p-5 h-full flex flex-col">
      {/* Header */}
      <div className="flex items-center justify-between mb-4 sm:mb-5">
        <div className="flex items-center gap-3">
          <h2 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white capitalize tracking-tight">
            {headerText}
          </h2>
          <span className="hidden sm:inline-flex items-center text-[11px] bg-gray-100/80 dark:bg-slate-800/80 text-gray-500 dark:text-slate-400 px-2.5 py-1 rounded-lg font-medium border border-gray-200/50 dark:border-slate-700/50">
            {businessHours.start} – {businessHours.end}
          </span>
        </div>
        <div className="flex items-center gap-2">
          {/* View toggle */}
          <div className="flex p-[3px] rounded-xl bg-gray-100/80 dark:bg-slate-800/80 border border-gray-200/50 dark:border-slate-700/50">
            <button
              onClick={() => onCalendarViewChange('month')}
              className={`px-2.5 py-1.5 rounded-lg text-[11px] sm:text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 ${
                calendarView === 'month'
                  ? 'bg-white dark:bg-slate-700 text-gray-800 dark:text-white shadow-sm'
                  : 'text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300'
              }`}
              aria-label="Month view"
            >
              <Calendar className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Maand</span>
            </button>
            <button
              onClick={() => onCalendarViewChange('week')}
              className={`px-2.5 py-1.5 rounded-lg text-[11px] sm:text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 ${
                calendarView === 'week'
                  ? 'bg-white dark:bg-slate-700 text-gray-800 dark:text-white shadow-sm'
                  : 'text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300'
              }`}
              aria-label="Week view"
            >
              <LayoutList className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Week</span>
            </button>
          </div>

          {/* Nav arrows */}
          <div className="flex gap-0.5">
            <button
              onClick={onPrev}
              className="w-8 h-8 sm:w-9 sm:h-9 flex items-center justify-center rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 transition-all duration-150 text-gray-400 dark:text-slate-500 hover:text-gray-700 dark:hover:text-slate-200 active:scale-95"
              aria-label="Previous"
            >
              <ChevronLeft className="w-4 h-4 sm:w-5 sm:h-5" />
            </button>
            <button
              onClick={onNext}
              className="w-8 h-8 sm:w-9 sm:h-9 flex items-center justify-center rounded-xl hover:bg-gray-100 dark:hover:bg-slate-800 transition-all duration-150 text-gray-400 dark:text-slate-500 hover:text-gray-700 dark:hover:text-slate-200 active:scale-95"
              aria-label="Next"
            >
              <ChevronRight className="w-4 h-4 sm:w-5 sm:h-5" />
            </button>
          </div>
        </div>
      </div>

      {calendarView === 'month' ? (
        <>
          {/* Weekday headers */}
          <div className="grid grid-cols-7 gap-0.5 sm:gap-1 mb-2">
            {weekDaysFull.map((d, i) => (
              <div key={d} className="text-center text-[10px] sm:text-[11px] font-semibold text-gray-400 dark:text-slate-500 uppercase tracking-wider py-2">
                <span className="hidden sm:inline">{d}</span>
                <span className="sm:hidden">{weekDaysMobile[i]}</span>
              </div>
            ))}
          </div>

          {/* Month grid */}
          <div className="grid grid-cols-7 gap-[3px] sm:gap-1 md:gap-1.5 flex-1">
            {padding.map((_, i) => (
              <div key={`pad-${i}`} className="min-h-[40px] sm:min-h-[56px] md:aspect-square rounded-xl"></div>
            ))}
            {days.map(day => renderMonthDay(day))}
          </div>
        </>
      ) : (
        <div className="grid grid-cols-7 gap-1.5 sm:gap-2 flex-1 animate-fade-in">
          {weekDays.map(date => renderWeekDay(date))}
        </div>
      )}
    </div>
  );
};
