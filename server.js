import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
// Cloud Run injects the PORT environment variable. We MUST listen on it.
const PORT = process.env.PORT || 8080;

// Serve static files from the build directory
app.use(express.static(path.join(__dirname, 'dist')));

// Handle client-side routing by returning index.html for all non-static requests
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'dist', 'index.html'));
});

app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});