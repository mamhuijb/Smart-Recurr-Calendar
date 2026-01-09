
import React, { useState, useEffect } from 'react';
import { parseRecurrenceRule } from '../services/geminiService';
import { generateRecurrenceDates, getReadableRule, Frequency, Ordinal, DayOfWeek, RecurrenceType } from '../utils/recurrenceEngine';
import { RecurrenceEvent, Customer, Service, Technician, LocationType } from '../types';
import { Loader2, CalendarCheck, Sparkles, ArrowLeft, User, Briefcase, UserCog, Ticket, CalendarDays, MapPin, Headset, Calculator, Wand2, Clock, Repeat, Calendar } from 'lucide-react';

interface EventCreatorProps {
  initialRule?: string;
  customers: Customer[];
  services: Service[];
  technicians: Technician[];
  onSave: (event: RecurrenceEvent) => void;
  onCancel: () => void;
}

export const EventCreator: React.FC<EventCreatorProps> = ({ 
  initialRule = '', 
  customers,
  services,
  technicians,
  onSave, 
  onCancel 
}) => {
  // Data State
  const [selectedCustomer, setSelectedCustomer] = useState('');
  const [selectedService, setSelectedService] = useState('');
  const [selectedTech, setSelectedTech] = useState('');
  const [selectedAsset, setSelectedAsset] = useState('');
  const [locationType, setLocationType] = useState<LocationType>('ON_SITE');
  
  // Scheduling Mode State
  const [scheduleType, setScheduleType] = useState<'RECURRING' | 'ONE_TIME'>('RECURRING');
  const [singleDate, setSingleDate] = useState<string>('');

  // Builder Mode State (for Recurring)
  const [mode, setMode] = useState<'AI' | 'BUILDER'>('BUILDER');

  // AI Mode State
  const [ruleText, setRuleText] = useState(initialRule);
  const [isAnalyzing, setIsAnalyzing] = useState(false);

  // Builder Parameters
  const [freq, setFreq] = useState<Frequency>('YEARLY');
  const [month, setMonth] = useState<number>(9); // Default October
  const [patternType, setPatternType] = useState<RecurrenceType>('RELATIVE');
  // Relative Params
  const [ordinal, setOrdinal] = useState<Ordinal>('2'); // Default 2nd
  const [dayOfWeek, setDayOfWeek] = useState<DayOfWeek>(2); // Default Tuesday
  // Absolute Params
  const [dayOfMonth, setDayOfMonth] = useState<number>(1); // Default 1st

  // Result State
  const [previewDates, setPreviewDates] = useState<string[]>([]);
  const [finalRuleDescription, setFinalRuleDescription] = useState('');
  const [error, setError] = useState<string | null>(null);

  // Derived
  const currentCustomer = customers.find(c => c.id === selectedCustomer);
  const currentService = services.find(s => s.id === selectedService);

  // Initialize Service Defaults
  useEffect(() => {
    if (currentService) {
      setLocationType(currentService.defaultLocation);
      // Auto-switch schedule type based on Service settings
      const defaultType = currentService.type === 'ONE_TIME' ? 'ONE_TIME' : 'RECURRING';
      setScheduleType(defaultType);
    }
  }, [selectedService, services]);

  // Initialize from Calendar Click
  useEffect(() => {
    if (initialRule) {
        // If clicking a date on calendar, set up AI text
        setMode('AI');
        handleAnalyzeAI(initialRule);

        // Also try to parse it as a single date for One-Time mode comfort
        const d = new Date(initialRule);
        if(!isNaN(d.getTime())) {
            setSingleDate(d.toISOString().split('T')[0]);
        }
    } else {
        // Run builder calculation once on mount for defaults
        handleBuilderCalculate(); 
    }
  }, [initialRule]);

  // Main Calculation Effect
  useEffect(() => {
      if (scheduleType === 'ONE_TIME') {
          if (singleDate) {
              setPreviewDates([singleDate]);
              setFinalRuleDescription(`One-time appointment on ${singleDate}`);
              setError(null);
          } else {
              setPreviewDates([]);
              setFinalRuleDescription('');
          }
      } else {
          // Recurring Logic
          if (mode === 'BUILDER') handleBuilderCalculate();
          // We don't auto-run AI on every render to avoid API spam, it's triggered manually or on mount
      }
  }, [scheduleType, singleDate, freq, month, ordinal, dayOfWeek, dayOfMonth, patternType, mode]);

  // --- Handlers ---

  const handleAnalyzeAI = async (text: string = ruleText) => {
    if (!text) return;
    setIsAnalyzing(true);
    setError(null);
    try {
      const dates = await parseRecurrenceRule(text);
      if (dates.length === 0) setError("No dates could be generated.");
      setPreviewDates(dates);
      setRuleText(text);
      setFinalRuleDescription(text);
    } catch (err) {
      setError("Failed to generate dates.");
    } finally {
      setIsAnalyzing(false);
    }
  };

  const handleBuilderCalculate = () => {
      try {
          const config = { 
              frequency: freq, 
              type: patternType,
              startMonth: month, 
              ordinal, 
              dayOfWeek, 
              dayOfMonth,
              yearsToGenerate: 5 
          };
          const dates = generateRecurrenceDates(config);
          setPreviewDates(dates);
          setFinalRuleDescription(getReadableRule(config));
          setError(null);
      } catch (e) {
          setError("Calculation error");
      }
  };


  const handleConfirm = () => {
    if (!selectedCustomer || !selectedService || previewDates.length === 0) return;

    // Simulate Syncro Ticket Creation
    let ticketId = undefined;
    if (currentService?.createTicket) {
        ticketId = `T-${Math.floor(Math.random() * 10000)}`; 
    }

    const newEvent: RecurrenceEvent = {
      id: crypto.randomUUID(),
      title: `${currentCustomer?.company} - ${currentService?.name}`,
      customerId: selectedCustomer,
      serviceId: selectedService,
      technicianId: selectedTech,
      assetId: selectedAsset,
      syncroTicketId: ticketId,
      locationType,
      description: '',
      recurrenceRule: finalRuleDescription, // Use the generated description
      generatedDates: previewDates,
      status: 'SCHEDULED',
      createdAt: Date.now(),
    };
    onSave(newEvent);
  };

  return (
    <div className="bg-slate-900 rounded-xl shadow-lg flex flex-col md:flex-row max-w-5xl mx-auto border border-slate-800 overflow-hidden min-h-[650px] text-slate-300">
      
      {/* Left Panel: Job Details */}
      <div className="flex-1 p-6 md:p-8 space-y-6 overflow-y-auto border-r border-slate-800">
        <div className="flex items-center gap-4 mb-2">
            <button onClick={onCancel} className="p-2 hover:bg-slate-800 rounded-full transition-colors text-slate-500">
                <ArrowLeft className="w-5 h-5" />
            </button>
            <h2 className="text-2xl font-bold text-slate-100 flex items-center gap-2">
                <Briefcase className="w-6 h-6 text-indigo-500" />
                New Appointment
            </h2>
        </div>

        <div className="space-y-4">
            <div className="p-4 bg-slate-800 rounded-lg border border-slate-700 space-y-3">
                <h3 className="text-sm font-bold text-slate-500 uppercase flex items-center gap-2"><User className="w-4 h-4"/> Client</h3>
                <select className="w-full p-2 border border-slate-600 rounded bg-slate-900 text-sm text-white" value={selectedCustomer} onChange={(e) => setSelectedCustomer(e.target.value)}>
                    <option value="">Select Customer...</option>
                    {customers.map(c => <option key={c.id} value={c.id}>{c.company} ({c.name})</option>)}
                </select>
                {currentCustomer?.assets && currentCustomer.assets.length > 0 && (
                     <select className="w-full p-2 border border-slate-600 rounded bg-slate-900 text-sm text-white" value={selectedAsset} onChange={(e) => setSelectedAsset(e.target.value)}>
                        <option value="">Select Asset (Optional)...</option>
                        {currentCustomer.assets.map(a => <option key={a.id} value={a.id}>{a.name} ({a.type})</option>)}
                    </select>
                )}
            </div>

            <div className="p-4 bg-slate-800 rounded-lg border border-slate-700 space-y-3">
                <h3 className="text-sm font-bold text-slate-500 uppercase flex items-center gap-2"><Briefcase className="w-4 h-4"/> Service & Tech</h3>
                <select className="w-full p-2 border border-slate-600 rounded bg-slate-900 text-sm text-white" value={selectedService} onChange={(e) => setSelectedService(e.target.value)}>
                    <option value="">Select Service...</option>
                    {services.map(s => <option key={s.id} value={s.id}>{s.name} ({s.type})</option>)}
                </select>
                
                <div className="flex gap-2 p-1 bg-slate-700 rounded-lg">
                    <button onClick={() => setLocationType('ON_SITE')} className={`flex-1 flex items-center justify-center gap-2 py-1.5 rounded-md text-xs font-bold transition-all ${locationType === 'ON_SITE' ? 'bg-slate-600 shadow text-indigo-400' : 'text-slate-400 hover:text-slate-200'}`}>
                        <MapPin className="w-3.5 h-3.5" /> On Location
                    </button>
                    <button onClick={() => setLocationType('REMOTE')} className={`flex-1 flex items-center justify-center gap-2 py-1.5 rounded-md text-xs font-bold transition-all ${locationType === 'REMOTE' ? 'bg-slate-600 shadow text-indigo-400' : 'text-slate-400 hover:text-slate-200'}`}>
                        <Headset className="w-3.5 h-3.5" /> Remote
                    </button>
                </div>

                <div className="flex items-center gap-2">
                     <UserCog className="w-4 h-4 text-slate-600" />
                     <select className="w-full p-2 border border-slate-600 rounded bg-slate-900 text-sm text-white" value={selectedTech} onChange={(e) => setSelectedTech(e.target.value)}>
                        <option value="">Unassigned (Technician)</option>
                        {technicians.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                    </select>
                </div>
            </div>
            
            {currentService?.createTicket && (
                <div className="p-3 bg-green-900/20 border border-green-800 rounded-lg flex items-center gap-2 text-xs font-medium text-green-400">
                    <Ticket className="w-4 h-4" />
                    <span>SyncroMSP Ticket will be auto-generated.</span>
                </div>
            )}
        </div>
      </div>

      {/* Right Panel: Advanced Scheduler */}
      <div className="w-full md:w-[500px] bg-slate-800 p-6 md:p-8 flex flex-col">
        <h3 className="text-lg font-bold mb-4 flex items-center gap-2 text-white">
            <CalendarDays className="w-5 h-5 text-indigo-500"/> 
            Schedule Builder
        </h3>

        {/* Schedule Type Toggle */}
        <div className="flex p-1 bg-slate-900 rounded-lg mb-6 border border-slate-700">
             <button 
                onClick={() => setScheduleType('ONE_TIME')}
                className={`flex-1 py-2 text-xs font-bold uppercase tracking-wider rounded-md flex items-center justify-center gap-2 transition-all ${scheduleType === 'ONE_TIME' ? 'bg-indigo-600 text-white shadow' : 'text-slate-500 hover:text-slate-300'}`}
             >
                <Clock className="w-3.5 h-3.5" /> One Time
             </button>
             <button 
                onClick={() => setScheduleType('RECURRING')}
                className={`flex-1 py-2 text-xs font-bold uppercase tracking-wider rounded-md flex items-center justify-center gap-2 transition-all ${scheduleType === 'RECURRING' ? 'bg-indigo-600 text-white shadow' : 'text-slate-500 hover:text-slate-300'}`}
             >
                <Repeat className="w-3.5 h-3.5" /> Recurring
             </button>
        </div>

        {/* ONE TIME UI */}
        {scheduleType === 'ONE_TIME' ? (
             <div className="mb-6 animate-in fade-in slide-in-from-top-2 duration-300">
                <div className="p-4 bg-indigo-900/20 rounded-xl border border-indigo-500/30">
                    <label className="text-xs font-bold text-indigo-300 uppercase mb-2 block flex items-center gap-2">
                        <Calendar className="w-3.5 h-3.5" /> Date Selection
                    </label>
                    <input 
                        type="date" 
                        value={singleDate} 
                        onChange={e => setSingleDate(e.target.value)} 
                        className="w-full p-3 bg-slate-900 border border-slate-600 rounded-lg text-white color-scheme-dark focus:ring-2 focus:ring-indigo-500 outline-none"
                    />
                </div>
             </div>
        ) : (
             /* RECURRING UI */
             <>
                {/* Mode Toggle */}
                <div className="flex gap-4 mb-4">
                    <button 
                        onClick={() => setMode('BUILDER')}
                        className={`text-xs font-bold uppercase pb-1 border-b-2 transition-colors flex items-center gap-2 ${mode === 'BUILDER' ? 'text-white border-indigo-500' : 'text-slate-500 border-transparent hover:text-slate-300'}`}
                    >
                        <Calculator className="w-3.5 h-3.5" /> Precision
                    </button>
                    <button 
                        onClick={() => setMode('AI')}
                        className={`text-xs font-bold uppercase pb-1 border-b-2 transition-colors flex items-center gap-2 ${mode === 'AI' ? 'text-white border-indigo-500' : 'text-slate-500 border-transparent hover:text-slate-300'}`}
                    >
                        <Wand2 className="w-3.5 h-3.5" /> AI Assistant
                    </button>
                </div>

                {/* Builder UI */}
                {mode === 'BUILDER' && (
                    <div className="space-y-4 mb-6 animate-in fade-in slide-in-from-top-2 duration-300">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="text-xs font-bold text-slate-500 uppercase mb-1 block">Frequency</label>
                                <select className="w-full p-2 border border-slate-600 rounded-lg bg-slate-900 text-white focus:ring-2 focus:ring-indigo-500 outline-none" value={freq} onChange={e => setFreq(e.target.value as Frequency)}>
                                    <option value="YEARLY">Yearly (1x/yr)</option>
                                    <option value="HALF_YEARLY">Half-Yearly (2x/yr)</option>
                                    <option value="QUARTERLY">Quarterly (4x/yr)</option>
                                    <option value="MONTHLY">Monthly (12x/yr)</option>
                                </select>
                            </div>
                            {freq !== 'MONTHLY' && (
                                <div>
                                    <label className="text-xs font-bold text-slate-500 uppercase mb-1 block">
                                        {freq === 'YEARLY' ? 'Recurs In' : 'Starts In'}
                                    </label>
                                    <select className="w-full p-2 border border-slate-600 rounded-lg bg-slate-900 text-white focus:ring-2 focus:ring-indigo-500 outline-none" value={month} onChange={e => setMonth(Number(e.target.value))}>
                                        {['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'].map((m, i) => (
                                            <option key={m} value={i}>{m}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                        </div>

                        <div className="p-4 bg-indigo-900/30 rounded-xl border border-indigo-500/30">
                            <div className="flex justify-center mb-3">
                                <div className="inline-flex bg-slate-900 p-1 rounded-lg border border-slate-700 w-full">
                                    <button onClick={() => setPatternType('RELATIVE')} className={`flex-1 px-3 py-1.5 text-xs font-bold rounded-md transition-colors ${patternType === 'RELATIVE' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'}`}>Weekday Pattern</button>
                                    <button onClick={() => setPatternType('ABSOLUTE')} className={`flex-1 px-3 py-1.5 text-xs font-bold rounded-md transition-colors ${patternType === 'ABSOLUTE' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'}`}>Specific Date</button>
                                </div>
                            </div>
                            
                            <label className="text-xs font-bold text-indigo-300 uppercase mb-2 block text-center">Rule Configuration</label>
                            
                            <div className="flex gap-2 items-center justify-center">
                                {patternType === 'RELATIVE' ? (
                                    <>
                                        <span className="text-sm font-medium text-slate-400 whitespace-nowrap">On the</span>
                                        <select className="w-20 p-2 border border-slate-600 rounded-lg bg-slate-900 text-sm text-white" value={ordinal} onChange={e => setOrdinal(e.target.value as Ordinal)}>
                                            <option value="1">1st</option>
                                            <option value="2">2nd</option>
                                            <option value="3">3rd</option>
                                            <option value="4">4th</option>
                                            <option value="last">Last</option>
                                        </select>
                                        <select className="flex-1 p-2 border border-slate-600 rounded-lg bg-slate-900 text-sm text-white" value={dayOfWeek} onChange={e => setDayOfWeek(Number(e.target.value) as DayOfWeek)}>
                                            <option value={1}>Monday</option>
                                            <option value={2}>Tuesday</option>
                                            <option value={3}>Wednesday</option>
                                            <option value={4}>Thursday</option>
                                            <option value={5}>Friday</option>
                                            <option value={6}>Saturday</option>
                                            <option value={0}>Sunday</option>
                                        </select>
                                    </>
                                ) : (
                                    <>
                                        <span className="text-sm font-medium text-slate-400 whitespace-nowrap">On the</span>
                                        <select className="w-20 p-2 border border-slate-600 rounded-lg bg-slate-900 text-sm text-white" value={dayOfMonth} onChange={e => setDayOfMonth(Number(e.target.value))}>
                                            {Array.from({length: 31}, (_, i) => i + 1).map(d => (
                                                <option key={d} value={d}>{d}</option>
                                            ))}
                                        </select>
                                        <span className="text-sm font-medium text-slate-400">day of the month</span>
                                    </>
                                )}
                            </div>
                            {patternType === 'RELATIVE' && (
                                <p className="text-xs text-center text-slate-500 mt-2">
                                    {freq === 'MONTHLY' ? 'of every month' : `of ${['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][month]}`}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                {/* AI UI */}
                {mode === 'AI' && (
                    <div className="mb-6 animate-in fade-in slide-in-from-top-2 duration-300">
                        <label className="text-xs text-slate-500 uppercase font-bold mb-1 block">Describe Schedule</label>
                        <div className="flex gap-2">
                            <input
                                type="text"
                                className="flex-1 px-3 py-2 border border-slate-600 bg-slate-900 rounded-lg text-white text-sm focus:ring-2 focus:ring-indigo-500 outline-none"
                                placeholder="e.g. The second Tuesday of October"
                                value={ruleText}
                                onChange={(e) => setRuleText(e.target.value)}
                            />
                            <button onClick={() => handleAnalyzeAI()} className="bg-indigo-600 hover:bg-indigo-700 text-white p-2 rounded-lg transition-colors">
                                {isAnalyzing ? <Loader2 className="w-5 h-5 animate-spin"/> : <Sparkles className="w-5 h-5"/>}
                            </button>
                        </div>
                        <p className="text-xs text-slate-500 mt-2">AI handles complex rules like "Last Friday of every quarter" or "The 2nd Tuesday of October".</p>
                    </div>
                )}
             </>
        )}

        {/* Preview List */}
        <div className="flex-1 bg-slate-900 rounded-xl p-4 overflow-y-auto border border-slate-800 shadow-inner custom-scrollbar">
             <div className="flex justify-between items-center mb-3">
                 <h4 className="text-xs font-bold text-slate-500 uppercase">{scheduleType === 'ONE_TIME' ? 'Selected Date' : 'Projection (5 Years)'}</h4>
                 <span className="text-xs bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full border border-slate-700">{previewDates.length} Events</span>
             </div>
             
             {error ? (
                 <p className="text-red-400 text-sm text-center py-4">{error}</p>
             ) : previewDates.length === 0 ? (
                 <div className="text-center py-8 text-slate-600">
                     <CalendarDays className="w-8 h-8 mx-auto mb-2 opacity-20" />
                     <p className="text-sm">Configure schedule to see dates</p>
                 </div>
             ) : (
                 <div className="space-y-2">
                     {previewDates.map(d => (
                         <div key={d} className="flex justify-between items-center text-sm bg-slate-800 p-2 rounded border border-slate-700 shadow-sm">
                             <span className="font-mono text-slate-400">{d}</span>
                             <span className="font-medium text-slate-200 capitalize">
                                 {new Date(d).toLocaleDateString('nl-NL', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}
                             </span>
                         </div>
                     ))}
                 </div>
             )}
        </div>

        <button
            onClick={handleConfirm}
            disabled={!selectedCustomer || !selectedService || previewDates.length === 0}
            className="mt-6 w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl shadow-lg shadow-indigo-900/50 disabled:opacity-50 disabled:shadow-none disabled:cursor-not-allowed flex items-center justify-center gap-2 transition-all transform active:scale-95"
          >
            <CalendarCheck className="w-5 h-5" />
            Confirm Schedule
        </button>
      </div>
    </div>
  );
};
