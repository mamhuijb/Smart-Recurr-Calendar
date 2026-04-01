
import React, { useState } from 'react';
import { Lock, ArrowRight, ShieldCheck, Calendar } from 'lucide-react';
import { BrandingSettings } from '../types';
import { api } from '../services/api';

interface LoginScreenProps {
  onLogin: (token: string) => void;
  branding?: BrandingSettings;
}

export const LoginScreen: React.FC<LoginScreenProps> = ({ onLogin, branding }) => {
  const [step, setStep] = useState<'CREDENTIALS' | '2FA'>('CREDENTIALS');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [tempToken, setTempToken] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      if (step === 'CREDENTIALS') {
        const res = await api.login(username, password);
        if (res.requires_2fa) {
          setTempToken(res.temp_token!);
          setStep('2FA');
        } else if (res.token) {
          onLogin(res.token);
        }
      } else {
        const res = await api.verify2FA(tempToken, twoFactorCode);
        onLogin(res.token);
      }
    } catch (err: any) {
      setError(err.message || 'Authentication failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 via-gray-100 to-primary-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950 flex items-center justify-center p-4 transition-colors duration-300">

      {/* Subtle background decoration */}
      <div className="fixed inset-0 overflow-hidden pointer-events-none">
        <div className="absolute -top-40 -right-40 w-80 h-80 rounded-full bg-primary-400/10 dark:bg-primary-600/5 blur-3xl" />
        <div className="absolute -bottom-40 -left-40 w-96 h-96 rounded-full bg-primary-300/10 dark:bg-primary-500/5 blur-3xl" />
      </div>

      <div className="relative w-full max-w-[400px] animate-scale-in">
        {/* Card — frosted glass effect */}
        <div className="glass rounded-3xl shadow-glass dark:shadow-glass-dark overflow-hidden border border-white/20 dark:border-slate-700/30">

          {/* Header gradient */}
          <div className="relative px-8 pt-10 pb-8 text-center">
            <div className="absolute inset-0 bg-gradient-to-b from-primary-500/8 to-transparent dark:from-primary-600/10" />
            <div className="relative">
              <div className="mx-auto w-16 h-16 rounded-2xl bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center mb-5 shadow-lg shadow-primary-600/30 transition-spring hover:scale-105">
                {branding?.logoUrl ? (
                  <img src={branding.logoUrl} className="w-9 h-9 object-contain" />
                ) : (
                  <Calendar className="w-8 h-8 text-white" />
                )}
              </div>
              <h1 className="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">SmartRecur</h1>
              <p className="text-gray-500 dark:text-slate-400 mt-1.5 text-sm font-medium">Sign in to your calendar</p>
            </div>
          </div>

          {/* Form */}
          <div className="px-8 pb-8">
            <form onSubmit={handleSubmit} className="space-y-4">

              {step === 'CREDENTIALS' ? (
                <div className="space-y-3 animate-fade-in">
                  <div>
                    <label className="block text-[13px] font-medium text-gray-600 dark:text-slate-400 mb-1.5">Username</label>
                    <input
                      type="text"
                      className="w-full px-4 py-2.5 bg-white/60 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500 outline-none transition-all placeholder-gray-400 dark:placeholder-slate-600"
                      placeholder="Enter username"
                      value={username}
                      onChange={(e) => setUsername(e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="block text-[13px] font-medium text-gray-600 dark:text-slate-400 mb-1.5">Password</label>
                    <input
                      type="password"
                      className="w-full px-4 py-2.5 bg-white/60 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500 outline-none transition-all placeholder-gray-400 dark:placeholder-slate-600"
                      placeholder="Enter password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                    />
                  </div>
                </div>
              ) : (
                <div className="animate-slide-up">
                  <div className="flex justify-center mb-4">
                    <div className="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center">
                      <ShieldCheck className="w-7 h-7 text-emerald-500" />
                    </div>
                  </div>
                  <h3 className="text-center font-semibold text-gray-800 dark:text-white mb-1.5 text-[15px]">Two-Factor Authentication</h3>
                  <p className="text-center text-[13px] text-gray-500 dark:text-slate-400 mb-5">Enter the 6-digit code from your authenticator app.</p>
                  <input
                    type="text"
                    maxLength={6}
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    aria-label="Six-digit authentication code"
                    className="w-full px-4 py-3 bg-white/60 dark:bg-slate-800/60 border border-gray-200 dark:border-slate-700 rounded-xl text-gray-900 dark:text-white text-center text-xl tracking-[0.3em] font-mono focus:ring-2 focus:ring-primary-500/40 focus:border-primary-500 outline-none transition-all"
                    placeholder="000000"
                    value={twoFactorCode}
                    onChange={(e) => setTwoFactorCode(e.target.value.replace(/\D/g, ''))}
                    onKeyDown={(e) => { if (e.key === 'Enter' && twoFactorCode.length === 6) handleSubmit(e); }}
                    autoFocus
                  />
                </div>
              )}

              {error && (
                <div className="text-red-600 dark:text-red-400 text-[13px] text-center bg-red-50 dark:bg-red-900/15 border border-red-200/60 dark:border-red-800/30 py-2.5 rounded-xl animate-fade-in">
                  {error}
                </div>
              )}

              <button
                type="submit"
                disabled={loading || (step === '2FA' && twoFactorCode.length !== 6)}
                className="w-full bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 text-white font-semibold py-3 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary-600/25 disabled:opacity-50 press-effect mt-2"
              >
                {loading ? (
                  <span className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                ) : step === 'CREDENTIALS' ? (
                  <>
                    Sign In
                    <ArrowRight className="w-4 h-4" />
                  </>
                ) : 'Verify Code'}
              </button>

              {step === '2FA' && (
                <button type="button" onClick={() => setStep('CREDENTIALS')} className="w-full text-[13px] text-gray-500 hover:text-gray-700 dark:hover:text-slate-300 transition-colors py-1">
                  Back to login
                </button>
              )}
            </form>
          </div>
        </div>
      </div>
    </div>
  );
};
