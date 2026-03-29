import React, { useState, useEffect } from 'react';
import { api } from '../services/api';
import { toast } from '../utils/toast';
import {
  Clock, Play, CheckCircle2, XCircle, AlertTriangle,
  RefreshCw, Save, Trash2, Loader2, Activity, Zap, Timer
} from 'lucide-react';

interface CronSettings {
  enabled: boolean;
  frequencyMinutes: number;
  runHour: number;
  runMinute: number;
}

interface CronLogEntry {
  id: number;
  startedAt: string;
  finishedAt: string | null;
  durationMs: number;
  status: string;
  emailsSent: number;
  emailsFailed: number;
  pushSent: number;
  error: string | null;
  triggeredBy: string;
}

export const CronJobsTab: React.FC = () => {
  const [loading, setLoading] = useState(true);
  const [settings, setSettings] = useState<CronSettings>({ enabled: true, frequencyMinutes: 60, runHour: 8, runMinute: 0 });
  const [health, setHealth] = useState<string>('unknown');
  const [lastRun, setLastRun] = useState<CronLogEntry | null>(null);
  const [nextRun, setNextRun] = useState<string | null>(null);
  const [logs, setLogs] = useState<CronLogEntry[]>([]);
  const [saving, setSaving] = useState(false);
  const [running, setRunning] = useState(false);
  const [clearing, setClearing] = useState(false);

  const loadStatus = async () => {
    try {
      const data = await api.getCronStatus();
      setSettings(data.settings);
      setHealth(data.health);
      setLastRun(data.lastRun);
      setNextRun(data.nextRun);
      setLogs(data.recentLogs);
    } catch (e: any) {
      toast.error(`Failed to load cron status: ${e.message}`);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadStatus(); }, []);

  const handleSave = async () => {
    setSaving(true);
    try {
      await api.updateCronSettings(settings);
      toast.success('Cron settings saved.');
      await loadStatus();
    } catch (e: any) {
      toast.error(`Save failed: ${e.message}`);
    } finally {
      setSaving(false);
    }
  };

  const handleManualRun = async () => {
    setRunning(true);
    try {
      const result = await api.triggerCronRun();
      if (result.success) {
        toast.success(`Cron completed: ${result.emails_sent} emails sent in ${result.duration_ms}ms`);
      } else {
        toast.error(`Cron failed: ${result.error || 'Unknown error'}`);
      }
      await loadStatus();
    } catch (e: any) {
      toast.error(`Manual run failed: ${e.message}`);
    } finally {
      setRunning(false);
    }
  };

  const handleClearLogs = async () => {
    setClearing(true);
    try {
      await api.clearCronLogs();
      toast.success('Old log entries cleared.');
      await loadStatus();
    } catch (e: any) {
      toast.error(`Clear failed: ${e.message}`);
    } finally {
      setClearing(false);
    }
  };

  const frequencyOptions = [
    { label: 'Every 5 min', value: 5 },
    { label: 'Every 15 min', value: 15 },
    { label: 'Every 30 min', value: 30 },
    { label: 'Hourly', value: 60 },
    { label: 'Every 2 hours', value: 120 },
    { label: 'Every 6 hours', value: 360 },
    { label: 'Every 12 hours', value: 720 },
    { label: 'Daily', value: 1440 },
  ];

  const getHealthIcon = () => {
    switch (health) {
      case 'healthy': return <CheckCircle2 className="w-5 h-5 text-green-500" />;
      case 'error': return <XCircle className="w-5 h-5 text-red-500" />;
      case 'running': return <Loader2 className="w-5 h-5 text-blue-500 animate-spin" />;
      case 'stuck': return <AlertTriangle className="w-5 h-5 text-orange-500" />;
      default: return <Clock className="w-5 h-5 text-gray-400 dark:text-slate-500" />;
    }
  };

  const getHealthLabel = () => {
    switch (health) {
      case 'healthy': return 'Active & Healthy';
      case 'error': return 'Last Run Failed';
      case 'running': return 'Running Now';
      case 'stuck': return 'Stuck (>5min)';
      default: return 'No Runs Yet';
    }
  };

  const getHealthColor = () => {
    switch (health) {
      case 'healthy': return 'bg-green-50 dark:bg-green-900/10 border-green-200 dark:border-green-700/30 text-green-700 dark:text-green-400';
      case 'error': return 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-700/30 text-red-700 dark:text-red-400';
      case 'running': return 'bg-blue-50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-700/30 text-blue-700 dark:text-blue-400';
      case 'stuck': return 'bg-orange-50 dark:bg-orange-900/10 border-orange-200 dark:border-orange-700/30 text-orange-700 dark:text-orange-400';
      default: return 'bg-gray-50 dark:bg-slate-800 border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-400';
    }
  };

  const formatDuration = (ms: number) => {
    if (ms < 1000) return `${ms}ms`;
    return `${(ms / 1000).toFixed(1)}s`;
  };

  const formatDateTime = (dt: string | null) => {
    if (!dt) return '—';
    const d = new Date(dt);
    return d.toLocaleString('nl-NL', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20 text-gray-400 dark:text-slate-500">
        <Loader2 className="w-6 h-6 animate-spin mr-2" /> Loading cron status...
      </div>
    );
  }

  return (
    <div className="space-y-6 max-w-3xl animate-fade-in">
      <div className="flex items-center justify-between border-b border-gray-200 dark:border-slate-700 pb-4">
        <h3 className="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
          <Timer className="w-6 h-6 text-primary-500" />
          Scheduled Tasks
        </h3>
        <button
          onClick={loadStatus}
          className="text-sm text-gray-400 dark:text-slate-500 hover:text-gray-700 dark:hover:text-slate-200 flex items-center gap-1.5 px-3 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-all"
        >
          <RefreshCw className="w-3.5 h-3.5" /> Refresh
        </button>
      </div>

      {/* Health Status Card */}
      <div className={`p-5 rounded-xl border-2 ${getHealthColor()} flex items-center justify-between`}>
        <div className="flex items-center gap-4">
          {getHealthIcon()}
          <div>
            <h4 className="font-bold text-base">{getHealthLabel()}</h4>
            <p className="text-sm opacity-75 mt-0.5">
              {lastRun
                ? `Last: ${formatDateTime(lastRun.startedAt)} (${formatDuration(lastRun.durationMs)}, ${lastRun.emailsSent} sent)`
                : 'No executions recorded yet'}
            </p>
          </div>
        </div>
        <button
          onClick={handleManualRun}
          disabled={running}
          className="bg-white/80 dark:bg-slate-900/50 border border-current/20 px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 hover:bg-white dark:hover:bg-slate-900 transition-all disabled:opacity-50"
        >
          {running ? <Loader2 className="w-4 h-4 animate-spin" /> : <Play className="w-4 h-4" />}
          Run Now
        </button>
      </div>

      {/* Quick Stats */}
      {lastRun && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { label: 'Emails Sent', value: lastRun.emailsSent, icon: <Zap className="w-4 h-4 text-green-500" /> },
            { label: 'Failed', value: lastRun.emailsFailed, icon: <XCircle className="w-4 h-4 text-red-400" /> },
            { label: 'Push Sent', value: lastRun.pushSent, icon: <Activity className="w-4 h-4 text-blue-500" /> },
            { label: 'Duration', value: formatDuration(lastRun.durationMs), icon: <Clock className="w-4 h-4 text-amber-500" /> },
          ].map(stat => (
            <div key={stat.label} className="p-3.5 rounded-xl bg-gray-50/80 dark:bg-slate-800/30 border border-gray-100 dark:border-slate-700/40">
              <div className="flex items-center gap-2 mb-1">
                {stat.icon}
                <span className="text-[11px] text-gray-400 dark:text-slate-500 font-medium uppercase">{stat.label}</span>
              </div>
              <div className="text-lg font-bold text-gray-800 dark:text-white">{stat.value}</div>
            </div>
          ))}
        </div>
      )}

      {/* Settings */}
      <div className="p-5 bg-gray-50/80 dark:bg-slate-800/30 rounded-xl border border-gray-200/60 dark:border-slate-700/40 space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h4 className="font-bold text-gray-800 dark:text-white">Reminder Cron Job</h4>
            <p className="text-sm text-gray-500 dark:text-slate-400 mt-0.5">Sends email and push reminders for upcoming appointments.</p>
          </div>
          <label className="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" checked={settings.enabled} onChange={e => setSettings({ ...settings, enabled: e.target.checked })} className="sr-only peer" />
            <div className="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-primary-500/30 dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all rounded-full"></div>
          </label>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label className="block text-xs font-bold text-gray-500 dark:text-slate-500 uppercase mb-1.5">Frequency</label>
            <select
              value={settings.frequencyMinutes}
              onChange={e => setSettings({ ...settings, frequencyMinutes: parseInt(e.target.value) })}
              className="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm text-gray-800 dark:text-white focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 outline-none transition-all"
            >
              {frequencyOptions.map(o => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-bold text-gray-500 dark:text-slate-500 uppercase mb-1.5">Start Hour</label>
            <select
              value={settings.runHour}
              onChange={e => setSettings({ ...settings, runHour: parseInt(e.target.value) })}
              className="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm text-gray-800 dark:text-white focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 outline-none transition-all"
            >
              {Array.from({ length: 24 }, (_, i) => (
                <option key={i} value={i}>{String(i).padStart(2, '0')}:00</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-bold text-gray-500 dark:text-slate-500 uppercase mb-1.5">Start Minute</label>
            <select
              value={settings.runMinute}
              onChange={e => setSettings({ ...settings, runMinute: parseInt(e.target.value) })}
              className="w-full px-3 py-2.5 bg-white dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-sm text-gray-800 dark:text-white focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 outline-none transition-all"
            >
              {[0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55].map(m => (
                <option key={m} value={m}>{String(m).padStart(2, '0')}</option>
              ))}
            </select>
          </div>
        </div>

        <button
          onClick={handleSave}
          disabled={saving}
          className="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 disabled:opacity-50 transition-all shadow-sm shadow-primary-600/20"
        >
          {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
          Save Settings
        </button>
      </div>

      {/* Cron URL Info */}
      <div className="p-4 bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800 rounded-xl text-sm text-blue-700 dark:text-blue-300 space-y-2">
        <h4 className="font-bold">Plesk Cron Setup</h4>
        <p className="text-xs">Add this to Plesk &gt; Scheduled Tasks (adjust frequency to match settings above):</p>
        <code className="block text-[11px] bg-blue-100 dark:bg-blue-900/30 p-2.5 rounded-lg mt-1 break-all font-mono select-all">
          curl -s "{typeof window !== 'undefined' ? window.location.origin : 'https://your-domain.com'}/api/cron/send-reminders?token=YOUR_CRON_SECRET"
        </code>
      </div>

      {/* Execution Log */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h4 className="font-bold text-gray-800 dark:text-white flex items-center gap-2">
            <Activity className="w-4 h-4 text-primary-500" />
            Recent Executions ({logs.length})
          </h4>
          {logs.length > 0 && (
            <button
              onClick={handleClearLogs}
              disabled={clearing}
              className="text-xs text-gray-400 dark:text-slate-500 hover:text-red-500 flex items-center gap-1 px-2 py-1 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-800 transition-all"
            >
              {clearing ? <Loader2 className="w-3 h-3 animate-spin" /> : <Trash2 className="w-3 h-3" />}
              Clear old
            </button>
          )}
        </div>

        {logs.length === 0 ? (
          <div className="text-center py-8 text-gray-400 dark:text-slate-600">
            <Clock className="w-10 h-10 mx-auto mb-2 opacity-15" />
            <p className="text-sm">No executions recorded yet.</p>
            <p className="text-xs mt-1 opacity-60">Use "Run Now" or wait for the Plesk cron to execute.</p>
          </div>
        ) : (
          <div className="space-y-1.5">
            {logs.map(log => (
              <div
                key={log.id}
                className={`flex items-center justify-between p-3 rounded-xl text-sm transition-all
                  ${log.status === 'success'
                    ? 'bg-white dark:bg-slate-800/20 border border-gray-100 dark:border-slate-700/30'
                    : log.status === 'error'
                      ? 'bg-red-50/50 dark:bg-red-900/5 border border-red-100 dark:border-red-800/20'
                      : 'bg-blue-50/50 dark:bg-blue-900/5 border border-blue-100 dark:border-blue-800/20'
                  }`}
              >
                <div className="flex items-center gap-3 min-w-0">
                  {log.status === 'success' ? (
                    <CheckCircle2 className="w-4 h-4 text-green-500 flex-shrink-0" />
                  ) : log.status === 'error' ? (
                    <XCircle className="w-4 h-4 text-red-500 flex-shrink-0" />
                  ) : (
                    <Loader2 className="w-4 h-4 text-blue-500 animate-spin flex-shrink-0" />
                  )}
                  <div className="min-w-0">
                    <div className="text-[13px] text-gray-700 dark:text-slate-300 font-medium">
                      {formatDateTime(log.startedAt)}
                      <span className="text-gray-400 dark:text-slate-500 font-normal ml-2">via {log.triggeredBy}</span>
                    </div>
                    {log.error && (
                      <div className="text-[11px] text-red-500 truncate mt-0.5">{log.error}</div>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-4 flex-shrink-0 text-[11px] text-gray-400 dark:text-slate-500">
                  <span>{log.emailsSent} sent</span>
                  {log.emailsFailed > 0 && <span className="text-red-400">{log.emailsFailed} failed</span>}
                  <span>{formatDuration(log.durationMs)}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};
