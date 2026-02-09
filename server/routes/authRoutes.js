
import express from 'express';
import { getAuthUrl, handleCallback, getEvents } from '../controllers/authController.js';

const router = express.Router();

router.get('/url', getAuthUrl);
router.get('/callback', handleCallback);
router.get('/events', getEvents);

export default router;
