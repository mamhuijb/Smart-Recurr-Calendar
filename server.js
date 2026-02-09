import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';
import cors from 'cors';
import helmet from 'helmet';
import dotenv from 'dotenv';

import syncroRoutes from './server/routes/syncroRoutes.js';
import authRoutes from './server/routes/authRoutes.js';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 8080;

// Middleware
app.use(helmet({
  contentSecurityPolicy: false // Disabled for dev flexibility, enable in strict prod
}));
app.use(cors());
app.use(express.json());

// API Routes
app.use('/api/syncro', syncroRoutes);
app.use('/api/auth', authRoutes);

// Mock Health Check
app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', environment: process.env.NODE_ENV });
});

// Serve static files from the build directory
app.use(express.static(path.join(__dirname, 'dist')));

// Handle client-side routing by returning index.html for all non-static requests
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'dist', 'index.html'));
});

app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
  console.log(`API Key Status: ${process.env.SYNCRO_API_KEY ? 'Loaded' : 'Missing'}`);
});