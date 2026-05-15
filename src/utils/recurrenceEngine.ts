
/**
 * RECURRENCE ENGINE
 * Handles precise date calculations for standard MSP scheduling patterns.
 * This runs locally in the browser for instant feedback and 100% accuracy.
 */

export type Frequency = 'YEARLY' | 'HALF_YEARLY' | 'QUARTERLY' | 'MONTHLY';
export type Ordinal = '1' | '2' | '3' | '4' | 'last';
export type DayOfWeek = 0 | 1 | 2 | 3 | 4 | 5 | 6; // 0 = Sunday, 1 = Monday...
export type RecurrenceType = 'RELATIVE' | 'ABSOLUTE';

export interface RecurrenceConfig {
    frequency: Frequency;
    type: RecurrenceType;
    startMonth: number; // 0-11 (January - December)
    ordinal: Ordinal;
    dayOfWeek: DayOfWeek;
    dayOfMonth: number; // 1-31
    yearsToGenerate: number;
}

const getNthWeekdayOfMonth = (year: number, month: number, ordinal: Ordinal, dayOfWeek: DayOfWeek): Date | null => {
    const lastDay = new Date(year, month + 1, 0);
    const days: Date[] = [];
    
    // Collect all instances of the specific weekday in the month
    for (let d = 1; d <= lastDay.getDate(); d++) {
        const current = new Date(year, month, d);
        if (current.getDay() === dayOfWeek) {
            days.push(current);
        }
    }

    if (days.length === 0) return null;

    if (ordinal === 'last') {
        return days[days.length - 1];
    }

    const index = parseInt(ordinal) - 1;
    if (index >= 0 && index < days.length) {
        return days[index];
    }

    return null; // The month might not have a 5th Monday, for example
};

export const generateRecurrenceDates = (config: RecurrenceConfig): string[] => {
    const dates: string[] = [];
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Normalize today
    const currentYear = today.getFullYear();
    const endYear = currentYear + config.yearsToGenerate;

    // Determine target months based on frequency
    // E.g. Quarterly starting in Oct (9) -> 9, 0, 3, 6 (Oct, Jan, Apr, Jul)
    const targetMonths = new Set<number>();
    
    let interval = 12;
    if (config.frequency === 'HALF_YEARLY') interval = 6;
    if (config.frequency === 'QUARTERLY') interval = 3;
    if (config.frequency === 'MONTHLY') interval = 1;

    let m = config.startMonth;
    if (config.frequency === 'MONTHLY') {
        // For monthly, we target all 12 months
        for(let i=0; i<12; i++) targetMonths.add(i);
    } else {
        // For others, calculate offsets from start month
        for (let i = 0; i < 12 / interval; i++) {
            targetMonths.add((m + (i * interval)) % 12);
        }
    }

    for (let y = currentYear; y <= endYear; y++) {
        // Iterate through all months in the year
        for (let month = 0; month < 12; month++) {
            if (targetMonths.has(month)) {
                let date: Date | null = null;

                if (config.type === 'ABSOLUTE') {
                    // Handle "15th of the month" style
                    // Clamp to the last day of the month (e.g. Feb 30 -> Feb 28)
                    const maxDays = new Date(y, month + 1, 0).getDate();
                    const d = Math.min(config.dayOfMonth, maxDays);
                    date = new Date(y, month, d);
                } else {
                    // Handle "2nd Tuesday" style
                    date = getNthWeekdayOfMonth(y, month, config.ordinal, config.dayOfWeek);
                }

                if (date) {
                    // Only add if it's in the future (or today)
                    if (date >= today) {
                        // Format YYYY-MM-DD manually to avoid timezone shifts
                        const yearStr = date.getFullYear();
                        const monthStr = String(date.getMonth() + 1).padStart(2, '0');
                        const dayStr = String(date.getDate()).padStart(2, '0');
                        dates.push(`${yearStr}-${monthStr}-${dayStr}`);
                    }
                }
            }
        }
    }

    return dates.sort();
};

export const getReadableRule = (config: RecurrenceConfig): string => {
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const ordinals: Record<string, string> = { '1': 'First', '2': 'Second', '3': 'Third', '4': 'Fourth', 'last': 'Last' };
    
    const monthStr = months[config.startMonth];
    let patternStr = '';

    if (config.type === 'ABSOLUTE') {
        const d = config.dayOfMonth;
        const suffix = (d === 1 || d === 21 || d === 31) ? 'st' : (d === 2 || d === 22) ? 'nd' : (d === 3 || d === 23) ? 'rd' : 'th';
        
        if (config.frequency === 'MONTHLY') {
            patternStr = `the ${d}${suffix} day of the month`;
        } else {
            patternStr = `${monthStr} ${d}${suffix}`;
        }
    } else {
        const dayStr = days[config.dayOfWeek];
        const ordStr = ordinals[config.ordinal];
        
        if (config.frequency === 'MONTHLY') {
            patternStr = `the ${ordStr} ${dayStr} of the month`;
        } else {
            patternStr = `the ${ordStr} ${dayStr} of ${monthStr}`;
        }
    }

    if (config.frequency === 'YEARLY') {
        return `Yearly on ${patternStr}`;
    }
    if (config.frequency === 'HALF_YEARLY') {
        return `Half-Yearly on ${patternStr} (starts ${monthStr})`;
    }
    if (config.frequency === 'QUARTERLY') {
        return `Quarterly on ${patternStr} (starts ${monthStr})`;
    }
    if (config.frequency === 'MONTHLY') {
        return `Monthly on ${patternStr.replace(' of the month', '')}`;
    }
    return 'Custom Schedule';
};
