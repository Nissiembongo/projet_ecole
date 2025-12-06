<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("ID de transaction non spécifié");
}

$transaction_id = $_GET['id'];
$database = new Database();
$db = $database->getConnection();

// Déterminer si c'est un paiement ou une vente
$type = $_GET['type'] ?? 'paiement'; // Par défaut paiement

if ($type == 'vente') {
    // Récupérer les informations de la vente avec jointure sur la table classe (incluant filière)
    $query = "SELECT v.*, 
                     e.nom, e.prenom, e.matricule, e.telephone, e.email,
                     c.nom as classe_nom, c.niveau as classe_niveau, c.filiere,
                     u.nom_complet as caissier
              FROM ventes v 
              JOIN etudiants e ON v.etudiant_id = e.id 
              LEFT JOIN classe c ON e.classe_id = c.id 
              LEFT JOIN utilisateurs u ON u.id = :user_id 
              WHERE v.id = :id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $transaction_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        die("Vente non trouvée");
    }
    
    // Récupérer les détails des articles de la vente
    $query_details = "SELECT dv.*, a.nom as article_nom, a.prix as prix_unitaire
                      FROM details_ventes dv
                      JOIN articles a ON dv.article_id = a.id
                      WHERE dv.vente_id = :vente_id
                      ORDER BY a.nom";
    
    $stmt_details = $db->prepare($query_details);
    $stmt_details->bindParam(':vente_id', $transaction_id);
    $stmt_details->execute();
    $details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
    
    // Préparer le titre et le type
    $titre = "REÇU DE VENTE";
    $type_transaction = "Vente de fournitures scolaires";
    $montant_total = $transaction['montant_total'] ?? 0;
    $date_transaction = $transaction['date_vente'] ?? date('Y-m-d');
    $mode_paiement = $transaction['mode_vente'] ?? 'espèces';
    $reference = $transaction['reference'] ?? '';
    $statut = $transaction['statut'] ?? 'payé';
    
} else {
    // Récupérer les informations du paiement avec jointure sur la table classe (incluant filière)
    $query = "SELECT p.*, 
                     e.nom, e.prenom, e.matricule, e.telephone, e.email,
                     c.nom as classe_nom, c.niveau as classe_niveau, c.filiere,
                     f.type_frais, f.montant as montant_attendu,
                     u.nom_complet as caissier
              FROM paiements p 
              JOIN etudiants e ON p.etudiant_id = e.id 
              LEFT JOIN classe c ON e.classe_id = c.id 
              JOIN frais f ON p.frais_id = f.id 
              LEFT JOIN utilisateurs u ON u.id = :user_id 
              WHERE p.id = :id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $transaction_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        die("Paiement non trouvé");
    }
    
    // Calculer le reste à payer
    $reste_a_payer = $transaction['montant_attendu'] - $transaction['montant_paye'];
    
    // Préparer le titre et le type
    $titre = "REÇU DE PAIEMENT";
    $type_transaction = $transaction['type_frais'] ?? 'Frais scolaire';
    $montant_total = $transaction['montant_paye'] ?? 0;
    $date_transaction = $transaction['date_paiement'] ?? date('Y-m-d');
    $mode_paiement = $transaction['mode_paiement'] ?? 'espèces';
    $reference = $transaction['reference'] ?? '';
    $statut = $transaction['statut'] ?? 'payé';
}

// Si le caissier n'est pas trouvé, utiliser l'utilisateur connecté
if (empty($transaction['caissier'])) {
    // Récupérer le nom de l'utilisateur connecté
    $query_user = "SELECT nom_complet FROM utilisateurs WHERE id = :user_id";
    $stmt_user = $db->prepare($query_user);
    $stmt_user->bindParam(':user_id', $_SESSION['user_id']);
    $stmt_user->execute();
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $transaction['caissier'] = $user['nom_complet'] ?? 'Caissier';
}

// Construire le nom complet de la classe avec filière
$classe_complete = $transaction['classe_nom'] ?? 'Non assigné';
if ($transaction['classe_niveau']) {
    $classe_complete = $transaction['classe_nom'] . ' (' . $transaction['classe_niveau'];
    if (!empty($transaction['filiere'])) {
        $classe_complete .= ' - ' . $transaction['filiere'];
    }
    $classe_complete .= ')';
} elseif (!empty($transaction['filiere'])) {
    $classe_complete .= ' (' . $transaction['filiere'] . ')';
}

// Chemins des images
$logo_ecole = "assets/images/logo.png";
$filigrane_image = "assets/images/filigrane-ecole.jpg";

// Vérifier si les images existent
$has_logo = file_exists($logo_ecole);
$has_filigrane = file_exists($filigrane_image);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titre; ?> - <?php echo $transaction['matricule']; ?></title>
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
            color: <?php echo $type == 'vente' ? '#27ae60' : '#e74c3c'; ?>;
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
            border-bottom: 1px solid <?php echo $type == 'vente' ? '#27ae60' : '#e74c3c'; ?>;
            padding-bottom: 3px;
            margin-bottom: 6px;
        }
        
        /* Styles spécifiques pour les détails de vente */
        .vente-details-table {
            width: 100%;
            font-size: 9px;
            border-collapse: collapse;
            margin: 8px 0;
        }
        
        .vente-details-table th {
            background: #f1f1f1;
            padding: 3px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .vente-details-table td {
            padding: 3px;
            border-bottom: 1px solid #eee;
        }
        
        .vente-details-table .text-end {
            text-align: right;
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

        .filiere-badge {
            background: #17a2b8;
            color: white;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 8px;
            margin-left: 3px;
        }
        
        .transaction-type-badge {
            background: <?php echo $type == 'vente' ? '#27ae60' : '#e74c3c'; ?>;
            color: white;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 8px;
            margin-left: 5px;
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
                <?php endif; ?>
            </div>
            
            <div class="school-name">COMPLEXE SCOLAIRE PRIVÉ FRANCOPHONE LES BAMBINS SAGES</div>
            <div class="school-address">
                LUANDA - ANGOLA - Tél: +244 92 99 46 399 / 92 65 93 551<br>
                Email: bambins.sages@gmail.com
            </div>
            <div class="receipt-title"><?php echo $titre; ?></div>
        </div>

        <!-- Informations de référence -->
        <div class="reference-section">
            <div>
                <strong>Référence:</strong> 
                <span class="reference-code">
                    <?php 
                    if ($type == 'vente') {
                        echo sprintf('VEN%06d', $transaction_id);
                    } else {
                        echo sprintf('PAY%06d', $transaction_id);
                    }
                    ?>
                </span>
                <span class="transaction-type-badge">
                    <?php echo $type == 'vente' ? 'VENTE' : 'PAIEMENT'; ?>
                </span>
            </div>
            <div>
                <strong>Date:</strong> <?php echo date('d/m/Y à H:i', strtotime($date_transaction)); ?>
            </div>
            <div>
                <strong>Statut:</strong> 
                <span class="status-badge status-paid"><?php echo strtoupper($statut); ?></span>
            </div>
        </div>

        <!-- Informations de l'étudiant -->
        <div class="info-section">
            <div class="section-title">Informations de l'Élève</div>
            <div class="info-row">
                <div class="info-label">Matricule:</div>
                <div class="info-value"><?php echo htmlspecialchars($transaction['matricule']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Nom et Prénom:</div>
                <div class="info-value"><?php echo htmlspecialchars($transaction['nom'] . ' ' . $transaction['prenom']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Classe:</div>
                <div class="info-value">
                    <span class="classe-badge"><?php echo htmlspecialchars($transaction['classe_nom'] ?? 'Non assigné'); ?></span>
                    <?php if ($transaction['classe_niveau']): ?>
                    <small class="text-muted">(Niveau: <?php echo htmlspecialchars($transaction['classe_niveau']); ?>)</small>
                    <?php endif; ?>
                    <?php if (!empty($transaction['filiere'])): ?>
                    <span class="filiere-badge"><?php echo htmlspecialchars($transaction['filiere']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($transaction['telephone'])): ?>
            <div class="info-row">
                <div class="info-label">Téléphone:</div>
                <div class="info-value"><?php echo htmlspecialchars($transaction['telephone']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($transaction['email'])): ?>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value"><?php echo htmlspecialchars($transaction['email']); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Détails de la transaction -->
        <div class="payment-details">
            <div class="payment-title">
                <?php echo $type == 'vente' ? 'Détails de la Vente' : 'Détails du Paiement'; ?>
            </div>
            
            <?php if ($type == 'vente'): ?>
                <!-- Détails des articles vendus -->
                <?php if (!empty($details)): ?>
                <div class="info-row">
                    <div class="info-label">Nombre d'articles:</div>
                    <div class="info-value"><strong><?php echo count($details); ?> article(s)</strong></div>
                </div>
                
                <table class="vente-details-table">
                    <thead>
                        <tr>
                            <th>Article</th>
                            <th class="text-end">Qté</th>
                            <th class="text-end">Prix unitaire</th>
                            <th class="text-end">Sous-total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_quantite = 0;
                        foreach ($details as $detail): 
                            $total_quantite += $detail['quantite'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detail['article_nom']); ?></td>
                            <td class="text-end"><?php echo $detail['quantite']; ?></td>
                            <td class="text-end"><?php echo number_format($detail['prix_unitaire'], 0, ',', ' '); ?> Kwz</td>
                            <td class="text-end"><?php echo number_format($detail['sous_total'], 0, ',', ' '); ?> Kwz</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold; background: #f1f1f1;">
                            <td>Total</td>
                            <td class="text-end"><?php echo $total_quantite; ?></td>
                            <td colspan="2" class="text-end">
                                <?php echo number_format($montant_total, 0, ',', ' '); ?> Kwz
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <?php else: ?>
                <div class="info-row">
                    <div class="info-value text-center" style="padding: 10px; color: #666;">
                        Aucun détail d'article disponible
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Détails du paiement -->
                <div class="info-row">
                    <div class="info-label">Type de Frais:</div>
                    <div class="info-value"><strong><?php echo htmlspecialchars($type_transaction); ?></strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Montant Attendu:</div>
                    <div class="info-value"><?php echo number_format($transaction['montant_attendu'], 0, ',', ' '); ?> Kwz</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Montant Payé:</div>
                    <div class="info-value">
                        <span class="montant-principal"><?php echo number_format($montant_total, 0, ',', ' '); ?> Kwz</span>
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
            <?php endif; ?>
            
            <div class="info-row">
                <div class="info-label">Montant Total:</div>
                <div class="info-value">
                    <span class="montant-principal"><?php echo number_format($montant_total, 0, ',', ' '); ?> Kwz</span>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Mode de Paiement:</div>
                <div class="info-value">
                    <span class="mode-badge"><?php echo htmlspecialchars($mode_paiement); ?></span>
                </div>
            </div>
            
            <?php if (!empty($reference)): ?>
            <div class="info-row">
                <div class="info-label">Référence:</div>
                <div class="info-value">
                    <span class="reference-code"><?php echo htmlspecialchars($reference); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="info-row">
                <div class="info-label">Description:</div>
                <div class="info-value">
                    <small class="text-muted">
                        <?php echo htmlspecialchars($type_transaction); ?>
                    </small>
                </div>
            </div>
            
            <div class="info-row">
                <div class="info-label">Classe:</div>
                <div class="info-value">
                    <small class="text-muted">
                        <?php echo htmlspecialchars($classe_complete); ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Montant en lettres -->
        <div class="amount-in-words">
            <strong>Arrêté la présente quittance à la somme de :</strong><br>
            « <?php echo convertirMontantEnLettres($montant_total); ?> Kwanza angola »
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
                    <?php echo htmlspecialchars($transaction['caissier']); ?><br>
                    Cachet et signature
                </div>
            </div>
        </div>

        <!-- Pied de page -->
        <div class="footer">
            <div><strong>Ce reçu est généré automatiquement et fait foi de transaction</strong></div>
            <div style="margin: 8px 0; font-size: 9px;">
                Conservez précieusement ce reçu pour toute réclamation ou justificatif<br>
                <small>Classe: <?php echo htmlspecialchars($classe_complete); ?></small>
            </div>
            <div style="font-size: 8px; color: #95a5a6;">
                <?php 
                if ($type == 'vente') {
                    echo 'Vente N°: ' . sprintf('VEN%06d', $transaction_id) . ' | ';
                    echo 'Reçu N°: ' . sprintf('REC-V%06d', $transaction_id) . ' | ';
                } else {
                    echo 'Paiement N°: ' . sprintf('PAY%06d', $transaction_id) . ' | ';
                    echo 'Reçu N°: ' . sprintf('REC-P%06d', $transaction_id) . ' | ';
                }
                ?>
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