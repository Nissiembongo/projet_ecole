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

    <div class="container-fluid mt-4">
        <div class="row"> 
            <div class="col-md-12"> 
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- En-tête avec bouton -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-credit-card me-2"></i>Gestion des Paiements</h2>
                    <div>
                        <span class="badge bg-info me-2">
                            Total: <?php echo $stats_supp['total_tous_paiements'] ?? 0; ?> paiements
                        </span>
                        <span class="badge bg-warning me-2">
                            En attente: <?php echo $stats_supp['paiements_attente'] ?? 0; ?>
                        </span>
                        <span class="badge bg-danger me-2">
                            Annulés: <?php echo $stats_supp['paiements_annules'] ?? 0; ?>
                        </span>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterPaiementModal">
                            <i class="bi bi-plus-circle"></i> Nouveau Paiement
                        </button>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0"><i class="bi bi-funnel"></i> Filtres</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="filtre_classe" class="form-label">Classe</label>
                                <select class="form-control" id="filtre_classe" name="classe">
                                    <option value="">Toutes les classes</option>
                                    <?php foreach ($classes as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" <?php echo ($filtre_classe == $classe['id']) ? 'selected' : ''; ?>>
                                        <?php echo $classe['nom']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filtre_statut" class="form-label">Statut</label>
                                <select class="form-control" id="filtre_statut" name="statut">
                                    <option value="">Tous les statuts</option>
                                    <option value="payé" <?php echo ($filtre_statut == 'payé') ? 'selected' : ''; ?>>Payé</option>
                                    <option value="en attente" <?php echo ($filtre_statut == 'en attente') ? 'selected' : ''; ?>>En attente</option>
                                    <option value="annulé" <?php echo ($filtre_statut == 'annulé') ? 'selected' : ''; ?>>Annulé</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="filtre_mois" class="form-label">Mois</label>
                                <select class="form-control" id="filtre_mois" name="mois">
                                    <option value="">Tous les mois</option>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($filtre_mois == $i) ? 'selected' : ''; ?>>
                                            <?php echo DateTime::createFromFormat('!m', $i)->format('F'); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="filtre_annee" class="form-label">Année</label>
                                <select class="form-control" id="filtre_annee" name="annee">
                                    <option value="">Toutes les années</option>
                                    <?php for ($i = date('Y') - 5; $i <= date('Y') + 1; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($filtre_annee == $i) ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Résumé des filtres actifs -->
                <?php if (!empty($filtre_classe) || !empty($filtre_statut) || !empty($filtre_mois)|| !empty($filtre_annee) ): ?>
                    <div class="alert alert-info mb-4">
                        <h6><i class="bi bi-info-circle"></i> Filtres actifs :</h6>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <?php if (!empty($filtre_classe)): 
                                $classe_nom = '';
                                foreach ($classes as $classe) {
                                    if ($classe['id'] == $filtre_classe) {
                                        $classe_nom = $classe['nom'];
                                        break;
                                    }
                                }
                            ?>
                            <span class="badge bg-primary">
                                Classe: <?php echo $classe_nom; ?>
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['classe' => ''])); ?>" class="text-white ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($filtre_statut)): ?>
                            <span class="badge bg-success">
                                Statut: <?php echo ucfirst($filtre_statut); ?>
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['statut' => ''])); ?>" class="text-white ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($filtre_mois)): ?>
                            <span class="badge bg-info">
                                Mois: <?php echo DateTime::createFromFormat('!m', $filtre_mois)->format('F'); ?>
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['mois' => ''])); ?>" class="text-white ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($filtre_annee) && $filtre_annee != date('Y')): ?>
                            <span class="badge bg-secondary">
                                Année: <?php echo $filtre_annee; ?>
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['annee' => ''])); ?>" class="text-white ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                            <?php endif; ?>

                            <a href="?" class="badge bg-danger">
                                <i class="bi bi-x-circle"></i> Supprimer tous les filtres
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Statistiques avec filtres appliqués -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo number_format($stats['total_montant'] ?? 0, 0, ',', ' '); ?> Kwz</h4>
                                        <small>Total Collecté</small>
                                        <?php if (!empty($filtre_statut) || !empty($filtre_classe) || !empty($filtre_mois)): ?>
                                        <div class="mt-1">
                                            <small><i class="bi bi-funnel"></i> Filtres appliqués</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-cash-coin fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stats['total_paiements'] ?? 0; ?></h4>
                                        <small>Paiements Validés</small>
                                        <?php if (!empty($filtre_statut) || !empty($filtre_classe) || !empty($filtre_mois)): ?>
                                        <div class="mt-1">
                                            <small><i class="bi bi-funnel"></i> Filtres appliqués</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stats['etudiants_payants'] ?? 0; ?></h4>
                                        <small>Élèves Ayant Payé</small>
                                        <?php if (!empty($filtre_statut) || !empty($filtre_classe) || !empty($filtre_mois)): ?>
                                        <div class="mt-1">
                                            <small><i class="bi bi-funnel"></i> Filtres appliqués</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-people fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo number_format($stats['moyenne_paiement'] ?? 0, 0, ',', ' '); ?> Kwz</h4>
                                        <small>Moyenne par Paiement</small>
                                        <?php if (!empty($filtre_statut) || !empty($filtre_classe) || !empty($filtre_mois)): ?>
                                        <div class="mt-1">
                                            <small><i class="bi bi-funnel"></i> Filtres appliqués</small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-graph-up fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                

                <!-- Historique des paiements -->
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="bi bi-list-ul"></i> Historique des Paiements</h5>
                        <div>
                            <span class="badge bg-primary"><?php echo $total_items; ?> paiement(s) au total</span>
                            <span class="badge bg-secondary ms-2">Page <?php echo $page; ?> sur <?php echo $total_pages; ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($paiements) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped" id="tablePaiements">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Élève</th>
                                        <th>Classe</th>
                                        <th>Type de Frais</th>
                                        <th>Montant</th>
                                        <th>Mode</th>
                                        <th>Statut</th>
                                        <th>Caisse</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paiements as $paiement): 
                                        $solde = $paiement['montant_attendu'] - $paiement['montant_paye'];
                                    ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?></small>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($paiement['matricule']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($paiement['classe_nom'] ?? 'Non assigné'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($paiement['type_frais']); ?></td>
                                        <td>
                                            <span class="badge bg-success fs-6">
                                                <?php echo number_format($paiement['montant_paye'], 0, ',', ' '); ?> Kwz
                                            </span>
                                            <?php if ($solde > 0): ?>
                                            <br><small class="text-danger">Reste: <?php echo number_format($solde, 0, ',', ' '); ?> Kwz</small>
                                            <?php elseif ($solde < 0): ?>
                                            <br><small class="text-warning">Excédent: <?php echo number_format(abs($solde), 0, ',', ' '); ?> Kwz</small>
                                            <?php else: ?>
                                            <br><small class="text-success">Solde réglé</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($paiement['mode_paiement']); ?></span>
                                            <?php if (!empty($paiement['reference'])): ?>
                                            <br><small class="text-muted">Ref: <?php echo htmlspecialchars($paiement['reference']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $paiement['statut'] == 'payé' ? 'success' : 
                                                    ($paiement['statut'] == 'en attente' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo htmlspecialchars($paiement['statut']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($paiement['statut'] == 'payé' && $paiement['caisse_id']): ?>
                                            <span class="badge bg-success" data-bs-toggle="tooltip" title="Dépôt en caisse effectué">
                                                <i class="bi bi-check-circle"></i> Validé
                                            </span>
                                            <?php elseif ($paiement['statut'] == 'payé'): ?>
                                            <span class="badge bg-warning" data-bs-toggle="tooltip" title="En attente d'enregistrement en caisse">
                                                <i class="bi bi-clock"></i> En attente
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($paiement['statut'] != 'payé'): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['changer_statut' => $paiement['id'], 'statut' => 'payé'])); ?>" 
                                                class="btn btn-success" data-bs-toggle="tooltip" title="Marquer comme payé">
                                                    <i class="bi bi-check-circle"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if ($paiement['statut'] == 'payé'): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['changer_statut' => $paiement['id'], 'statut' => 'annulé'])); ?>" 
                                                class="btn btn-danger" data-bs-toggle="tooltip" title="Annuler le paiement">
                                                    <i class="bi bi-x-circle"></i>
                                                </a>
                                                <?php endif; ?>
                                                <button class="btn btn-info" data-bs-toggle="tooltip" title="Imprimer le reçu"
                                                        onclick="genererRecu(<?php echo $paiement['id']; ?>)">
                                                    <i class="bi bi-receipt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Pagination des paiements">
                            <ul class="pagination justify-content-center mt-4">
                                <!-- Premier et précédent -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                                        <i class="bi bi-chevron-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>

                                <!-- Pages -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <!-- Suivant et dernier -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                        <i class="bi bi-chevron-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                            
                            <!-- Informations de pagination -->
                            <div class="text-center text-muted mt-2">
                                <small>
                                    Affichage de <strong><?php echo (($page - 1) * $items_par_page) + 1; ?></strong> 
                                    à <strong><?php echo min($page * $items_par_page, $total_items); ?></strong> 
                                    sur <strong><?php echo $total_items; ?></strong> paiements
                                </small>
                            </div>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-credit-card display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">Aucun paiement enregistré</h4>
                            <p class="text-muted">Commencez par enregistrer le premier paiement.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterPaiementModal">
                                <i class="bi bi-plus-circle"></i> Enregistrer le premier paiement
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Ajouter Paiement -->
    <div class="modal fade" id="ajouterPaiementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-cash"></i> Enregistrer un paiement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="form-paiement">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="classe_id" class="form-label">Classe</label>
                                    <select class="form-control" id="classe_id" name="classe_id" required>
                                        <option value="">Sélectionner une classe</option>
                                        <?php foreach ($classes as $classe): ?>
                                            <option value="<?php echo $classe['id']; ?>">
                                                <?php echo $classe['nom'] . ' - ' . $classe['niveau']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="etudiant_id" class="form-label">Élève</label>
                                    <select class="form-control" id="etudiant_id" name="etudiant_id" required disabled>
                                        <option value="">Sélectionner d'abord une classe</option>
                                    </select>
                                    <div class="form-text">Veuillez d'abord sélectionner une classe</div>
                                </div>

                                <div class="mb-3">
                                    <label for="frais_id" class="form-label">Type de frais</label>
                                    <select class="form-control" id="frais_id" name="frais_id" required>
                                        <option value="">Sélectionner le type de frais</option>
                                        <?php foreach ($frais_list as $f): ?>
                                            <option value="<?php echo $f['id']; ?>" data-montant="<?php echo $f['montant']; ?>">
                                                <?php echo $f['type_frais'] . ' - ' . number_format($f['montant'], 2, ',', ' ') . ' Kwz'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="montant_paye" class="form-label">Montant payé</label>
                                    <input type="number" class="form-control" id="montant_paye" name="montant_paye" step="0.01" required>
                                    <div class="form-text">
                                        Montant attendu: <span id="montant-attendu">0</span> Kwz
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="date_paiement" class="form-label">Date de paiement</label>
                                    <input type="date" class="form-control" id="date_paiement" 
                                           name="date_paiement" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="mode_paiement" class="form-label">Mode de paiement</label>
                                    <select class="form-control" id="mode_paiement" name="mode_paiement" required>
                                        <option value="espèces">Espèces</option>
                                        <option value="chèque">Chèque</option>
                                        <option value="virement">Virement</option>
                                        <option value="carte">Carte bancaire</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="reference" class="form-label">Référence</label>
                                    <input type="text" class="form-control" id="reference" 
                                           name="reference" placeholder="Numéro de chèque, référence virement, etc.">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="enregistrer_paiement" class="btn btn-primary">
                            <i class="bi bi-save"></i> Enregistrer le paiement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const classeSelect = document.getElementById('classe_id');
            const etudiantSelect = document.getElementById('etudiant_id');
            const fraisSelect = document.getElementById('frais_id');
            const montantPayeInput = document.getElementById('montant_paye');
            const montantAttenduSpan = document.getElementById('montant-attendu');
            
            // Gestion du changement de classe
            classeSelect.addEventListener('change', function() {
                const classeId = this.value;
                
                if (classeId) {
                    // Activer le champ étudiant
                    etudiantSelect.disabled = false;
                    
                    // Charger les étudiants de cette classe via AJAX
                    chargerEtudiantsParClasse(classeId);
                } else {
                    // Désactiver et vider le champ étudiant
                    etudiantSelect.disabled = true;
                    etudiantSelect.innerHTML = '<option value="">Sélectionner d\'abord une classe</option>';
                }
            });
            
            // Gestion du changement de type de frais
            fraisSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const montantAttendu = selectedOption.getAttribute('data-montant') || 0;
                
                montantAttenduSpan.textContent = new Intl.NumberFormat('fr-FR', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(montantAttendu);
                
                // Pré-remplir le montant payé avec le montant attendu
                montantPayeInput.value = montantAttendu;
            });
            
            // Fonction pour charger les étudiants par classe
            function chargerEtudiantsParClasse(classeId) {
                // Afficher un indicateur de chargement
                etudiantSelect.innerHTML = '<option value="">Chargement...</option>';
                
                // Envoyer une requête AJAX pour récupérer les étudiants
                fetch(`api/etudiants-par-classe.php?classe_id=${classeId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erreur réseau');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Vider le select
                        etudiantSelect.innerHTML = '<option value="">Sélectionner un élève</option>';
                        
                        // Ajouter les options d'étudiants
                        data.forEach(etudiant => {
                            const option = document.createElement('option');
                            option.value = etudiant.id;
                            option.textContent = `${etudiant.matricule} - ${etudiant.nom} ${etudiant.prenom}`;
                            etudiantSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement des étudiants:', error);
                        etudiantSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                    });
            }
            
            // Initialiser le montant attendu
            const fraisOption = fraisSelect.options[fraisSelect.selectedIndex];
            if (fraisOption.value) {
                const montantInitial = fraisOption.getAttribute('data-montant') || 0;
                montantAttenduSpan.textContent = new Intl.NumberFormat('fr-FR', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(montantInitial);
            }

            // Activation des tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });

        function genererRecu(paiementId) {
            // Ouvrir dans une nouvelle fenêtre pour impression
            var url = 'generer_recu.php?id=' + paiementId + '&auto_print=1';
            var windowFeatures = 'width=800,height=900,scrollbars=yes,resizable=yes';
            window.open(url, '_blank', windowFeatures);
        }
    </script>
</body>
</html>