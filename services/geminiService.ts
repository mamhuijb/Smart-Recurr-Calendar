
// import { GoogleGenAI, Type } from "@google/genai";

// const apiKey = import.meta.env.VITE_GEMINI_API_KEY || '';
// const ai = new GoogleGenAI({ apiKey });

export const parseRecurrenceRule = async (rule: string): Promise<string[]> => {
  const currentYear = new Date().getFullYear();

  // We ask for dates for the next 5 years to cover future recurrences
  const prompt = `
    I need to calculate specific dates for a recurring event based on a natural language rule.
    The rule is: "${rule}".
    
    Examples of rules:
    - "The second Tuesday of October" (Implies Yearly in October)
    - "Every Friday"
    - "Last day of every quarter"
    
    Please calculate the specific dates (YYYY-MM-DD) when this event occurs for the years ${currentYear} through ${currentYear + 5}.
    Start from today's date: ${new Date().toISOString().split('T')[0]}.
    
    Return ONLY a JSON array of date strings.
  `;

  // Mock implementation for demo to avoid crash
  return new Promise((resolve) => {
    setTimeout(() => {
      // Return dummy dates for "Every Monday" or similar just to show it doesn't crash
      resolve([
        `${currentYear}-10-01`,
        `${currentYear}-10-08`,
        `${currentYear}-10-15`
      ]);
    }, 1000);
  });
};
