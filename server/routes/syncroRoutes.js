
import express from 'express';
import { getCustomers, createTicket } from '../controllers/syncroController.js';

const router = express.Router();

router.get('/customers', getCustomers);
router.post('/tickets', createTicket);

export default router;
