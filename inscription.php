<?php
// ============================================
// À Son Image — Backend inscription
// Envoie les données du formulaire par email
// ============================================

// Configuration
$destinataire = "contact@asonimage.ch";
$sujet_admin  = "Nouvelle inscription — À Son Image";

// Autoriser les requêtes depuis le frontend
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");

// Gérer le preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Méthode non autorisée"]);
    exit;
}

// Récupérer les données
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Données invalides"]);
    exit;
}

// Champs attendus
$prenom       = htmlspecialchars($data['prenom'] ?? '');
$nom          = htmlspecialchars($data['nom'] ?? '');
$email        = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
$tel          = htmlspecialchars($data['tel'] ?? 'Non renseigné');
$dob          = htmlspecialchars($data['dob'] ?? '');
$ville        = htmlspecialchars($data['ville'] ?? '');
$eglise_yn    = htmlspecialchars($data['eglise_yn'] ?? '');
$eglise       = htmlspecialchars($data['eglise'] ?? 'Non précisé');
$source       = htmlspecialchars($data['source'] ?? '');
$source_autre = htmlspecialchars($data['source_autre'] ?? '');
$logement     = htmlspecialchars($data['logement'] ?? 'Non précisé');
$allergies    = htmlspecialchars($data['allergies'] ?? 'Aucune');
$accessibilite = htmlspecialchars($data['accessibilite'] ?? 'Aucun');
$question     = htmlspecialchars($data['question_camp'] ?? 'Pas de question');
$autres       = htmlspecialchars($data['autres_questions'] ?? 'Rien à signaler');

// Validation minimale
if (empty($prenom) || empty($nom) || empty($email) || empty($dob)) {
    http_response_code(400);
    echo json_encode(["error" => "Champs obligatoires manquants"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["error" => "Email invalide"]);
    exit;
}

// Source complète
$source_txt = $source;
if ($source === 'autre' && !empty($source_autre)) {
    $source_txt = "Autre : " . $source_autre;
}

// ============================================
// Email pour l'équipe
// ============================================
$message_admin = "
══════════════════════════════════════
  NOUVELLE INSCRIPTION — À SON IMAGE
══════════════════════════════════════

INFORMATIONS DE BASE
─────────────────────
Prénom :           $prenom
Nom :              $nom
Email :            $email
Téléphone :        $tel
Date de naissance : $dob

CONTEXTE
─────────────────────
Ville / Région :   $ville
Église :           $eglise_yn" . ($eglise_yn === 'oui' ? " — $eglise" : "") . "
Source :           $source_txt

BESOINS PRATIQUES
─────────────────────
Logement :         $logement
Allergies :        $allergies
Accessibilité :    $accessibilite

CE QUI L'AMÈNE
─────────────────────
$question

AUTRES QUESTIONS
─────────────────────
$autres

══════════════════════════════════════
";

$headers_admin   = [];
$headers_admin[] = "From: À Son Image <noreply@asonimage.ch>";
$headers_admin[] = "Reply-To: $email";
$headers_admin[] = "Content-Type: text/plain; charset=UTF-8";

$envoi_admin = mail(
    $destinataire,
    "=?UTF-8?B?" . base64_encode($sujet_admin . " — $prenom $nom") . "?=",
    $message_admin,
    implode("\r\n", $headers_admin)
);

// ============================================
// Email de confirmation pour le campeur
// ============================================
$sujet_campeur = "Inscription reçue — À Son Image";

$message_campeur = "
Salut $prenom !

Merci pour ton inscription au séminaire À Son Image (1-6 août 2025, Plateau du Vercors).

Nous avons bien reçu ta demande. Tu recevras un email de confirmation définitive d'ici quelques jours avec toutes les informations pratiques.

D'ici là, n'hésite pas à nous écrire si tu as des questions : contact@asonimage.ch

À très bientôt !
L'équipe À Son Image
";

$headers_campeur   = [];
$headers_campeur[] = "From: À Son Image <contact@asonimage.ch>";
$headers_campeur[] = "Reply-To: contact@asonimage.ch";
$headers_campeur[] = "Content-Type: text/plain; charset=UTF-8";

$envoi_campeur = mail(
    $email,
    "=?UTF-8?B?" . base64_encode($sujet_campeur) . "?=",
    $message_campeur,
    implode("\r\n", $headers_campeur)
);

// ============================================
// Sauvegarder en CSV (backup local)
// ============================================
$csv_file = __DIR__ . '/inscriptions.csv';
$csv_exists = file_exists($csv_file);

$csv_row = [
    date('Y-m-d H:i:s'),
    $prenom,
    $nom,
    $email,
    $tel,
    $dob,
    $ville,
    $eglise_yn,
    $eglise,
    $source_txt,
    $logement,
    $allergies,
    $accessibilite,
    $question,
    $autres
];

$fp = fopen($csv_file, 'a');
if (!$csv_exists) {
    fputcsv($fp, [
        'Date inscription', 'Prénom', 'Nom', 'Email', 'Téléphone',
        'Date naissance', 'Ville', 'Église (O/N)', 'Église (nom)',
        'Source', 'Logement', 'Allergies', 'Accessibilité', 'Question', 'Autres'
    ]);
}
fputcsv($fp, $csv_row);
fclose($fp);

// ============================================
// Réponse
// ============================================
if ($envoi_admin) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Inscription enregistrée"
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "error" => "Erreur lors de l'envoi. Contacte-nous directement à contact@asonimage.ch"
    ]);
}
