import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';
import { GoogleGenAI, Type } from '@google/genai';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
// Cloud Run injects the PORT environment variable. We MUST listen on it.
const PORT = process.env.PORT || 8080;
const GEMINI_API_KEY = process.env.GEMINI_API_KEY || process.env.API_KEY || '';
const DEBUG_INGEST_URL = 'http://127.0.0.1:7242/ingest/1fd3480b-af87-4fad-bdc7-af9c063ee6d6';

app.use(express.json({ limit: '1mb' }));

const ai = GEMINI_API_KEY ? new GoogleGenAI({ apiKey: GEMINI_API_KEY }) : null;

app.post('/api/debug-log', async (req, res) => {
  try {
    const payload = req.body ?? {};
    fetch(DEBUG_INGEST_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }).catch(() => {});
  } catch (error) {
    console.error('Debug log relay failed:', error);
  }
  res.status(204).end();
});

app.post('/api/recurrence', async (req, res) => {
  const { rule } = req.body ?? {};
  if (!rule || typeof rule !== 'string') {
    return res.status(400).json({ error: 'Missing or invalid rule.' });
  }
  if (!ai) {
    return res.status(500).json({ error: 'GEMINI_API_KEY is not configured.' });
  }

  const currentYear = new Date().getFullYear();
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
      model: 'gemini-2.5-flash',
      contents: prompt,
      config: {
        responseMimeType: 'application/json',
        responseSchema: {
          type: Type.ARRAY,
          items: { type: Type.STRING },
        },
      },
    });

    if (!response.text) {
      return res.status(502).json({ error: 'Empty response from model.' });
    }

    const dates = JSON.parse(response.text);
    if (!Array.isArray(dates)) {
      return res.status(502).json({ error: 'Invalid response format.' });
    }
    const normalized = dates
      .filter((d) => typeof d === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(d))
      .sort();

    return res.json({ dates: normalized });
  } catch (error) {
    console.error('Error parsing recurrence rule:', error);
    return res.status(500).json({ error: 'Failed to interpret the recurrence rule.' });
  }
});

// Serve static files from the build directory
app.use(express.static(path.join(__dirname, 'dist')));

// Handle client-side routing by returning index.html for all non-static requests
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'dist', 'index.html'));
});

app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});