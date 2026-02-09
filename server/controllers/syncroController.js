
import axios from 'axios';
import { config } from '../config/index.js';

export const getCustomers = async (req, res) => {
    try {
        if (!config.syncro.apiKey || !config.syncro.subdomain) {
            return res.status(500).json({ error: 'Syncro configuration missing in .env' });
        }

        // Production: Use the real Syncro API
        const response = await axios.get(`https://${config.syncro.subdomain}.syncromsp.com/api/v1/customers`, {
            headers: {
                'Authorization': `Bearer ${config.syncro.apiKey}`,
                'Accept': 'application/json'
            }
        });

        // Map Syncro format to our simple internal format
        const customers = response.data.customers.map(c => ({
            id: c.id.toString(),
            name: c.firstname + ' ' + c.lastname,
            company: c.business_name || c.firstname + ' ' + c.lastname,
            email: c.email,
            phone: c.phone,
            address: c.address,
            syncroId: c.id.toString()
        }));

        res.json({ customers });
    } catch (error) {
        console.error('Syncro API Error:', error.message);
        if (error.response) {
            return res.status(error.response.status).json({ error: error.response.data });
        }
        res.status(500).json({ error: 'Failed to fetch customers from Syncro' });
    }
};

export const createTicket = async (req, res) => {
    try {
        const { customerId, subject, description } = req.body;

        const response = await axios.post(`https://${config.syncro.subdomain}.syncromsp.com/api/v1/tickets`, {
            customer_id: customerId,
            subject: subject,
            description: description,
            status: 'New'
        }, {
            headers: { 'Authorization': `Bearer ${config.syncro.apiKey}` }
        });

        res.json({ ticketId: response.data.ticket.number });
    } catch (error) {
        console.error('Syncro Ticket Error:', error.message);
        res.status(500).json({ error: 'Failed to create ticket' });
    }
};
