
import React, { useState } from 'react';
import { Lock, ArrowRight, ShieldCheck } from 'lucide-react';
import { BrandingSettings, SecuritySettings } from '../types';
import { verifyToken } from '../utils/authSecurity';

interface LoginScreenProps {
  onLogin: () => void;
  branding?: BrandingSettings;
  security?: SecuritySettings;
}

export const LoginScreen: React.FC<LoginScreenProps> = ({ onLogin, branding, security }) => {
  const [step, setStep] = useState<'CREDENTIALS' | '2FA'>('CREDENTIALS');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const [error, setError] = useState('');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    // #region agent log
    fetch('/api/debug-log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'LoginScreen.tsx:handleSubmit:start',message:'login submit',data:{step,usernameLength:username.length,twoFactorEnabled:!!security?.twoFactorEnabled},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-change',hypothesisId:'H1'})}).catch(()=>{});
    // #endregion
    if (step === 'CREDENTIALS') {
        if (username === 'webmaster' && password === 'ngramO3365!@#21') {
            if (security?.twoFactorEnabled) {
                // #region agent log
                fetch('/api/debug-log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'LoginScreen.tsx:handleSubmit:credentials-ok-2fa',message:'credentials ok, 2fa required',data:{step},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-change',hypothesisId:'H1'})}).catch(()=>{});
                // #endregion
                setStep('2FA');
                setError('');
            } else {
                // #region agent log
                fetch('/api/debug-log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'LoginScreen.tsx:handleSubmit:credentials-ok',message:'credentials ok, logging in',data:{step},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-change',hypothesisId:'H1'})}).catch(()=>{});
                // #endregion
                onLogin();
            }
        } else {
            // #region agent log
            fetch('/api/debug-log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'LoginScreen.tsx:handleSubmit:credentials-fail',message:'credentials invalid',data:{step},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-change',hypothesisId:'H1'})}).catch(()=>{});
            // #endregion
            setError('Invalid credentials');
        }
    } else {
        // Real-world TOTP Validation
        if (security?.twoFactorSecret && verifyToken(twoFactorCode, security.twoFactorSecret)) { 
            // #region agent log
            fetch('/api/debug-log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'LoginScreen.tsx:handleSubmit:2fa-ok',message:'2fa ok, logging in',data:{step,twoFactorDigits:twoFactorCode.length},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-change',hypothesisId:'H1'})}).catch(()=>{});
            // #endregion
            onLogin();
        } else {
            // #region agent log
            fetch('/api/debug-log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'LoginScreen.tsx:handleSubmit:2fa-fail',message:'2fa invalid',data:{step,twoFactorDigits:twoFactorCode.length},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-change',hypothesisId:'H1'})}).catch(()=>{});
            // #endregion
            setError('Invalid 2FA Code');
        }
    }
  };

  return (
    <div className="min-h-screen bg-gray-100 dark:bg-slate-950 flex items-center justify-center p-4 transition-colors duration-200">
      <div className="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-gray-200 dark:border-slate-800">
        <div className="bg-primary-600 p-8 text-center relative overflow-hidden">
          <div className="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-primary-500 to-primary-800 opacity-90"></div>
          <div className="relative z-10">
            <div className="mx-auto bg-white/20 w-16 h-16 rounded-full flex items-center justify-center mb-4 backdrop-blur-sm shadow-lg">
                {branding?.logoUrl ? (
                     <img src={branding.logoUrl} className="w-10 h-10 object-contain" />
                ) : (
                    <Lock className="w-8 h-8 text-white" />
                )}
            </div>
            <h1 className="text-2xl font-bold text-white tracking-wide">SmartRecur</h1>
            <p className="text-primary-100 mt-2 text-sm font-medium">MSP Calendar Management</p>
          </div>
        </div>
        
        <div className="p-8">
          <form onSubmit={handleSubmit} className="space-y-6">
            
            {step === 'CREDENTIALS' ? (
                <>
                    <div>
                    <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-1">Username</label>
                    <input
                        type="text"
                        className="w-full px-4 py-3 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all placeholder-gray-400 dark:placeholder-slate-600"
                        placeholder="Username"
                        value={username}
                        onChange={(e) => setUsername(e.target.value)}
                    />
                    </div>
                    
                    <div>
                    <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-1">Password</label>
                    <input
                        type="password"
                        className="w-full px-4 py-3 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all placeholder-gray-400 dark:placeholder-slate-600"
                        placeholder="••••••••"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                    />
                    </div>
                </>
            ) : (
                <div className="animate-in fade-in slide-in-from-right-4">
                    <div className="flex justify-center mb-4">
                        <div className="bg-green-100 p-3 rounded-full">
                            <ShieldCheck className="w-8 h-8 text-green-600" />
                        </div>
                    </div>
                    <h3 className="text-center font-bold text-gray-800 dark:text-white mb-4">Two-Factor Authentication</h3>
                    <p className="text-center text-sm text-gray-500 dark:text-slate-400 mb-6">Please enter the 6-digit code from your authenticator app.</p>
                    
                    <label className="block text-sm font-medium text-gray-600 dark:text-slate-400 mb-1">Authentication Code</label>
                    <input
                        type="text"
                        maxLength={6}
                        className="w-full px-4 py-3 bg-gray-50 dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-lg text-gray-900 dark:text-white text-center text-xl tracking-widest font-mono focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition-all"
                        placeholder="000000"
                        value={twoFactorCode}
                        onChange={(e) => setTwoFactorCode(e.target.value.replace(/\D/g, ''))}
                        autoFocus
                    />
                </div>
            )}

            {error && (
              <div className="text-red-500 dark:text-red-400 text-sm text-center bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-900/50 py-2 rounded-lg">
                {error}
              </div>
            )}

            <button
              type="submit"
              className="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 rounded-lg transition-colors flex items-center justify-center gap-2 group shadow-lg shadow-primary-900/50"
            >
              {step === 'CREDENTIALS' ? (
                  <>
                    Sign In
                    <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                  </>
              ) : 'Verify Code'}
            </button>
            
            {step === '2FA' && (
                <button type="button" onClick={() => setStep('CREDENTIALS')} className="w-full text-sm text-gray-500 hover:text-gray-700 dark:hover:text-slate-300">
                    Back to login
                </button>
            )}
          </form>
        </div>
      </div>
    </div>
  );
};
