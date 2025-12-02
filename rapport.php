<?php
include 'config.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Récupérer la liste des classes
$query_classes = "SELECT * FROM classe ORDER BY niveau, nom";
$stmt_classes = $db->prepare($query_classes);
$stmt_classes->execute();
$classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des frais
$query_frais = "SELECT * FROM frais ORDER BY type_frais";
$stmt_frais = $db->prepare($query_frais);
$stmt_frais->execute();
$frais_list = $stmt_frais->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les années disponibles pour les filtres
$query_annees = "SELECT DISTINCT YEAR(date_paiement) as annee FROM paiements ORDER BY annee DESC";
$stmt_annees = $db->prepare($query_annees);
$stmt_annees->execute();
$annees = $stmt_annees->fetchAll(PDO::FETCH_COLUMN, 0);

// Si pas d'années dans la base, utiliser l'année courante
if (empty($annees)) {
    $annees = [date('Y')];
}
?>

<?php 
$page_title = "Rapports des Paiements";
include 'layout.php'; 
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i></a></li>
        <li class="breadcrumb-item active" aria-current="page">Rapports</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-graph-up me-2"></i>Rapports des Paiements</h2>
</div>

<!-- Section Rapports -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0">
            <i class="bi bi-graph-up"></i> Rapports des Paiements
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3" id="form-rapport">
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
            
            <div class="col-md-3">
                <label for="rapport_niveau" class="form-label">Niveau</label>
                <select class="form-control" id="rapport_niveau" name="rapport_niveau">
                    <option value="">Tous les niveaux</option>
                    <?php
                    // Récupérer les niveaux distincts depuis la base de données
                    $query_niveaux = "SELECT DISTINCT niveau FROM classe ORDER BY niveau";
                    $stmt_niveaux = $db->prepare($query_niveaux);
                    $stmt_niveaux->execute();
                    $niveaux = $stmt_niveaux->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($niveaux as $niveau): 
                    ?>
                    <option value="<?php echo htmlspecialchars($niveau); ?>" <?php echo (($_GET['rapport_niveau'] ?? '') == $niveau) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($niveau); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="rapport_classe" class="form-label">Classe</label>
                <select class="form-control" id="rapport_classe" name="rapport_classe">
                    <option value="">Toutes les classes</option>
                    <?php 
                    // Filtrer les classes par niveau si un niveau est sélectionné
                    $rapport_niveau = Validator::validateText($_GET['rapport_niveau'] ?? '');
                    $classes_filtrees = $classes;
                    if (!empty($rapport_niveau)) {
                        $classes_filtrees = array_filter($classes, function($classe) use ($rapport_niveau) {
                            return $classe['niveau'] == $rapport_niveau;
                        });
                    }
                    
                    foreach ($classes_filtrees as $classe): 
                    ?>
                    <option value="<?php echo $classe['id']; ?>" <?php echo (($_GET['rapport_classe'] ?? '') == $classe['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($classe['nom'] . ' - ' . $classe['niveau']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="rapport_type" class="form-label">Type de Rapport</label>
                <select class="form-control" id="rapport_type" name="rapport_type">
                    <option value="tous" <?php echo (($_GET['rapport_type'] ?? '') == 'tous') ? 'selected' : ''; ?>>Tous les étudiants</option>
                    <option value="payes" <?php echo (($_GET['rapport_type'] ?? '') == 'payes') ? 'selected' : ''; ?>>Étudiants ayant payé</option>
                    <option value="non_payes" <?php echo (($_GET['rapport_type'] ?? '') == 'non_payes') ? 'selected' : ''; ?>>Étudiants non soldés</option>
                    <option value="detail" <?php echo (($_GET['rapport_type'] ?? '') == 'detail') ? 'selected' : ''; ?>>Détail par étudiant</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="rapport_frais" class="form-label">Type de Frais</label>
                <select class="form-control" id="rapport_frais" name="rapport_frais">
                    <option value="">Tous les frais</option>
                    <?php foreach ($frais_list as $frais): ?>
                    <option value="<?php echo $frais['id']; ?>" <?php echo (($_GET['rapport_frais'] ?? '') == $frais['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($frais['type_frais']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="rapport_mois" class="form-label">Mois</label>
                <select class="form-control" id="rapport_mois" name="rapport_mois">
                    <option value="">Tous les mois</option>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo (($_GET['rapport_mois'] ?? '') == $i) ? 'selected' : ''; ?>>
                        <?php echo DateTime::createFromFormat('!m', $i)->format('F'); ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="rapport_annee" class="form-label">Année</label>
                <select class="form-control" id="rapport_annee" name="rapport_annee">
                    <option value="">Toutes les années</option>
                    <?php foreach ($annees as $annee): ?>
                    <option value="<?php echo $annee; ?>" <?php echo (($_GET['rapport_annee'] ?? '') == $annee) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($annee); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-eye"></i> Générer le Rapport
                    </button>
                    <button type="button" class="btn btn-success" onclick="imprimerRapport()">
                        <i class="bi bi-printer"></i> Imprimer le Rapport
                    </button>
                    <button type="button" class="btn btn-warning" onclick="exporterExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Exporter Excel
                    </button>
                </div>
            </div>
        </form>

        <?php 
        // Génération du rapport avec validation CSRF
        if (isset($_GET['rapport_type'])) {
            // Validation CSRF
            if (empty($_GET['csrf_token']) || !CSRF::validateToken($_GET['csrf_token'])) {
                echo '<div class="alert alert-danger mt-4">';
                echo '<i class="bi bi-exclamation-triangle"></i> Erreur de sécurité. Veuillez réessayer.';
                echo '</div>';
            } else {
                // Validation et nettoyage des paramètres
                $rapport_niveau = Validator::validateText($_GET['rapport_niveau'] ?? '');
                $rapport_classe = Validator::validateNumber($_GET['rapport_classe'] ?? 0) ?: '';
                $rapport_type = Validator::validateText($_GET['rapport_type'] ?? 'tous');
                $rapport_frais = Validator::validateNumber($_GET['rapport_frais'] ?? 0) ?: '';
                $rapport_mois = Validator::validateNumber($_GET['rapport_mois'] ?? 0) ?: '';
                $rapport_annee = Validator::validateNumber($_GET['rapport_annee'] ?? 0) ?: '';
                
                // Construire la requête en fonction du type de rapport
                $where_conditions_rapport = [];
                $params_rapport = [];
                
                // Ajouter le filtre par niveau
                if (!empty($rapport_niveau)) {
                    $where_conditions_rapport[] = "c.niveau = :niveau";
                    $params_rapport[':niveau'] = $rapport_niveau;
                }
                
                if (!empty($rapport_classe)) {
                    $where_conditions_rapport[] = "e.classe_id = :classe_id";
                    $params_rapport[':classe_id'] = $rapport_classe;
                }
                
                // Filtres mois et année
                if (!empty($rapport_mois)) {
                    $where_conditions_rapport[] = "MONTH(p.date_paiement) = :mois";
                    $params_rapport[':mois'] = $rapport_mois;
                }
                
                if (!empty($rapport_annee)) {
                    $where_conditions_rapport[] = "YEAR(p.date_paiement) = :annee";
                    $params_rapport[':annee'] = $rapport_annee;
                }
                
                // Gérer le filtre par type de frais différemment selon le type de rapport
                if (!empty($rapport_frais)) {
                    switch ($rapport_type) {
                        case 'payes':
                            $where_conditions_rapport[] = "f.id = :frais_id";
                            $params_rapport[':frais_id'] = $rapport_frais;
                            break;
                        case 'non_payes':
                            // Pour les non payés, on vérifie qu'ils n'ont pas payé CE frais spécifique
                            $where_conditions_rapport[] = "e.id NOT IN (
                                SELECT DISTINCT etudiant_id 
                                FROM paiements 
                                WHERE statut = 'payé' AND frais_id = :frais_id
                            )";
                            $params_rapport[':frais_id'] = $rapport_frais;
                            break;
                        case 'detail':
                            $where_conditions_rapport[] = "f.id = :frais_id";
                            $params_rapport[':frais_id'] = $rapport_frais;
                            break;
                        default:
                            $where_conditions_rapport[] = "p.frais_id = :frais_id";
                            $params_rapport[':frais_id'] = $rapport_frais;
                            break;
                    }
                }
                
                $where_clause_rapport = '';
                if (!empty($where_conditions_rapport)) {
                    $where_clause_rapport = "WHERE " . implode(" AND ", $where_conditions_rapport);
                }
                
                try {
                    switch ($rapport_type) {
                        case 'payes':
                            // Étudiants ayant payé (au moins un paiement)
                            $query_rapport = "SELECT 
                                e.id, e.matricule, e.nom, e.prenom, 
                                c.nom as classe_nom, c.niveau as classe_niveau,
                                COUNT(p.id) as nombre_paiements,
                                SUM(p.montant_paye) as total_paye,
                                GROUP_CONCAT(DISTINCT f.type_frais SEPARATOR ', ') as types_frais_payes
                            FROM etudiants e
                            LEFT JOIN classe c ON e.classe_id = c.id
                            LEFT JOIN paiements p ON e.id = p.etudiant_id AND p.statut = 'payé'
                            LEFT JOIN frais f ON p.frais_id = f.id
                            $where_clause_rapport
                            GROUP BY e.id
                            HAVING COUNT(p.id) > 0
                            ORDER BY c.niveau, c.nom, e.nom, e.prenom";
                            break;
                            
                        case 'non_payes':
                            // Étudiants n'ayant jamais payé (ou pas pour le frais spécifique)
                            if (empty($rapport_frais)) {
                                // Tous les frais - étudiants n'ayant jamais payé du tout
                                $query_rapport = "SELECT 
                                    e.id, e.matricule, e.nom, e.prenom, 
                                    c.nom as classe_nom, c.niveau as classe_niveau,
                                    'Aucun paiement' as statut_paiement
                                FROM etudiants e
                                LEFT JOIN classe c ON e.classe_id = c.id
                                WHERE e.id NOT IN (
                                    SELECT DISTINCT etudiant_id 
                                    FROM paiements 
                                    WHERE statut = 'payé'
                                )";
                                
                                if (!empty($where_conditions_rapport)) {
                                    $query_rapport .= " AND " . implode(" AND ", $where_conditions_rapport);
                                }
                            } else {
                                // Frais spécifique - étudiants n'ayant pas payé ce frais
                                $query_rapport = "SELECT 
                                    e.id, e.matricule, e.nom, e.prenom, 
                                    c.nom as classe_nom, c.niveau as classe_niveau,
                                    CONCAT('N\\'a pas payé: ', (SELECT type_frais FROM frais WHERE id = :frais_id)) as statut_paiement
                                FROM etudiants e
                                LEFT JOIN classe c ON e.classe_id = c.id
                                WHERE e.id NOT IN (
                                    SELECT DISTINCT etudiant_id 
                                    FROM paiements 
                                    WHERE statut = 'payé' AND frais_id = :frais_id
                                )";
                                
                                if (!empty($where_conditions_rapport)) {
                                    $query_rapport .= " AND " . implode(" AND ", $where_conditions_rapport);
                                }
                                // Ajouter le paramètre frais_id pour la sous-requête
                                $params_rapport[':frais_id'] = $rapport_frais;
                            }
                            $query_rapport .= " ORDER BY c.niveau, c.nom, e.nom, e.prenom";
                            break;
                            
                        case 'detail':
                            // Détail complet par étudiant
                            $query_rapport = "SELECT 
                                e.id, e.matricule, e.nom, e.prenom, 
                                c.nom as classe_nom, c.niveau as classe_niveau,
                                f.type_frais, f.montant as montant_attendu,
                                COALESCE(SUM(p.montant_paye), 0) as montant_paye,
                                (f.montant - COALESCE(SUM(p.montant_paye), 0)) as solde_restant,
                                CASE 
                                    WHEN COALESCE(SUM(p.montant_paye), 0) >= f.montant THEN 'Soldé'
                                    WHEN COALESCE(SUM(p.montant_paye), 0) > 0 THEN 'Partiellement payé'
                                    ELSE 'Non payé'
                                END as statut_paiement
                            FROM etudiants e
                            LEFT JOIN classe c ON e.classe_id = c.id
                            CROSS JOIN frais f
                            LEFT JOIN paiements p ON e.id = p.etudiant_id AND p.frais_id = f.id AND p.statut = 'payé'
                            $where_clause_rapport
                            GROUP BY e.id, f.id
                            HAVING montant_attendu > 0
                            ORDER BY c.niveau, c.nom, e.nom, e.prenom, f.type_frais";
                            break;
                            
                        default:
                            // Tous les étudiants avec résumé
                            $query_rapport = "SELECT 
                                e.id, e.matricule, e.nom, e.prenom, 
                                c.nom as classe_nom, c.niveau as classe_niveau,
                                COUNT(p.id) as nombre_paiements,
                                COALESCE(SUM(p.montant_paye), 0) as total_paye,
                                CASE 
                                    WHEN COUNT(p.id) > 0 THEN 'A payé'
                                    ELSE 'N\\'a jamais payé'
                                END as statut_paiement
                            FROM etudiants e
                            LEFT JOIN classe c ON e.classe_id = c.id
                            LEFT JOIN paiements p ON e.id = p.etudiant_id AND p.statut = 'payé'
                            $where_clause_rapport
                            GROUP BY e.id
                            ORDER BY c.niveau, c.nom, e.nom, e.prenom";
                            break;
                    }
                    
                    $stmt_rapport = $db->prepare($query_rapport);
                    foreach ($params_rapport as $key => $value) {
                        $stmt_rapport->bindValue($key, $value);
                    }
                    $stmt_rapport->execute();
                    $rapport_data = $stmt_rapport->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Afficher le rapport
                    if (count($rapport_data) > 0) {
                        echo '<div class="mt-4" id="contenu-rapport">';
                        
                        // En-tête du rapport avec les filtres appliqués
                        echo '<div class="d-flex justify-content-between align-items-center mb-3">';
                        echo '<div>';
                        echo '<h5><i class="bi bi-file-text"></i> Résultat du Rapport</h5>';
                        
                        // Afficher les filtres appliqués
                        $filtres_appliques = [];
                        
                        if (!empty($rapport_niveau)) {
                            $filtres_appliques[] = "Niveau: " . htmlspecialchars($rapport_niveau);
                        }
                        
                        if (!empty($rapport_classe)) {
                            $classe_nom = '';
                            foreach ($classes as $classe) {
                                if ($classe['id'] == $rapport_classe) {
                                    $classe_nom = htmlspecialchars($classe['nom'] . ' - ' . $classe['niveau']);
                                    break;
                                }
                            }
                            $filtres_appliques[] = "Classe: $classe_nom";
                        }
                        
                        if (!empty($rapport_frais)) {
                            $frais_nom = '';
                            foreach ($frais_list as $frais) {
                                if ($frais['id'] == $rapport_frais) {
                                    $frais_nom = htmlspecialchars($frais['type_frais']);
                                    break;
                                }
                            }
                            $filtres_appliques[] = "Frais: $frais_nom";
                        }
                        
                        if (!empty($rapport_mois)) {
                            $nom_mois = DateTime::createFromFormat('!m', $rapport_mois)->format('F');
                            $filtres_appliques[] = "Mois: " . htmlspecialchars($nom_mois);
                        }
                        
                        if (!empty($rapport_annee)) {
                            $filtres_appliques[] = "Année: " . htmlspecialchars($rapport_annee);
                        }
                        
                        $types_rapport = [
                            'tous' => 'Tous les étudiants',
                            'payes' => 'Étudiants ayant payé',
                            'non_payes' => 'Étudiants non soldés',
                            'detail' => 'Détail par étudiant'
                        ];
                        $filtres_appliques[] = "Type: " . ($types_rapport[$rapport_type] ?? $rapport_type);
                        
                        if (!empty($filtres_appliques)) {
                            echo '<small class="text-muted">' . implode(' | ', $filtres_appliques) . '</small>';
                        }
                        echo '</div>';
                        echo '<span class="badge bg-primary">' . count($rapport_data) . ' enregistrement(s)</span>';
                        echo '</div>';
                        
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-bordered table-striped">';
                        
                        // En-têtes du tableau selon le type de rapport
                        echo '<thead class="table-primary">';
                        echo '<tr>';
                        switch ($rapport_type) {
                            case 'payes':
                                echo '<th>Matricule</th>';
                                echo '<th>Nom Complet</th>';
                                echo '<th>Classe</th>';
                                echo '<th>Nb Paiements</th>';
                                echo '<th>Total Payé</th>';
                                echo '<th>Types de Frais</th>';
                                break;
                                
                            case 'non_payes':
                                echo '<th>Matricule</th>';
                                echo '<th>Nom Complet</th>';
                                echo '<th>Classe</th>';
                                echo '<th>Statut</th>';
                                break;
                                
                            case 'detail':
                                echo '<th>Matricule</th>';
                                echo '<th>Nom Complet</th>';
                                echo '<th>Classe</th>';
                                echo '<th>Type de Frais</th>';
                                echo '<th>Montant Attendu</th>';
                                echo '<th>Montant Payé</th>';
                                echo '<th>Solde Restant</th>';
                                echo '<th>Statut</th>';
                                break;
                                
                            default:
                                echo '<th>Matricule</th>';
                                echo '<th>Nom Complet</th>';
                                echo '<th>Classe</th>';
                                echo '<th>Nb Paiements</th>';
                                echo '<th>Total Payé</th>';
                                echo '<th>Statut</th>';
                                break;
                        }
                        echo '</tr>';
                        echo '</thead>';
                        
                        echo '<tbody>';
                        $total_general = 0;
                        foreach ($rapport_data as $ligne) {
                            echo '<tr>';
                            
                            switch ($rapport_type) {
                                case 'payes':
                                    echo '<td>' . htmlspecialchars($ligne['matricule']) . '</td>';
                                    echo '<td>' . htmlspecialchars($ligne['nom'] . ' ' . $ligne['prenom']) . '</td>';
                                    echo '<td>';
                                    echo '<div>';
                                    echo '<span class="badge bg-secondary">' . htmlspecialchars($ligne['classe_nom'] ?? 'Non assigné') . '</span>';
                                    if (!empty($ligne['classe_niveau'])) {
                                        echo '<br><small class="text-muted">Niveau: ' . htmlspecialchars($ligne['classe_niveau']) . '</small>';
                                    }
                                    echo '</div>';
                                    echo '</td>';
                                    echo '<td class="text-center">' . htmlspecialchars($ligne['nombre_paiements']) . '</td>';
                                    echo '<td class="text-end fw-bold">' . number_format($ligne['total_paye'], 0, ',', ' ') . ' Kwz</td>';
                                    echo '<td>' . htmlspecialchars($ligne['types_frais_payes'] ?? '-') . '</td>';
                                    $total_general += $ligne['total_paye'];
                                    break;
                                    
                                case 'non_payes':
                                    echo '<td>' . htmlspecialchars($ligne['matricule']) . '</td>';
                                    echo '<td>' . htmlspecialchars($ligne['nom'] . ' ' . $ligne['prenom']) . '</td>';
                                    echo '<td>';
                                    echo '<div>';
                                    echo '<span class="badge bg-secondary">' . htmlspecialchars($ligne['classe_nom'] ?? 'Non assigné') . '</span>';
                                    if (!empty($ligne['classe_niveau'])) {
                                        echo '<br><small class="text-muted">Niveau: ' . htmlspecialchars($ligne['classe_niveau']) . '</small>';
                                    }
                                    echo '</div>';
                                    echo '</td>';
                                    echo '<td><span class="badge bg-danger">' . htmlspecialchars($ligne['statut_paiement']) . '</span></td>';
                                    break;
                                    
                                case 'detail':
                                    echo '<td>' . htmlspecialchars($ligne['matricule']) . '</td>';
                                    echo '<td>' . htmlspecialchars($ligne['nom'] . ' ' . $ligne['prenom']) . '</td>';
                                    echo '<td>';
                                    echo '<div>';
                                    echo '<span class="badge bg-secondary">' . htmlspecialchars($ligne['classe_nom'] ?? 'Non assigné') . '</span>';
                                    if (!empty($ligne['classe_niveau'])) {
                                        echo '<br><small class="text-muted">Niveau: ' . htmlspecialchars($ligne['classe_niveau']) . '</small>';
                                    }
                                    echo '</div>';
                                    echo '</td>';
                                    echo '<td>' . htmlspecialchars($ligne['type_frais']) . '</td>';
                                    echo '<td class="text-end">' . number_format($ligne['montant_attendu'], 0, ',', ' ') . ' Kwz</td>';
                                    echo '<td class="text-end">' . number_format($ligne['montant_paye'], 0, ',', ' ') . ' Kwz</td>';
                                    
                                    $solde_restant = $ligne['solde_restant'];
                                    $classe_solde = $solde_restant > 0 ? 'text-danger fw-bold' : 'text-success';
                                    echo '<td class="text-end ' . $classe_solde . '">' . number_format($solde_restant, 0, ',', ' ') . ' Kwz</td>';
                                    
                                    $badge_class = $ligne['statut_paiement'] == 'Soldé' ? 'bg-success' : 
                                                ($ligne['statut_paiement'] == 'Partiellement payé' ? 'bg-warning' : 'bg-danger');
                                    echo '<td><span class="badge ' . $badge_class . '">' . htmlspecialchars($ligne['statut_paiement']) . '</span></td>';
                                    break;
                                    
                                default:
                                    echo '<td>' . htmlspecialchars($ligne['matricule']) . '</td>';
                                    echo '<td>' . htmlspecialchars($ligne['nom'] . ' ' . $ligne['prenom']) . '</td>';
                                    echo '<td>';
                                    echo '<div>';
                                    echo '<span class="badge bg-secondary">' . htmlspecialchars($ligne['classe_nom'] ?? 'Non assigné') . '</span>';
                                    if (!empty($ligne['classe_niveau'])) {
                                        echo '<br><small class="text-muted">Niveau: ' . htmlspecialchars($ligne['classe_niveau']) . '</small>';
                                    }
                                    echo '</div>';
                                    echo '</td>';
                                    echo '<td class="text-center">' . htmlspecialchars($ligne['nombre_paiements']) . '</td>';
                                    echo '<td class="text-end">' . number_format($ligne['total_paye'], 0, ',', ' ') . ' Kwz</td>';
                                    
                                    $badge_class = $ligne['statut_paiement'] == 'A payé' ? 'bg-success' : 'bg-danger';
                                    echo '<td><span class="badge ' . $badge_class . '">' . htmlspecialchars($ligne['statut_paiement']) . '</span></td>';
                                    break;
                            }
                            
                            echo '</tr>';
                        }
                        echo '</tbody>';
                        
                        // Pied de tableau avec totaux si applicable
                        if ($rapport_type == 'payes' && $total_general > 0) {
                            echo '<tfoot class="table-info">';
                            echo '<tr>';
                            echo '<td colspan="4" class="text-end fw-bold">Total Général:</td>';
                            echo '<td class="text-end fw-bold">' . number_format($total_general, 0, ',', ' ') . ' Kwz</td>';
                            echo '<td></td>';
                            echo '</tr>';
                            echo '</tfoot>';
                        }
                        
                        echo '</table>';
                        echo '</div>'; // fin table-responsive
                        echo '</div>'; // fin contenu-rapport
                    } else {
                        echo '<div class="alert alert-warning mt-4">';
                        echo '<i class="bi bi-exclamation-triangle"></i> Aucun résultat trouvé pour les critères sélectionnés.';
                        echo '</div>';
                    }
                    
                } catch (PDOException $e) {
                    error_log("Erreur génération rapport: " . $e->getMessage());
                    echo '<div class="alert alert-danger mt-4">';
                    echo '<i class="bi bi-exclamation-triangle"></i> Une erreur est survenue lors de la génération du rapport.';
                    echo '</div>';
                }
            }
        }
        ?>
    </div>
</div>

<?php include 'layout-end.php'; ?>

<script>
// JavaScript pour mettre à jour dynamiquement les classes selon le niveau sélectionné
document.addEventListener('DOMContentLoaded', function() {
    const niveauSelect = document.getElementById('rapport_niveau');
    const classeSelect = document.getElementById('rapport_classe');
    
    niveauSelect.addEventListener('change', function() {
        const niveau = this.value;
        
        // Recharger la page avec le nouveau filtre de niveau
        if (niveau) {
            const url = new URL(window.location);
            url.searchParams.set('rapport_niveau', niveau);
            // Supprimer le filtre de classe si le niveau change
            url.searchParams.delete('rapport_classe');
            window.location.href = url.toString();
        } else {
            // Si aucun niveau n'est sélectionné, recharger sans filtre
            const url = new URL(window.location);
            url.searchParams.delete('rapport_niveau');
            url.searchParams.delete('rapport_classe');
            window.location.href = url.toString();
        }
    });
});

function imprimerRapport() {
    if (!document.getElementById('contenu-rapport')) {
        alert('Veuillez d\'abord générer un rapport avant d\'imprimer.');
        return;
    }
    
    var contenu = document.getElementById('contenu-rapport').innerHTML;
    var titre = 'Rapport des Paiements - ' + new Date().toLocaleDateString();
    
    var fenetreImpression = window.open('', '_blank');
    fenetreImpression.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>${titre}</title>
            <link href="assets/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @media print {
                    body { margin: 0; padding: 20px; }
                    .table { font-size: 12px; }
                    .badge { font-size: 10px; }
                }
                .header-print { 
                    text-align: center; 
                    margin-bottom: 20px;
                    border-bottom: 2px solid #333;
                    padding-bottom: 10px;
                }
            </style>
        </head>
        <body>
            <div class="header-print">
                <h3>Rapport des Paiements</h3>
                <p>Généré le: ${new Date().toLocaleDateString()}</p>
            </div>
            ${contenu}
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(function() {
                        window.close();
                    }, 500);
                }
            <\/script>
        </body>
        </html>
    `);
    fenetreImpression.document.close();
}

function exporterExcel() {
    if (!document.getElementById('contenu-rapport')) {
        alert('Veuillez d\'abord générer un rapport avant d\'exporter.');
        return;
    }
    
    var table = document.querySelector('#contenu-rapport table');
    var html = table.outerHTML;
    
    // Créer un fichier Excel
    var uri = 'data:application/vnd.ms-excel;base64,';
    var template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body><table>{table}</table></body></html>'; 
    
    var base64 = function(s) { return window.btoa(unescape(encodeURIComponent(s))); };
    var format = function(s, c) { return s.replace(/{(\w+)}/g, function(m, p) { return c[p]; }); };
    
    var ctx = { worksheet: 'Rapport', table: html };
    
    var link = document.createElement("a");
    link.download = "rapport_paiements_" + new Date().toISOString().split('T')[0] + ".xls";
    link.href = uri + base64(format(template, ctx));
    link.click();
}
</script>