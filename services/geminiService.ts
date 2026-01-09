
import { GoogleGenAI, Type } from "@google/genai";

const ai = new GoogleGenAI({ apiKey: process.env.API_KEY });

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

  try {
    const response = await ai.models.generateContent({
      model: "gemini-2.5-flash",
      contents: prompt,
      config: {
        responseMimeType: "application/json",
        responseSchema: {
          type: Type.ARRAY,
          items: {
            type: Type.STRING
          }
        }
      }
    });

    if (response.text) {
      const dates = JSON.parse(response.text);
      // Validate simple format
      return dates.filter((d: string) => /^\d{4}-\d{2}-\d{2}$/.test(d)).sort();
    }
    return [];
  } catch (error) {
    console.error("Error parsing recurrence rule:", error);
    throw new Error("Failed to interpret the recurrence rule. Please try being more specific.");
  }
};
