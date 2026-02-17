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
  events,
  technicians,
  displayDate,
  holidays,
  manualClosures = [],
  businessHours = { start: '09:00', end: '17:00', closedDays: [0] },
  calendarView,
  onCalendarViewChange,
  onPrev,
  onNext,
  onDayClick,
  onEventClick
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

      const isHoliday = holidays.includes(dateStr);
      const isManualClosed = manualClosures.includes(dateStr);
      const isClosedDay = businessHours.closedDays.includes(jsDay);

      return { isHoliday, isManualClosed, isClosedDay };
  }

  const getTechColor = (id?: string) => {
      const t = technicians.find(tech => tech.id === id);
      return t ? t.color : '#475569';
  }

  // Weekdays header
  const weekDaysFull = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
  const weekDaysMobile = ['M', 'D', 'W', 'D', 'V', 'Z', 'Z'];

  // Week view: get the Monday of the current week
  const getWeekStart = (date: Date) => {
    const d = new Date(date);
    const day = d.getDay();
    const diff = (day === 0 ? -6 : 1) - day; // Monday = 1
    d.setDate(d.getDate() + diff);
    d.setHours(0, 0, 0, 0);
    return d;
  };

  const weekStart = getWeekStart(displayDate);
  const weekDays = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(weekStart);
    d.setDate(d.getDate() + i);
    return d;
  });

  // Header text
  const headerText = calendarView === 'month'
    ? displayDate.toLocaleDateString(locale, { month: 'long', year: 'numeric' })
    : `${weekDays[0].toLocaleDateString(locale, { day: 'numeric', month: 'short' })} – ${weekDays[6].toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' })}`;

  // Render a day cell for month view
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
        className={`group relative min-h-[36px] sm:min-h-[48px] md:aspect-square p-0.5 sm:p-1 md:p-2 border rounded sm:rounded-lg flex flex-col items-start transition-all
          ${isClosed
              ? 'bg-slate-950/50 border-slate-800 cursor-not-allowed opacity-60'
              : 'bg-slate-800 border-slate-700 cursor-pointer hover:border-indigo-500 hover:shadow-md hover:shadow-indigo-900/20'
          }
          ${isToday ? 'ring-1 sm:ring-2 ring-indigo-500 ring-offset-1 sm:ring-offset-2 ring-offset-slate-900' : ''}
          ${isClosed ? 'bg-[url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0IiBoZWlnaHQ9IjQiPgo8cmVjdCB3aWR0aD0iNCIgaGVpZ2h0PSI0IiBmaWxsPSIjMGUxNzJhIi8+CjxwYXRoIGQ9Ik0wIDBMNCA0IiBzdHJva2U9IiMxZTI5M2IiIHN0cm9rZS13aWR0aD0iMSIvPgo8L3N2Zz4=")]' : ''}
        `}
      >
        <div className="flex justify-between w-full">
            <span className={`text-xs sm:text-sm font-medium w-5 h-5 sm:w-6 sm:h-6 flex items-center justify-center rounded-full ${
              isToday ? 'bg-indigo-600 text-white' : isClosed ? 'text-slate-600' : 'text-slate-300 group-hover:bg-slate-700'
            }`}>
              {day}
            </span>
            {isHoliday && <span title="Holiday"><AlertCircle className="w-3 h-3 sm:w-4 sm:h-4 text-red-500" /></span>}
            {(isManualClosed || isClosedDay) && !isHoliday && <span title="Closed"><Lock className="w-2.5 h-2.5 sm:w-3 sm:h-3 text-slate-600" /></span>}
        </div>

        {/* Event indicators */}
        {!isClosed && dayEvents.length > 0 && (
          <>
            {/* Mobile: colored dots */}
            <div className="flex gap-0.5 mt-0.5 flex-wrap sm:hidden">
              {dayEvents.slice(0, 4).map(ev => (
                <div
                  key={ev.id}
                  className="w-1.5 h-1.5 rounded-full"
                  style={{ backgroundColor: getTechColor(ev.technicianId) }}
                  title={ev.title}
                />
              ))}
              {dayEvents.length > 4 && (
                <span className="text-[8px] text-slate-500">+{dayEvents.length - 4}</span>
              )}
            </div>

            {/* Desktop: event cards */}
            <div className="hidden sm:flex flex-1 w-full flex-col gap-0.5 md:gap-1 mt-0.5 md:mt-1 overflow-hidden">
              {dayEvents.slice(0, 3).map(ev => (
              <div
                  key={ev.id}
                  onClick={(e) => { e.stopPropagation(); onEventClick(ev); }}
                  className="flex items-center gap-0.5 md:gap-1 text-[9px] md:text-[10px] leading-tight truncate px-1 md:px-1.5 py-0.5 rounded bg-slate-700 hover:bg-slate-600 text-slate-300 font-medium w-full border-l-2 cursor-pointer transition-colors"
                  style={{ borderLeftColor: getTechColor(ev.technicianId)}}
                  title={`Click to edit: ${ev.title}`}
              >
                  {ev.locationType === 'REMOTE' ? (
                      <Headset className="w-2.5 h-2.5 md:w-3 md:h-3 text-slate-400 flex-shrink-0" />
                  ) : (
                      <MapPin className="w-2.5 h-2.5 md:w-3 md:h-3 text-indigo-400 flex-shrink-0" />
                  )}
                  <span className="truncate">{ev.title}</span>
              </div>
              ))}
              {dayEvents.length > 3 && (
              <div className="text-[8px] md:text-[9px] text-slate-500 pl-1">+{dayEvents.length - 3} more</div>
              )}
            </div>
          </>
        )}

        {/* Hover Plus Icon - only on non-touch devices */}
        {!isClosed && dayEvents.length === 0 && (
          <div className="absolute inset-0 hidden sm:flex items-center justify-center opacity-0 group-hover:opacity-100 bg-slate-900/60 backdrop-blur-[1px] rounded-lg transition-opacity pointer-events-none">
              <Plus className="w-5 h-5 md:w-6 md:h-6 text-indigo-400 drop-shadow-sm" />
          </div>
        )}
      </div>
    );
  };

  // Render a day column for week view
  const renderWeekDay = (date: Date) => {
    const y = date.getFullYear();
    const m = date.getMonth();
    const d = date.getDate();
    const dayEvents = getEventsForDay(y, m, d);
    const isToday = new Date().toDateString() === date.toDateString();
    const { isHoliday, isManualClosed, isClosedDay } = checkStatus(y, m, d);
    const isClosed = isHoliday || isManualClosed || isClosedDay;

    return (
      <div
        key={date.toISOString()}
        className={`flex flex-col border rounded-lg transition-all min-h-[200px] sm:min-h-[350px]
          ${isClosed
            ? 'bg-slate-950/50 border-slate-800 opacity-60'
            : 'bg-slate-800 border-slate-700'
          }
          ${isToday ? 'ring-2 ring-indigo-500 ring-offset-2 ring-offset-slate-900' : ''}
        `}
      >
        {/* Day header */}
        <div
          className={`p-2 border-b border-slate-700 cursor-pointer hover:bg-slate-750 transition-colors ${isClosed ? 'cursor-not-allowed' : ''}`}
          onClick={() => !isClosed && onDayClick(date)}
        >
          <div className="text-center">
            <div className="text-[10px] sm:text-xs text-slate-500 uppercase font-bold">
              {date.toLocaleDateString(locale, { weekday: 'short' })}
            </div>
            <div className={`text-sm sm:text-lg font-bold mt-0.5 ${isToday ? 'text-indigo-400' : 'text-slate-200'}`}>
              {d}
            </div>
            {isHoliday && <AlertCircle className="w-3 h-3 text-red-500 mx-auto mt-0.5" />}
            {(isManualClosed || isClosedDay) && !isHoliday && <Lock className="w-3 h-3 text-slate-600 mx-auto mt-0.5" />}
          </div>
        </div>

        {/* Events list */}
        <div className="flex-1 p-1.5 space-y-1 overflow-y-auto custom-scrollbar">
          {dayEvents.map(ev => (
            <div
              key={ev.id}
              onClick={() => onEventClick(ev)}
              className="flex items-start gap-1.5 text-[10px] sm:text-xs p-1.5 sm:p-2 rounded bg-slate-700 hover:bg-slate-600 text-slate-300 font-medium border-l-2 cursor-pointer transition-colors"
              style={{ borderLeftColor: getTechColor(ev.technicianId) }}
              title={`Click to edit: ${ev.title}`}
            >
              <div className="min-w-0 flex-1">
                <div className="font-semibold truncate text-slate-200">{ev.title}</div>
                <div className="flex items-center gap-1 mt-0.5 text-slate-400">
                  {ev.locationType === 'REMOTE' ? (
                    <Headset className="w-3 h-3 flex-shrink-0" />
                  ) : (
                    <MapPin className="w-3 h-3 flex-shrink-0 text-indigo-400" />
                  )}
                  <span>{ev.locationType === 'ON_SITE' ? 'On Site' : 'Remote'}</span>
                </div>
              </div>
            </div>
          ))}
          {dayEvents.length === 0 && !isClosed && (
            <div
              className="flex items-center justify-center h-full min-h-[40px] text-slate-600 hover:text-indigo-400 cursor-pointer transition-colors rounded hover:bg-slate-750"
              onClick={() => onDayClick(date)}
            >
              <Plus className="w-4 h-4" />
            </div>
          )}
        </div>
      </div>
    );
  };

  // Month view data
  const daysInMonth = getDaysInMonth(year, month);
  const startDay = getFirstDayOfMonth(year, month);
  const days = Array.from({ length: daysInMonth }, (_, i) => i + 1);
  const padding = Array.from({ length: startDay }, (_, i) => i);

  return (
    <div className="bg-slate-900 rounded-xl shadow-lg border border-slate-800 p-3 sm:p-4 md:p-6 h-full flex flex-col">
      {/* Header */}
      <div className="flex items-center justify-between mb-3 sm:mb-4">
        <div className="flex items-center gap-2 sm:gap-3">
          <h2 className="text-base sm:text-xl font-bold text-slate-100 capitalize">
            {headerText}
          </h2>
          <span className="hidden sm:inline text-xs bg-slate-800 text-slate-400 px-2 py-1 rounded border border-slate-700 font-normal">
              {businessHours.start} - {businessHours.end}
          </span>
        </div>
        <div className="flex items-center gap-2 sm:gap-3">
          {/* View toggle */}
          <div className="flex bg-slate-800 rounded-lg border border-slate-700 p-0.5">
            <button
              onClick={() => onCalendarViewChange('month')}
              className={`px-2 py-1 rounded text-[10px] sm:text-xs font-medium transition-all flex items-center gap-1 ${
                calendarView === 'month' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
              }`}
              title="Month view"
            >
              <Calendar className="w-3 h-3" />
              <span className="hidden sm:inline">Month</span>
            </button>
            <button
              onClick={() => onCalendarViewChange('week')}
              className={`px-2 py-1 rounded text-[10px] sm:text-xs font-medium transition-all flex items-center gap-1 ${
                calendarView === 'week' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
              }`}
              title="Week view"
            >
              <List className="w-3 h-3" />
              <span className="hidden sm:inline">Week</span>
            </button>
          </div>

          {/* Navigation */}
          <div className="flex gap-1 sm:gap-2">
            <button onClick={onPrev} className="p-1.5 sm:p-2 hover:bg-slate-800 rounded-full transition-colors text-slate-400 hover:text-white">
              <ChevronLeft className="w-4 h-4 sm:w-5 sm:h-5" />
            </button>
            <button onClick={onNext} className="p-1.5 sm:p-2 hover:bg-slate-800 rounded-full transition-colors text-slate-400 hover:text-white">
              <ChevronRight className="w-4 h-4 sm:w-5 sm:h-5" />
            </button>
          </div>
        </div>
      </div>

      {calendarView === 'month' ? (
        <>
          {/* Weekday headers */}
          <div className="grid grid-cols-7 gap-0.5 sm:gap-1 mb-1 sm:mb-2">
            {weekDaysFull.map((d, i) => (
              <div key={d} className="text-center text-[10px] sm:text-xs font-bold text-slate-500 uppercase tracking-wider py-1 sm:py-2">
                <span className="hidden sm:inline">{d}</span>
                <span className="sm:hidden">{weekDaysMobile[i]}</span>
              </div>
            ))}
          </div>

          {/* Month grid */}
          <div className="grid grid-cols-7 gap-0.5 sm:gap-1 md:gap-2 flex-1">
            {padding.map((_, i) => (
              <div key={`pad-${i}`} className="min-h-[36px] sm:min-h-[48px] md:aspect-square bg-slate-950/50 rounded sm:rounded-lg border border-transparent"></div>
            ))}
            {days.map(day => renderMonthDay(day))}
          </div>
        </>
      ) : (
        /* Week view */
        <div className="grid grid-cols-7 gap-1 sm:gap-2 flex-1">
          {weekDays.map(date => renderWeekDay(date))}
        </div>
      )}
    </div>
  );
};
