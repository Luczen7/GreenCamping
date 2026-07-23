/**
 * api/send.js — Green Camping Tampolo
 * Serverless Function Vercel qui appelle l'API Brevo
 * La clé API est cachée dans les variables d'environnement Vercel
 */

export default async function handler(req, res) {
  // Autoriser CORS
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }

  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, message: 'Méthode non autorisée' });
  }

  const data = req.body;

  // Validation
  const required = ['NOM', 'EMAIL', 'TELEPHONE', 'FORFAIT', 'PARTICIPANTS', 'DATE'];
  for (const field of required) {
    if (!data[field]) {
      return res.status(400).json({ success: false, message: 'Champ manquant : ' + field });
    }
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(data.EMAIL)) {
    return res.status(400).json({ success: false, message: 'Email invalide' });
  }

  const BREVO_API_KEY = process.env.BREVO_API_KEY;
  const BREVO_LIST_ID = process.env.BREVO_LIST_ID || null;

  if (!BREVO_API_KEY) {
    console.error("BREVO_API_KEY non configurée dans les variables d'environnement Vercel");
    return res.status(500).json({ success: false, message: 'Erreur serveur : clé API non configurée' });
  }

  const nom = data.NOM.trim();
  const email = data.EMAIL.trim();
  const telephone = data.TELEPHONE.trim();
  const forfait = data.FORFAIT;
  const participants = parseInt(data.PARTICIPANTS);
  const date = data.DATE;
  const message = data.MESSAGE || 'Aucun message';

  try {
    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 1 : Créer le contact dans Brevo
    // ═══════════════════════════════════════════════════════════════
    const contactPayload = {
      email: email,
      attributes: {
        NOM: nom,
        TELEPHONE: telephone,
        FORFAIT: forfait,
        PARTICIPANTS: participants,
        DATE: date,
        MESSAGE: message,
        SOURCE: 'Site Web greencamping-tampolo.com'
      },
      updateEnabled: true
    };

    if (BREVO_LIST_ID) {
      contactPayload.listIds = [parseInt(BREVO_LIST_ID)];
    }

    const contactRes = await fetch('https://api.brevo.com/v3/contacts', {
      method: 'POST',
      headers: {
        'accept': 'application/json',
        'api-key': BREVO_API_KEY,
        'content-type': 'application/json'
      },
      body: JSON.stringify(contactPayload)
    });

    // 201 = créé, 204 = mis à jour, 400 = déjà existant → tous OK
    if (![201, 204, 400].includes(contactRes.status)) {
      console.error('Erreur contact Brevo:', contactRes.status, await contactRes.text());
    }

    // ═══════════════════════════════════════════════════════════════
    // ÉTAPE 2 : Envoyer l'email transactionnel
    // ═══════════════════════════════════════════════════════════════
    const emailPayload = {
      sender: {
        name: 'Green Camping Tampolo',
        email: 'green.campingfen@gmail.com'
      },
      to: [
        {
          email: 'green.campingfen@gmail.com',
          name: 'Green Camping Tampolo'
        }
      ],
      replyTo: {
        email: email,
        name: nom
      },
      subject: 'Nouvelle réservation — ' + nom + ' (' + forfait + ')',
      htmlContent: `
        <h2 style="color:#00BA61;">Nouvelle réservation Green Camping Tampolo !</h2>
        <table style="font-family:Arial,sans-serif;border-collapse:collapse;width:100%;max-width:600px;">
          <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Nom</td><td style="padding:8px;border-bottom:1px solid #eee;">${nom}</td></tr>
          <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Email</td><td style="padding:8px;border-bottom:1px solid #eee;">${email}</td></tr>
          <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Téléphone</td><td style="padding:8px;border-bottom:1px solid #eee;">${telephone}</td></tr>
          <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Forfait</td><td style="padding:8px;border-bottom:1px solid #eee;">${forfait}</td></tr>
          <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Participants</td><td style="padding:8px;border-bottom:1px solid #eee;">${participants}</td></tr>
          <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Date souhaitée</td><td style="padding:8px;border-bottom:1px solid #eee;">${date}</td></tr>
          <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;vertical-align:top;">Message</td><td style="padding:8px;border-bottom:1px solid #eee;">${message.replace(/\n/g, '<br>')}</td></tr>
        </table>
        <p style="margin-top:20px;color:#666;font-size:12px;">Envoyé depuis <a href="https://greencamping-tampolo.com">greencamping-tampolo.com</a></p>
      `
    };

    const emailRes = await fetch('https://api.brevo.com/v3/smtp/email', {
      method: 'POST',
      headers: {
        'accept': 'application/json',
        'api-key': BREVO_API_KEY,
        'content-type': 'application/json'
      },
      body: JSON.stringify(emailPayload)
    });

    if (emailRes.status === 201 || emailRes.status === 202) {
      return res.status(200).json({ success: true, message: 'Réservation envoyée avec succès' });
    } else {
      const errorText = await emailRes.text();
      console.error('Erreur email Brevo:', emailRes.status, errorText);
      return res.status(500).json({ success: false, message: "Erreur lors de l'envoi de l'email" });
    }

  } catch (error) {
    console.error('Erreur serveur:', error);
    return res.status(500).json({ success: false, message: 'Erreur serveur : ' + error.message });
  }
}