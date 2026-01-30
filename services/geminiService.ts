
export const parseRecurrenceRule = async (rule: string): Promise<string[]> => {
  try {
    // #region agent log
    fetch('/api/debug-log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'geminiService.ts:parseRecurrenceRule:start',message:'parse recurrence rule',data:{ruleLength:rule.length},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-change',hypothesisId:'H4'})}).catch(()=>{});
    // #endregion
    const response = await fetch('/api/recurrence', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ rule }),
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    const payload = await response.json();
    const dates = Array.isArray(payload?.dates) ? payload.dates : [];
    // #region agent log
    fetch('/api/debug-log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'geminiService.ts:parseRecurrenceRule:ok',message:'parse recurrence rule ok',data:{datesCount:dates.length},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-change',hypothesisId:'H4'})}).catch(()=>{});
    // #endregion
    return dates
      .filter((d: string) => typeof d === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(d))
      .sort();
  } catch (error) {
    // #region agent log
    fetch('/api/debug-log',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'geminiService.ts:parseRecurrenceRule:error',message:'parse recurrence rule failed',data:{errorName:(error as Error)?.name || 'unknown'},timestamp:Date.now(),sessionId:'debug-session',runId:'pre-change',hypothesisId:'H4'})}).catch(()=>{});
    // #endregion
    console.error("Error parsing recurrence rule:", error);
    throw new Error("Failed to interpret the recurrence rule. Please try being more specific.");
  }
};
