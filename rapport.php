<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = $error = '';

// Fonction pour mettre à jour le solde du jour
function mettreAJourSoldeJour($db, $date_solde = null) {
    if (!$date_solde) {
        $date_solde = date('Y-m-d');
    }
    
    $query = "UPDATE solde_caisse sc 
              SET total_depots = (SELECT COALESCE(SUM(montant), 0) FROM caisse WHERE DATE(date_operation) = sc.date_solde AND montant > 0 AND statut = 'validé'),
                  total_retraits = (SELECT COALESCE(SUM(ABS(montant)), 0) FROM caisse WHERE DATE(date_operation) = sc.date_solde AND montant < 0 AND statut = 'validé'),
                  nombre_operations = (SELECT COUNT(*) FROM caisse WHERE DATE(date_operation) = sc.date_solde AND statut = 'validé')
              WHERE sc.date_solde = :date_solde";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':date_solde', $date_solde);
    $stmt->execute();
}

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

// Enregistrer un paiement
if ($_POST && isset($_POST['enregistrer_paiement'])) {
    try {
        $etudiant_id = $_POST['etudiant_id'];
        $frais_id = $_POST['frais_id'];
        $montant_paye = $_POST['montant_paye'];
        $date_paiement = $_POST['date_paiement'];
        $mode_paiement = $_POST['mode_paiement'];
        $reference = $_POST['reference'];
        $statut = 'payé'; // Statut par défaut
        
        // Vérifier si l'étudiant existe
        $query_check_etudiant = "SELECT id FROM etudiants WHERE id = :etudiant_id";
        $stmt_check_etudiant = $db->prepare($query_check_etudiant);
        $stmt_check_etudiant->bindParam(':etudiant_id', $etudiant_id);
        $stmt_check_etudiant->execute();
        
        if ($stmt_check_etudiant->rowCount() == 0) {
            $error = "Étudiant sélectionné introuvable!";
        } else {
            // Commencer une transaction
            $db->beginTransaction();
            
            try {
                // 1. Enregistrer le paiement
                $query_paiement = "INSERT INTO paiements (etudiant_id, frais_id, montant_paye, date_paiement, mode_paiement, reference, statut) 
                                  VALUES (:etudiant_id, :frais_id, :montant_paye, :date_paiement, :mode_paiement, :reference, :statut)";
                $stmt_paiement = $db->prepare($query_paiement);
                $stmt_paiement->bindParam(':etudiant_id', $etudiant_id);
                $stmt_paiement->bindParam(':frais_id', $frais_id);
                $stmt_paiement->bindParam(':montant_paye', $montant_paye);
                $stmt_paiement->bindParam(':date_paiement', $date_paiement);
                $stmt_paiement->bindParam(':mode_paiement', $mode_paiement);
                $stmt_paiement->bindParam(':reference', $reference);
                $stmt_paiement->bindParam(':statut', $statut);
                $stmt_paiement->execute();
                
                $paiement_id = $db->lastInsertId();
                
                // 2. Enregistrer automatiquement en caisse
                // Récupérer les infos de l'étudiant et du frais pour la description
                $query_info = "SELECT e.nom, e.prenom, e.matricule, c.nom as classe_nom, f.type_frais 
                              FROM etudiants e 
                              LEFT JOIN classe c ON e.classe_id = c.id 
                              JOIN frais f ON f.id = :frais_id 
                              WHERE e.id = :etudiant_id";
                $stmt_info = $db->prepare($query_info);
                $stmt_info->bindParam(':etudiant_id', $etudiant_id);
                $stmt_info->bindParam(':frais_id', $frais_id);
                $stmt_info->execute();
                $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                
                $description = "Paiement " . $info['type_frais'] . " - " . $info['nom'] . " " . $info['prenom'] . " (" . $info['matricule'] . ") - " . $info['classe_nom'];
                
                // Déterminer la catégorie en fonction du type de frais
                $categorie = 'scolarité'; // Catégorie par défaut
                if (strpos(strtolower($info['type_frais']), 'inscription') !== false) {
                    $categorie = "Frais d'inscription";
                } elseif (strpos(strtolower($info['type_frais']), 'divers') !== false) {
                    $categorie = 'Frais divers';
                }
                
                // Enregistrer en caisse avec la nouvelle structure
                $query_caisse = "INSERT INTO caisse (type_operation, montant, date_operation, mode_operation, description, reference, categorie, utilisateur_id, paiement_id) 
                                VALUES ('dépôt', :montant, :date_operation, :mode_operation, :description, :reference, :categorie, :utilisateur_id, :paiement_id)";
                $stmt_caisse = $db->prepare($query_caisse);
                $stmt_caisse->bindParam(':montant', $montant_paye);
                $stmt_caisse->bindParam(':date_operation', $date_paiement);
                $stmt_caisse->bindParam(':mode_operation', $mode_paiement);
                $stmt_caisse->bindParam(':description', $description);
                $stmt_caisse->bindParam(':reference', $reference);
                $stmt_caisse->bindParam(':categorie', $categorie);
                $stmt_caisse->bindParam(':utilisateur_id', $_SESSION['user_id']);
                $stmt_caisse->bindParam(':paiement_id', $paiement_id);
                $stmt_caisse->execute();
                
                $operation_caisse_id = $db->lastInsertId();
                
                // Lier le paiement à l'opération de caisse
                $query_lier = "UPDATE paiements SET operation_caisse_id = :operation_caisse_id WHERE id = :paiement_id";
                $stmt_lier = $db->prepare($query_lier);
                $stmt_lier->bindParam(':operation_caisse_id', $operation_caisse_id);
                $stmt_lier->bindParam(':paiement_id', $paiement_id);
                $stmt_lier->execute();
                
                // Mettre à jour le solde du jour
                mettreAJourSoldeJour($db, $date_paiement);
                
                // Valider la transaction
                $db->commit();
                
                $success = "Paiement enregistré avec succès! Le dépôt a été automatiquement effectué en caisse.";
                $_POST = array();
                
            } catch (Exception $e) {
                // Annuler la transaction en cas d'erreur
                $db->rollBack();
                throw $e;
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Modifier le statut d'un paiement
if (isset($_GET['changer_statut'])) {
    try {
        $paiement_id = $_GET['changer_statut'];
        $nouveau_statut = $_GET['statut'];
        
        // Commencer une transaction
        $db->beginTransaction();
        
        // Récupérer les infos du paiement
        $query_info_paiement = "SELECT p.*, e.nom, e.prenom, e.matricule, c.nom as classe_nom, f.type_frais 
                               FROM paiements p 
                               JOIN etudiants e ON p.etudiant_id = e.id 
                               LEFT JOIN classe c ON e.classe_id = c.id 
                               JOIN frais f ON p.frais_id = f.id 
                               WHERE p.id = :id";
        $stmt_info_paiement = $db->prepare($query_info_paiement);
        $stmt_info_paiement->bindParam(':id', $paiement_id);
        $stmt_info_paiement->execute();
        $paiement_info = $stmt_info_paiement->fetch(PDO::FETCH_ASSOC);
        
        if ($nouveau_statut == 'payé' && $paiement_info['statut'] != 'payé') {
            // Si on passe à "payé", créer l'opération de caisse
            $description = "Paiement " . $paiement_info['type_frais'] . " - " . $paiement_info['nom'] . " " . $paiement_info['prenom'] . " (" . $paiement_info['matricule'] . ") - " . $paiement_info['classe_nom'];
            
            // Déterminer la catégorie en fonction du type de frais
            $categorie = 'scolarité'; // Catégorie par défaut
            if (strpos(strtolower($paiement_info['type_frais']), 'inscription') !== false) {
                $categorie = "Frais d'inscription";
            } elseif (strpos(strtolower($paiement_info['type_frais']), 'divers') !== false) {
                $categorie = 'Frais divers';
            }
            
            $query_caisse = "INSERT INTO caisse (type_operation, montant, date_operation, mode_operation, description, reference, categorie, utilisateur_id, paiement_id) 
                            VALUES ('dépôt', :montant, :date_operation, :mode_operation, :description, :reference, :categorie, :utilisateur_id, :paiement_id)";
            $stmt_caisse = $db->prepare($query_caisse);
            $stmt_caisse->bindParam(':montant', $paiement_info['montant_paye']);
            $stmt_caisse->bindParam(':date_operation', $paiement_info['date_paiement']);
            $stmt_caisse->bindParam(':mode_operation', $paiement_info['mode_paiement']);
            $stmt_caisse->bindParam(':description', $description);
            $stmt_caisse->bindParam(':reference', $paiement_info['reference']);
            $stmt_caisse->bindParam(':categorie', $categorie);
            $stmt_caisse->bindParam(':utilisateur_id', $_SESSION['user_id']);
            $stmt_caisse->bindParam(':paiement_id', $paiement_id);
            $stmt_caisse->execute();
            
            $operation_caisse_id = $db->lastInsertId();
            
            // Mettre à jour le paiement avec l'ID de l'opération de caisse
            $query_update = "UPDATE paiements SET statut = :statut, operation_caisse_id = :operation_caisse_id WHERE id = :id";
            $stmt_update = $db->prepare($query_update);
            $stmt_update->bindParam(':statut', $nouveau_statut);
            $stmt_update->bindParam(':operation_caisse_id', $operation_caisse_id);
            $stmt_update->bindParam(':id', $paiement_id);
            $stmt_update->execute();
            
            // Mettre à jour le solde du jour
            mettreAJourSoldeJour($db, $paiement_info['date_paiement']);
            
        } elseif ($nouveau_statut != 'payé' && $paiement_info['statut'] == 'payé') {
            // Si on retire le statut "payé", supprimer l'opération de caisse associée
            if ($paiement_info['operation_caisse_id']) {
                $query_delete_caisse = "DELETE FROM caisse WHERE id = :operation_caisse_id";
                $stmt_delete_caisse = $db->prepare($query_delete_caisse);
                $stmt_delete_caisse->bindParam(':operation_caisse_id', $paiement_info['operation_caisse_id']);
                $stmt_delete_caisse->execute();
            }
            
            $query_update = "UPDATE paiements SET statut = :statut, operation_caisse_id = NULL WHERE id = :id";
            $stmt_update = $db->prepare($query_update);
            $stmt_update->bindParam(':statut', $nouveau_statut);
            $stmt_update->bindParam(':id', $paiement_id);
            $stmt_update->execute();
            
            // Mettre à jour le solde du jour
            mettreAJourSoldeJour($db, $paiement_info['date_paiement']);
            
        } else {
            // Simple changement de statut sans affectation caisse
            $query_update = "UPDATE paiements SET statut = :statut WHERE id = :id";
            $stmt_update = $db->prepare($query_update);
            $stmt_update->bindParam(':statut', $nouveau_statut);
            $stmt_update->bindParam(':id', $paiement_id);
            $stmt_update->execute();
        }
        
        $db->commit();
        $success = "Statut du paiement mis à jour avec succès!" . 
                  ($nouveau_statut == 'payé' ? " Le dépôt a été automatiquement effectué en caisse." : 
                  ($paiement_info['statut'] == 'payé' ? " L'opération de caisse associée a été supprimée." : ""));
        
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Erreur: " . $e->getMessage();
    }
}

// Configuration de la pagination
$items_par_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_par_page;

// Récupérer l'historique des paiements avec filtres
$where_conditions = [];
$params = [];

// Filtres
$filtre_etudiant = $_GET['etudiant'] ?? '';
$filtre_classe = $_GET['classe'] ?? '';
$filtre_statut = $_GET['statut'] ?? '';
$filtre_mois = $_GET['mois'] ?? '';
$filtre_annee = $_GET['annee'] ?? '';

if (!empty($filtre_etudiant)) {
    $where_conditions[] = "p.etudiant_id = :etudiant_id";
    $params[':etudiant_id'] = $filtre_etudiant;
}

if (!empty($filtre_classe)) {
    $where_conditions[] = "e.classe_id = :classe_id";
    $params[':classe_id'] = $filtre_classe;
}

if (!empty($filtre_statut)) {
    $where_conditions[] = "p.statut = :statut";
    $params[':statut'] = $filtre_statut;
}

if (!empty($filtre_mois)) {
    $where_conditions[] = "MONTH(p.date_paiement) = :mois";
    $params[':mois'] = $filtre_mois;
}

if (!empty($filtre_annee)) {
    $where_conditions[] = "YEAR(p.date_paiement) = :annee";
    $params[':annee'] = $filtre_annee;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Requête pour le nombre total d'éléments
$query_total = "SELECT COUNT(*) as total 
               FROM paiements p 
               JOIN etudiants e ON p.etudiant_id = e.id 
               LEFT JOIN classe c ON e.classe_id = c.id 
               JOIN frais f ON p.frais_id = f.id 
               $where_clause";

$stmt_total = $db->prepare($query_total);
foreach ($params as $key => $value) {
    $stmt_total->bindValue($key, $value);
}
$stmt_total->execute();
$total_result = $stmt_total->fetch(PDO::FETCH_ASSOC);
$total_items = $total_result['total'];
$total_pages = ceil($total_items / $items_par_page);

// Assurer que la page est dans les limites
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Requête pour les données paginées
$query_paiements = "SELECT p.*, e.nom, e.prenom, e.matricule, e.classe_id,
                           c.nom as classe_nom, c.niveau as classe_niveau,
                           f.type_frais, f.montant as montant_attendu,
                           ca.id as caisse_id, ca.categorie as caisse_categorie
                   FROM paiements p 
                   JOIN etudiants e ON p.etudiant_id = e.id 
                   LEFT JOIN classe c ON e.classe_id = c.id 
                   JOIN frais f ON p.frais_id = f.id 
                   LEFT JOIN caisse ca ON p.operation_caisse_id = ca.id
                   $where_clause
                   ORDER BY p.date_paiement DESC, p.id DESC
                   LIMIT :limit OFFSET :offset";

$stmt_paiements = $db->prepare($query_paiements);
foreach ($params as $key => $value) {
    $stmt_paiements->bindValue($key, $value);
}
$stmt_paiements->bindValue(':limit', $items_par_page, PDO::PARAM_INT);
$stmt_paiements->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_paiements->execute();
$paiements = $stmt_paiements->fetchAll(PDO::FETCH_ASSOC);

// Statistiques avec les mêmes filtres
$where_stats_conditions = ["p.statut = 'payé'"];
$stats_params = [];

// Appliquer les mêmes filtres aux statistiques
if (!empty($filtre_etudiant)) {
    $where_stats_conditions[] = "p.etudiant_id = :etudiant_id";
    $stats_params[':etudiant_id'] = $filtre_etudiant;
}

if (!empty($filtre_classe)) {
    $where_stats_conditions[] = "e.classe_id = :classe_id";
    $stats_params[':classe_id'] = $filtre_classe;
}

if (!empty($filtre_mois)) {
    $where_stats_conditions[] = "MONTH(p.date_paiement) = :mois";
    $stats_params[':mois'] = $filtre_mois;
}

if (!empty($filtre_annee)) {
    $where_stats_conditions[] = "YEAR(p.date_paiement) = :annee";
    $stats_params[':annee'] = $filtre_annee;
}

$where_stats_clause = '';
if (!empty($where_stats_conditions)) {
    $where_stats_clause = "WHERE " . implode(" AND ", $where_stats_conditions);
}

// Requête pour les statistiques avec filtres
$query_stats = "SELECT 
    COUNT(*) as total_paiements,
    SUM(p.montant_paye) as total_montant,
    AVG(p.montant_paye) as moyenne_paiement,
    COUNT(DISTINCT p.etudiant_id) as etudiants_payants
FROM paiements p 
JOIN etudiants e ON p.etudiant_id = e.id 
LEFT JOIN classe c ON e.classe_id = c.id 
$where_stats_clause";

$stmt_stats = $db->prepare($query_stats);
foreach ($stats_params as $key => $value) {
    $stmt_stats->bindValue($key, $value);
}
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Statistiques supplémentaires pour les badges
$query_stats_supplementaires = "SELECT 
    COUNT(*) as total_tous_paiements,
    SUM(montant_paye) as total_tous_montants,
    COUNT(CASE WHEN statut = 'en attente' THEN 1 END) as paiements_attente,
    COUNT(CASE WHEN statut = 'annulé' THEN 1 END) as paiements_annules
FROM paiements p 
JOIN etudiants e ON p.etudiant_id = e.id 
LEFT JOIN classe c ON e.classe_id = c.id 
$where_clause";

$stmt_stats_supp = $db->prepare($query_stats_supplementaires);
foreach ($params as $key => $value) {
    $stmt_stats_supp->bindValue($key, $value);
}
$stmt_stats_supp->execute();
$stats_supp = $stmt_stats_supp->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Paiements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
   <?php 
        $page_title = "Gestion des Paiements";
        include 'layout.php'; 
    ?>
<!-- Section Rapports -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0">
            <i class="bi bi-graph-up"></i> Rapports des Paiements
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3" id="form-rapport">
            <div class="col-md-4">
                <label for="rapport_classe" class="form-label">Classe</label>
                <select class="form-control" id="rapport_classe" name="rapport_classe">
                    <option value="">Toutes les classes</option>
                    <?php foreach ($classes as $classe): ?>
                    <option value="<?php echo $classe['id']; ?>" <?php echo ($_GET['rapport_classe'] ?? '') == $classe['id'] ? 'selected' : ''; ?>>
                        <?php echo $classe['nom'] . ' - ' . $classe['niveau']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="rapport_type" class="form-label">Type de Rapport</label>
                <select class="form-control" id="rapport_type" name="rapport_type">
                    <option value="tous" <?php echo ($_GET['rapport_type'] ?? '') == 'tous' ? 'selected' : ''; ?>>Tous les étudiants</option>
                    <option value="payes" <?php echo ($_GET['rapport_type'] ?? '') == 'payes' ? 'selected' : ''; ?>>Étudiants ayant payé</option>
                    <option value="non_payes" <?php echo ($_GET['rapport_type'] ?? '') == 'non_payes' ? 'selected' : ''; ?>>Étudiants non soldés</option>
                    <option value="detail" <?php echo ($_GET['rapport_type'] ?? '') == 'detail' ? 'selected' : ''; ?>>Détail par étudiant</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="rapport_frais" class="form-label">Type de Frais</label>
                <select class="form-control" id="rapport_frais" name="rapport_frais">
                    <option value="">Tous les frais</option>
                    <?php foreach ($frais_list as $frais): ?>
                    <option value="<?php echo $frais['id']; ?>" <?php echo ($_GET['rapport_frais'] ?? '') == $frais['id'] ? 'selected' : ''; ?>>
                        <?php echo $frais['type_frais']; ?>
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
        // Génération du rapport
        if (isset($_GET['rapport_type'])) {
            $rapport_classe = $_GET['rapport_classe'] ?? '';
            $rapport_type = $_GET['rapport_type'] ?? 'tous';
            $rapport_frais = $_GET['rapport_frais'] ?? '';
            
            // Construire la requête en fonction du type de rapport
            $where_conditions_rapport = [];
            $params_rapport = [];
            
            if (!empty($rapport_classe)) {
                $where_conditions_rapport[] = "e.classe_id = :classe_id";
                $params_rapport[':classe_id'] = $rapport_classe;
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
                    ORDER BY c.nom, e.nom, e.prenom";
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
                        
                        if (!empty($rapport_classe)) {
                            $query_rapport .= " AND e.classe_id = :classe_id";
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
                        
                        if (!empty($rapport_classe)) {
                            $query_rapport .= " AND e.classe_id = :classe_id";
                        }
                        // Ajouter le paramètre frais_id pour la sous-requête
                        $params_rapport[':frais_id'] = $rapport_frais;
                    }
                    $query_rapport .= " ORDER BY c.nom, e.nom, e.prenom";
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
                    ORDER BY c.nom, e.nom, e.prenom, f.type_frais";
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
                    ORDER BY c.nom, e.nom, e.prenom";
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
                if (!empty($rapport_classe)) {
                    $classe_nom = '';
                    foreach ($classes as $classe) {
                        if ($classe['id'] == $rapport_classe) {
                            $classe_nom = $classe['nom'] . ' - ' . $classe['niveau'];
                            break;
                        }
                    }
                    $filtres_appliques[] = "Classe: $classe_nom";
                }
                
                if (!empty($rapport_frais)) {
                    $frais_nom = '';
                    foreach ($frais_list as $frais) {
                        if ($frais['id'] == $rapport_frais) {
                            $frais_nom = $frais['type_frais'];
                            break;
                        }
                    }
                    $filtres_appliques[] = "Frais: $frais_nom";
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
                            echo '<td>' . htmlspecialchars($ligne['classe_nom']) . '</td>';
                            echo '<td class="text-center">' . $ligne['nombre_paiements'] . '</td>';
                            echo '<td class="text-end fw-bold">' . number_format($ligne['total_paye'], 0, ',', ' ') . ' Kwz</td>';
                            echo '<td>' . htmlspecialchars($ligne['types_frais_payes'] ?? '-') . '</td>';
                            $total_general += $ligne['total_paye'];
                            break;
                            
                        case 'non_payes':
                            echo '<td>' . htmlspecialchars($ligne['matricule']) . '</td>';
                            echo '<td>' . htmlspecialchars($ligne['nom'] . ' ' . $ligne['prenom']) . '</td>';
                            echo '<td>' . htmlspecialchars($ligne['classe_nom']) . '</td>';
                            echo '<td><span class="badge bg-danger">' . $ligne['statut_paiement'] . '</span></td>';
                            break;
                            
                        case 'detail':
                            echo '<td>' . htmlspecialchars($ligne['matricule']) . '</td>';
                            echo '<td>' . htmlspecialchars($ligne['nom'] . ' ' . $ligne['prenom']) . '</td>';
                            echo '<td>' . htmlspecialchars($ligne['classe_nom']) . '</td>';
                            echo '<td>' . htmlspecialchars($ligne['type_frais']) . '</td>';
                            echo '<td class="text-end">' . number_format($ligne['montant_attendu'], 0, ',', ' ') . ' Kwz</td>';
                            echo '<td class="text-end">' . number_format($ligne['montant_paye'], 0, ',', ' ') . ' Kwz</td>';
                            
                            $solde_restant = $ligne['solde_restant'];
                            $classe_solde = $solde_restant > 0 ? 'text-danger fw-bold' : 'text-success';
                            echo '<td class="text-end ' . $classe_solde . '">' . number_format($solde_restant, 0, ',', ' ') . ' Kwz</td>';
                            
                            $badge_class = $ligne['statut_paiement'] == 'Soldé' ? 'bg-success' : 
                                        ($ligne['statut_paiement'] == 'Partiellement payé' ? 'bg-warning' : 'bg-danger');
                            echo '<td><span class="badge ' . $badge_class . '">' . $ligne['statut_paiement'] . '</span></td>';
                            break;
                            
                        default:
                            echo '<td>' . htmlspecialchars($ligne['matricule']) . '</td>';
                            echo '<td>' . htmlspecialchars($ligne['nom'] . ' ' . $ligne['prenom']) . '</td>';
                            echo '<td>' . htmlspecialchars($ligne['classe_nom']) . '</td>';
                            echo '<td class="text-center">' . $ligne['nombre_paiements'] . '</td>';
                            echo '<td class="text-end">' . number_format($ligne['total_paye'], 0, ',', ' ') . ' Kwz</td>';
                            
                            $badge_class = $ligne['statut_paiement'] == 'A payé' ? 'bg-success' : 'bg-danger';
                            echo '<td><span class="badge ' . $badge_class . '">' . $ligne['statut_paiement'] . '</span></td>';
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
        }
        ?>
    </div>
</div>

<script>
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
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
</body>
</html>