<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("ID de paiement non spécifié");
}

$paiement_id = $_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Récupérer les informations du paiement avec jointure sur la table classe
$query = "SELECT p.*, 
                 e.nom, e.prenom, e.matricule, e.telephone, e.email,
                 c.nom as classe_nom, c.niveau as classe_niveau,
                 f.type_frais, f.montant as montant_attendu,
                 u.nom_complet as caissier
          FROM paiements p 
          JOIN etudiants e ON p.etudiant_id = e.id 
          LEFT JOIN classe c ON e.classe_id = c.id 
          JOIN frais f ON p.frais_id = f.id 
          LEFT JOIN utilisateurs u ON u.id = :user_id 
          WHERE p.id = :id";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $paiement_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$paiement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$paiement) {
    die("Paiement non trouvé");
}

// Si le caissier n'est pas trouvé, utiliser l'utilisateur connecté
if (empty($paiement['caissier'])) {
    // Récupérer le nom de l'utilisateur connecté
    $query_user = "SELECT nom_complet FROM utilisateurs WHERE id = :user_id";
    $stmt_user = $db->prepare($query_user);
    $stmt_user->bindParam(':user_id', $_SESSION['user_id']);
    $stmt_user->execute();
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $paiement['caissier'] = $user['nom_complet'] ?? 'Caissier';
}

// Calculer le reste à payer
$reste_a_payer = $paiement['montant_attendu'] - $paiement['montant_paye'];

// Construire le nom complet de la classe
$classe_complete = $paiement['classe_nom'] ?? 'Non assigné';
if ($paiement['classe_niveau']) {
    $classe_complete = $paiement['classe_nom'] . ' (' . $paiement['classe_niveau'] . ')';
}

// Chemins des images (à adapter selon votre structure)
$logo_ecole = "assets/images/logo.jpg"; // Chemin vers votre logo 
$filigrane_image = "assets/images/filigrane-ecole.jpg"; // Chemin vers votre image filigrane

// Vérifier si les images existent, sinon utiliser des valeurs par défaut
$has_logo = file_exists($logo_ecole);
$has_filigrane = file_exists($filigrane_image);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu de Paiement - <?php echo $paiement['matricule']; ?></title>
    <style>
        /* Taille A5 - moitié de A4 */
        @page {
            size: A5;
            margin: 0.5cm;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            background: white;
            width: 148mm; /* Largeur A5 */
            height: 210mm; /* Hauteur A5 */
            position: relative;
        }
        
        .receipt-container {
            width: 100%;
            height: 100%;
            padding: 10mm;
            box-sizing: border-box;
            position: relative;
            z-index: 1;
            border: 1px solid #ccc;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        
        .logo-container {
            margin-bottom: 8px;
        }
        
        .logo {
            max-width: 80px;
            max-height: 60px;
            height: auto;
        }
        
        .school-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
            color: #2c3e50;
            line-height: 1.2;
        }
        
        .school-address {
            font-size: 10px;
            margin-bottom: 5px;
            color: #7f8c8d;
            line-height: 1.2;
        }
        
        .receipt-title {
            font-size: 14px;
            font-weight: bold;
            margin: 8px 0;
            text-transform: uppercase;
            color: #e74c3c;
        }
        
        .reference-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding: 6px;
            background: #f8f9fa;
            border-radius: 3px;
            font-size: 10px;
        }
        
        .info-section {
            margin-bottom: 15px;
        }
        
        .section-title {
            font-size: 12px;
            color: #2c3e50;
            border-bottom: 1px solid #3498db;
            padding-bottom: 3px;
            margin-bottom: 8px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 4px;
            font-size: 10px;
            line-height: 1.2;
        }
        
        .info-label {
            font-weight: bold;
            width: 45mm;
            flex-shrink: 0;
        }
        
        .info-value {
            flex: 1;
        }
        
        .payment-details {
            border: 1px solid #000;
            padding: 8px;
            margin: 12px 0;
            background: #f9f9f9;
            font-size: 10px;
        }
        
        .payment-title {
            font-size: 11px;
            margin-top: 0;
            border-bottom: 1px solid #e74c3c;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }
        
        .amount-in-words {
            font-style: italic;
            margin: 10px 0;
            padding: 6px;
            background: #f0f0f0;
            border-left: 2px solid #007bff;
            font-size: 9px;
            line-height: 1.2;
        }
        
        .signature-section {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            width: 55mm;
            font-size: 9px;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 25px;
            width: 100%;
        }
        
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 5px;
            line-height: 1.2;
        }
        
        /* Styles pour le filigrane */
        .watermark {
            position: fixed;
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
        }
        
        .watermark-logo {
            width: 200px;
            height: 200px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
        }
        
        .watermark-text {
            font-size: 80px;
            color: #ccc;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
        }
        
        /* Badge pour le statut */
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .mode-badge {
            background: #3498db;
            color: white;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 8px;
        }
        
        .reference-code {
            background: #f8f9fa;
            padding: 1px 3px;
            border-radius: 2px;
            border: 1px solid #dee2e6;
            font-family: monospace;
            font-size: 8px;
        }
        
        /* Styles pour l'impression */
        @media print {
            body {
                margin: 0;
                padding: 0;
                width: 148mm;
                height: 210mm;
            }
            
            .receipt-container {
                border: none;
                padding: 10mm;
                margin: 0;
            }
            
            .no-print {
                display: none;
            }
            
            .watermark {
                display: block !important;
            }
            
            .logo {
                max-width: 70px;
            }
        }
        
        .print-button {
            text-align: center;
            margin: 10px 0;
        }
        
        .btn-print {
            background: #007bff;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-print:hover {
            background: #0056b3;
        }
        
        /* Styles pour les montants */
        .montant-principal {
            font-size: 12px;
            color: #27ae60;
            font-weight: bold;
        }
        
        .montant-reste {
            font-size: 9px;
            font-weight: bold;
        }
        
        .reste-positif {
            color: #e74c3c;
        }
        
        .reste-negatif {
            color: #f39c12;
        }
        
        .reste-nul {
            color: #27ae60;
        }

        .classe-badge {
            background: #6c757d;
            color: white;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 8px;
        }
    </style>
</head>
<body>
    <!-- Filigrane image -->
    <?php if ($has_filigrane): ?>
    <div class="watermark watermark-logo">
        <img src="<?php echo $filigrane_image; ?>" alt="Filigrane" style="width: 100%; height: 100%; opacity: 0.1;">
    </div>
    <?php else: ?>
    <div class="watermark watermark-text">REÇU</div>
    <?php endif; ?>
    
    <div class="print-button no-print">
        <button class="btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimer le Reçu
        </button>
        <button class="btn-print" onclick="window.close()" style="background: #6c757d; margin-left: 10px;">
            <i class="fas fa-times"></i> Fermer
        </button>
    </div>

    <div class="receipt-container">
        <!-- En-tête avec logo -->
        <div class="header">
            <div class="logo-container">
                <?php if ($has_logo): ?>
                    <img src="<?php echo $logo_ecole; ?>" alt="Logo École" class="logo">
                <?php else: ?>
                    <div style="height: 80px; display: flex; align-items: center; justify-content: center;">
                        <div style="font-size: 18px; font-weight: bold; color: #2c3e50;">LOGO ÉCOLE</div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="school-name">COMPLEXE SCOLAIRE PRIVÉ FRANCOPHONE LES BAMBINS SAGES</div>
            <div class="school-address">
                LUANDA - ANGOLA - Tél: +244 92 99 46 399 / 92 65 93 551<br>
                Email: bambins.sages@gmail.com
            </div>
            <div class="receipt-title">REÇU DE PAIEMENT</div>
        </div>

        <!-- Informations de référence -->
        <div class="reference-section">
            <div>
                <strong>Référence:</strong> 
                <span class="reference-code"><?php echo sprintf('PAY%06d', $paiement['id']); ?></span>
            </div>
            <div>
                <strong>Date:</strong> <?php echo date('d/m/Y à H:i', strtotime($paiement['date_paiement'])); ?>
            </div>
            <div>
                <strong>Statut:</strong> 
                <span class="status-badge status-paid">PAYÉ</span>
            </div>
        </div>

        <!-- Informations de l'étudiant -->
        <div class="info-section">
            <div class="section-title">Informations de l'Élève</div>
            <div class="info-row">
                <div class="info-label">Matricule:</div>
                <div class="info-value"><?php echo htmlspecialchars($paiement['matricule']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Nom et Prénom:</div>
                <div class="info-value"><?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Classe:</div>
                <div class="info-value">
                    <span class="classe-badge"><?php echo htmlspecialchars($classe_complete); ?></span>
                </div>
            </div> 
        </div>

        <!-- Détails du paiement -->
        <div class="payment-details">
            <div class="payment-title">Détails du Paiement</div>
            <div class="info-row">
                <div class="info-label">Type de Frais:</div>
                <div class="info-value"><strong><?php echo htmlspecialchars($paiement['type_frais']); ?></strong></div>
            </div>
            <div class="info-row">
                <div class="info-label">Montant Attendu:</div>
                <div class="info-value"><?php echo number_format($paiement['montant_attendu'], 0, ',', ' '); ?> Kwz</div>
            </div>
            <div class="info-row">
                <div class="info-label">Montant Payé:</div>
                <div class="info-value">
                    <span class="montant-principal"><?php echo number_format($paiement['montant_paye'], 0, ',', ' '); ?> Kwz</span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Reste à Payer:</div>
                <div class="info-value">
                    <span class="montant-reste <?php 
                        echo $reste_a_payer > 0 ? 'reste-positif' : 
                             ($reste_a_payer < 0 ? 'reste-negatif' : 'reste-nul'); 
                    ?>">
                        <?php echo number_format($reste_a_payer, 0, ',', ' '); ?> Kwz
                        <?php if ($reste_a_payer == 0): ?>
                        <span>(Solde réglé)</span>
                        <?php elseif ($reste_a_payer > 0): ?>
                        <span>(Reste à régler)</span>
                        <?php else: ?>
                        <span>(Excédent payé)</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Mode de Paiement:</div>
                <div class="info-value">
                    <span class="mode-badge"><?php echo htmlspecialchars($paiement['mode_paiement']); ?></span>
                </div>
            </div>
            <?php if (!empty($paiement['reference'])): ?>
            <div class="info-row">
                <div class="info-label">Référence:</div>
                <div class="info-value">
                    <span class="reference-code"><?php echo htmlspecialchars($paiement['reference']); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Montant en lettres -->
        <div class="amount-in-words">
            <strong>Arrêté la présente quittance à la somme de :</strong><br>
            « <?php echo convertirMontantEnLettres($paiement['montant_paye']); ?> Kwanza angola »
        </div>

        <!-- Section signatures -->
        <div class="signature-section">
            <div class="signature-box">
                <div style="margin-bottom: 10px; font-weight: bold;">L'Élève ou son Représentant</div>
                <div class="signature-line"></div>
                <div style="margin-top: 5px; font-size: 9px;">
                    Nom et signature
                </div>
            </div>
            <div class="signature-box">
                <div style="margin-bottom: 10px; font-weight: bold;">Pour l'École, Le Caissier</div>
                <div class="signature-line"></div>
                <div style="margin-top: 5px; font-size: 9px;">
                    <?php echo htmlspecialchars($paiement['caissier']); ?><br>
                    Cachet et signature
                </div>
            </div>
        </div>

        <!-- Pied de page -->
        <div class="footer">
            <div><strong>Ce reçu est généré automatiquement et fait foi de paiement</strong></div>
            <div style="margin: 8px 0; font-size: 9px;">
                Conservez précieusement ce reçu pour toute réclamation ou justificatif
            </div>
            <div style="font-size: 8px; color: #95a5a6;">
                Reçu N°: <?php echo sprintf('REC%06d', $paiement['id']); ?> | 
                Paiement N°: <?php echo sprintf('PAY%06d', $paiement['id']); ?> |
                Généré le: <?php echo date('d/m/Y à H:i'); ?>
            </div>
        </div>
    </div>

    <script>
        // Impression automatique si demandée
        <?php if (isset($_GET['auto_print']) && $_GET['auto_print'] == '1'): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// Fonctions de conversion montant en lettres
function convertirMontantEnLettres($montant) {
    $entier = intval($montant);
    $decimal = round(($montant - $entier) * 100);
    
    $lettres = convertirNombreEnLettres($entier);
    
    if ($decimal > 0) {
        $lettres .= ' virgule ' . convertirNombreEnLettres($decimal) . ' centimes';
    }
    
    return ucfirst($lettres);
}

function convertirNombreEnLettres($nombre) {
    if ($nombre == 0) {
        return 'zéro';
    }
    
    $unites = array('', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf');
    $dizaines = array('', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix');
    $centaines = array('', 'cent', 'deux cents', 'trois cents', 'quatre cents', 'cinq cents', 'six cents', 'sept cents', 'huit cents', 'neuf cents');
    
    if ($nombre < 10) {
        return $unites[$nombre];
    } elseif ($nombre < 20) {
        switch ($nombre) {
            case 10: return 'dix';
            case 11: return 'onze';
            case 12: return 'douze';
            case 13: return 'treize';
            case 14: return 'quatorze';
            case 15: return 'quinze';
            case 16: return 'seize';
            default: return 'dix-' . $unites[$nombre - 10];
        }
    } elseif ($nombre < 100) {
        $dizaine = floor($nombre / 10);
        $unite = $nombre % 10;
        
        if ($dizaine == 7 || $dizaine == 9) {
            return $dizaines[$dizaine - 1] . '-' . convertirNombreEnLettres(10 + $unite);
        } else {
            $result = $dizaines[$dizaine];
            if ($unite > 0) {
                $result .= ($dizaine == 8 ? '-' : ' ') . $unites[$unite];
            }
            return $result;
        }
    } elseif ($nombre < 1000) {
        $centaine = floor($nombre / 100);
        $reste = $nombre % 100;
        
        $result = $centaines[$centaine];
        if ($reste > 0) {
            $result .= ' ' . convertirNombreEnLettres($reste);
        }
        return $result;
    } elseif ($nombre < 1000000) {
        $millier = floor($nombre / 1000);
        $reste = $nombre % 1000;
        
        if ($millier == 1) {
            $result = 'mille';
        } else {
            $result = convertirNombreEnLettres($millier) . ' mille';
        }
        
        if ($reste > 0) {
            $result .= ' ' . convertirNombreEnLettres($reste);
        }
        return $result;
    } else {
        return 'nombre trop grand';
    }
}
?>