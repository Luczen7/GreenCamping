<?php
/**
 * send.php — Green Camping Tampolo
 * Reçoit le formulaire de réservation et envoie via l'API Brevo (côté serveur)
 * La clé API n'est JAMAIS exposée côté client
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier que c'est bien une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// ═══════════════════════════════════════════════════════════════
// ⚠️ CONFIGURATION — REMPLACEZ CETTE VALEUR :
// ═══════════════════════════════════════════════════════════════
$BREVO_API_KEY = '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';  // Votre clé API v3 Brevo
$BREVO_LIST_ID = null; // ID de liste Brevo (optionnel)
// ═══════════════════════════════════════════════════════════════

// Récupérer les données JSON envoyées par le formulaire
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

// Validation des champs obligatoires
$required = ['NOM', 'EMAIL', 'TELEPHONE', 'FORFAIT', 'PARTICIPANTS', 'DATE'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champ manquant : ' . $field]);
        exit;
    }
}

// Nettoyage des données
$nom = htmlspecialchars(trim($data['NOM']), ENT_QUOTES, 'UTF-8');
$email = filter_var(trim($data['EMAIL']), FILTER_SANITIZE_EMAIL);
$telephone = htmlspecialchars(trim($data['TELEPHONE']), ENT_QUOTES, 'UTF-8');
$forfait = htmlspecialchars(trim($data['FORFAIT']), ENT_QUOTES, 'UTF-8');
$participants = intval($data['PARTICIPANTS']);
$date = htmlspecialchars(trim($data['DATE']), ENT_QUOTES, 'UTF-8');
$message = !empty($data['MESSAGE']) ? htmlspecialchars(trim($data['MESSAGE']), ENT_QUOTES, 'UTF-8') : 'Aucun message';

// Validation email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email invalide']);
    exit;
}

// ═══════════════════════════════════════════════════════════════
// ÉTAPE 1 : Créer le contact dans Brevo
// ═══════════════════════════════════════════════════════════════
$contactPayload = [
    'email' => $email,
    'attributes' => [
        'NOM' => $nom,
        'TELEPHONE' => $telephone,
        'FORFAIT' => $forfait,
        'PARTICIPANTS' => $participants,
        'DATE' => $date,
        'MESSAGE' => $message,
        'SOURCE' => 'Site Web greencamping-tampolo.com'
    ],
    'updateEnabled' => true
];

if ($BREVO_LIST_ID) {
    $contactPayload['listIds'] = [intval($BREVO_LIST_ID)];
}

$ch1 = curl_init('https://api.brevo.com/v3/contacts');
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_POST, true);
curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($contactPayload));
curl_setopt($ch1, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'api-key: ' . $BREVO_API_KEY,
    'content-type: application/json'
]);
$contactResponse = curl_exec($ch1);
$contactHttpCode = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

// Le contact peut déjà exister (code 204 = OK aussi)
if ($contactHttpCode !== 201 && $contactHttpCode !== 204 && $contactHttpCode !== 400) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du contact Brevo']);
    exit;
}

// ═══════════════════════════════════════════════════════════════
// ÉTAPE 2 : Envoyer l'email transactionnel
// ═══════════════════════════════════════════════════════════════
$emailPayload = [
    'sender' => [
        'name' => 'Green Camping Tampolo',
        'email' => 'green.campingfen@gmail.com'
    ],
    'to' => [
        [
            'email' => 'green.campingfen@gmail.com',
            'name' => 'Green Camping Tampolo'
        ]
    ],
    'replyTo' => [
        'email' => $email,
        'name' => $nom
    ],
    'subject' => 'Nouvelle réservation — ' . $nom . ' (' . $forfait . ')',
    'htmlContent' => '
        <h2 style="color:#00BA61;">Nouvelle réservation Green Camping Tampolo !</h2>
        <table style="font-family:Arial,sans-serif;border-collapse:collapse;width:100%;max-width:600px;">
            <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Nom</td><td style="padding:8px;border-bottom:1px solid #eee;">' . $nom . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Email</td><td style="padding:8px;border-bottom:1px solid #eee;">' . $email . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Téléphone</td><td style="padding:8px;border-bottom:1px solid #eee;">' . $telephone . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Forfait</td><td style="padding:8px;border-bottom:1px solid #eee;">' . $forfait . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Participants</td><td style="padding:8px;border-bottom:1px solid #eee;">' . $participants . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;">Date souhaitée</td><td style="padding:8px;border-bottom:1px solid #eee;">' . $date . '</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #eee;font-weight:bold;vertical-align:top;">Message</td><td style="padding:8px;border-bottom:1px solid #eee;">' . nl2br($message) . '</td></tr>
        </table>
        <p style="margin-top:20px;color:#666;font-size:12px;">Envoyé depuis <a href="https://greencamping-tampolo.com">greencamping-tampolo.com</a></p>
    '
];

$ch2 = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($emailPayload));
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'accept: application/json',
    'api-key: ' . $BREVO_API_KEY,
    'content-type: application/json'
]);
$emailResponse = curl_exec($ch2);
$emailHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($emailHttpCode === 201 || $emailHttpCode === 202) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Réservation envoyée avec succès']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l'envoi de l'email']);
}
?>