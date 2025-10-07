import express from 'express';
import fetch from 'node-fetch';
import cors from 'cors';

const app = express();
app.use(cors()); // permette richieste dal tuo front-end
app.use(express.json());

// URL dell'API e chiave fissa
const API_URL = 'https://www.mevacoin.com/api.php';
const API_KEY = 'desy2011'; // ← chiave fissa

app.post('/api-proxy', async (req, res) => {
    try {
        const body = req.body;

        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-KEY': API_KEY
            },
            body: JSON.stringify(body)
        });

        const data = await response.json();
        res.json(data);

    } catch (err) {
        res.status(500).json({ status: 'error', message: err.message });
    }
});

const PORT = process.env.PORT || 3000;

// 👇 qui cambiamo 'localhost' in '0.0.0.0' per renderlo accessibile da fuori
app.listen(PORT, '0.0.0.0', () => {
    console.log(`🌐 Proxy API Mevacoin attivo su http://0.0.0.0:${PORT}`);
});
