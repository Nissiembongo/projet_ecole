<?php
include 'config.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = $error = '';

// Fonctions utilitaires
function calculerSoldeTotal($db) {
    // Calculer la somme nette de TOUTES les opérations validées
    $query = "SELECT COALESCE(SUM(montant), 0) as solde_total 
              FROM caisse 
              WHERE statut = 'validé'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['solde_total'] ?? 0;
}

function calculerSoldeDuJour($db, $date_solde = null) {
    if (!$date_solde) {
        $date_solde = date('Y-m-d');
    }
    
    // Récupérer le solde d'ouverture du jour
    $query_solde = "SELECT solde_ouverture FROM solde_caisse WHERE date_solde = :date_solde";
    $stmt_solde = $db->prepare($query_solde);
    $stmt_solde->bindParam(':date_solde', $date_solde);
    $stmt_solde->execute();
    $solde_ouverture = $stmt_solde->fetch(PDO::FETCH_ASSOC);
    $solde_ouverture = $solde_ouverture ? $solde_ouverture['solde_ouverture'] : 0;
    
    // Calculer la somme nette des opérations du jour
    $query_operations = "SELECT COALESCE(SUM(montant), 0) as somme_nette 
                         FROM caisse 
                         WHERE DATE(date_operation) = :date_solde 
                         AND statut = 'validé'";
    $stmt_operations = $db->prepare($query_operations);
    $stmt_operations->bindParam(':date_solde', $date_solde);
    $stmt_operations->execute();
    $somme_nette = $stmt_operations->fetch(PDO::FETCH_ASSOC)['somme_nette'];
    
    return $solde_ouverture + $somme_nette;
}

// Fonction de compatibilité (à supprimer progressivement)
function calculerSoldeActuel($db) {
    return calculerSoldeTotal($db);
}

function getOperationsDuJour($db) {
    $query = "SELECT c.*, u.nom_complet as caissier 
              FROM caisse c 
              LEFT JOIN utilisateurs u ON c.utilisateur_id = u.id 
              WHERE DATE(c.date_operation) = CURDATE() 
              ORDER BY c.date_operation DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatistiquesJour($db, $date_solde = null) {
    if (!$date_solde) {
        $date_solde = date('Y-m-d');
    }
    
    $query = "SELECT 
        SUM(CASE WHEN type_operation = 'dépôt' THEN montant ELSE 0 END) as total_depots,
        SUM(CASE WHEN type_operation = 'retrait' THEN ABS(montant) ELSE 0 END) as total_retraits,
        COUNT(*) as total_operations,
        COUNT(DISTINCT mode_operation) as modes_paiement
    FROM caisse 
    WHERE DATE(date_operation) = :date_solde AND statut = 'validé'";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':date_solde', $date_solde);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCategories($db) {
    $query = "SELECT * FROM categories_caisse WHERE statut = 'actif' ORDER BY type, nom";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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

function initialiserSoldeJour($db) {
    $date_aujourdhui = date('Y-m-d');
    
    // Vérifier si le solde du jour existe déjà
    $query_check = "SELECT id FROM solde_caisse WHERE date_solde = :date_solde";
    $stmt_check = $db->prepare($query_check);
    $stmt_check->bindParam(':date_solde', $date_aujourdhui);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() == 0) {
        // Calculer le solde de fermeture de la veille
        $date_veille = date('Y-m-d', strtotime('-1 day'));
        $query_solde_veille = "SELECT solde_fermeture FROM solde_caisse WHERE date_solde = :date_veille AND statut = 'fermé'";
        $stmt_solde_veille = $db->prepare($query_solde_veille);
        $stmt_solde_veille->bindParam(':date_veille', $date_veille);
        $stmt_solde_veille->execute();
        $solde_veille = $stmt_solde_veille->fetch(PDO::FETCH_ASSOC);
        
        $solde_ouverture = $solde_veille ? $solde_veille['solde_fermeture'] : 0;
        
        $query_insert = "INSERT INTO solde_caisse (date_solde, solde_ouverture, utilisateur_id) VALUES (:date_solde, :solde_ouverture, :utilisateur_id)";
        $stmt_insert = $db->prepare($query_insert);
        $stmt_insert->bindParam(':date_solde', $date_aujourdhui);
        $stmt_insert->bindParam(':solde_ouverture', $solde_ouverture);
        $stmt_insert->bindParam(':utilisateur_id', $_SESSION['user_id']);
        $stmt_insert->execute();
    }
}

// Initialiser le solde du jour
initialiserSoldeJour($db);

// Récupérer le solde du jour
$query_solde_jour = "SELECT * FROM solde_caisse WHERE date_solde = CURDATE()";
$stmt_solde_jour = $db->prepare($query_solde_jour);
$stmt_solde_jour->execute();
$solde_jour = $stmt_solde_jour->fetch(PDO::FETCH_ASSOC);

// Opération de caisse
if ($_POST && isset($_POST['operation_caisse'])) {
    try {
        $type_operation = $_POST['type_operation'];
        $montant = $_POST['montant'];
        $date_operation = $_POST['date_operation'];
        $mode_operation = $_POST['mode_operation'];
        $description = $_POST['description'];
        $reference = $_POST['reference'];
        // $categorie = $_POST['categorie'];
        
        // Vérifier le solde pour les retraits
        if ($type_operation == 'retrait') {
            $solde_total = calculerSoldeTotal($db);
            if ($montant > $solde_total) {
                $error = "Solde insuffisant! Solde disponible: " . number_format($solde_total, 0, ',', ' ') . " Kwz";
            }
        }
        
        if (empty($error)) {
            $query = "INSERT INTO caisse (type_operation, montant, date_operation, mode_operation, description, reference, utilisateur_id) 
                      VALUES (:type_operation, :montant, :date_operation, :mode_operation, :description, :reference, :utilisateur_id)";
            $stmt = $db->prepare($query);
            $montant_final = $type_operation == 'retrait' ? -$montant : $montant;
            $stmt->bindParam(':type_operation', $type_operation);
            $stmt->bindParam(':montant', $montant_final);
            $stmt->bindParam(':date_operation', $date_operation);
            $stmt->bindParam(':mode_operation', $mode_operation);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':reference', $reference);
            $stmt->bindParam(':utilisateur_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                // Mettre à jour les statistiques du solde du jour
                mettreAJourSoldeJour($db);
                $success = "Opération de caisse enregistrée avec succès!";
                $_POST = array();
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Fermeture de caisse
if ($_POST && isset($_POST['fermer_caisse'])) {
    try {
        $solde_fermeture = $_POST['solde_fermeture'];
        $notes = $_POST['notes'];
        
        $query = "UPDATE solde_caisse SET solde_fermeture = :solde_fermeture, notes = :notes, statut = 'fermé' WHERE date_solde = CURDATE()";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':solde_fermeture', $solde_fermeture);
        $stmt->bindParam(':notes', $notes);
        
        if ($stmt->execute()) {
            $success = "Caisse fermée avec succès!";
            // Recharger les données
            $stmt_solde_jour->execute();
            $solde_jour = $stmt_solde_jour->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer les données
$solde_total = calculerSoldeTotal($db);
$solde_du_jour = calculerSoldeDuJour($db);
$operations_du_jour = getOperationsDuJour($db);
$statistiques_jour = getStatistiquesJour($db);
$categories = getCategories($db);

// Pour la compatibilité
$solde_actuel = $solde_total;

// Récupérer les paiements automatiques du jour
$query_paiements_auto = "SELECT p.*, e.nom, e.prenom, e.matricule, f.type_frais 
                        FROM paiements p 
                        JOIN etudiants e ON p.etudiant_id = e.id 
                        JOIN frais f ON p.frais_id = f.id 
                        WHERE DATE(p.date_paiement) = CURDATE() AND p.statut = 'payé' 
                        ORDER BY p.date_paiement DESC";
$stmt_paiements_auto = $db->prepare($query_paiements_auto);
$stmt_paiements_auto->execute();
$paiements_auto = $stmt_paiements_auto->fetchAll(PDO::FETCH_ASSOC);
?>

<?php 
$page_title = "Gestion de la Caisse";
include 'layout.php'; 
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i></a></li>
        <li class="breadcrumb-item active" aria-current="page">Caisse</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cash-stack me-2"></i>Gestion de la Caisse</h2>
    <div class="btn-group">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#depotModal">
            <i class="bi bi-plus-circle"></i> Dépôt
        </button>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#retraitModal">
            <i class="bi bi-dash-circle"></i> Retrait
        </button>
        <?php if ($solde_jour && $solde_jour['statut'] == 'ouvert'): ?>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#fermerCaisseModal">
            <i class="bi bi-lock"></i> Fermer Caisse
        </button>
        <?php else: ?>
        <span class="btn btn-secondary disabled">
            <i class="bi bi-lock-fill"></i> Caisse Fermée
        </span>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($success) && !empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error) && !empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Bannière d'état de la caisse -->
<div class="card mb-4 <?php echo ($solde_jour && $solde_jour['statut'] == 'ouvert') ? 'border-success' : 'border-secondary'; ?>">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-3 text-center">
                <div class="display-4 fw-bold text-<?php echo ($solde_jour && $solde_jour['statut'] == 'ouvert') ? 'success' : 'secondary'; ?>">
                    <?php echo number_format($solde_total, 0, ',', ' '); ?> Kwz
                </div>
                <small class="text-muted">Solde total de la caisse</small>
            </div>
            <div class="col-md-3 text-center">
                <div class="h4 fw-bold text-info">
                    <?php echo number_format($solde_du_jour, 0, ',', ' '); ?> Kwz
                </div>
                <small class="text-muted">Solde du jour</small>
            </div>
            <div class="col-md-6">
                <div class="row text-center">
                    <div class="col-4">
                        <h5 class="text-success"><?php echo number_format($statistiques_jour['total_depots'] ?? 0, 0, ',', ' '); ?> Kwz</h5>
                        <small class="text-muted">Dépôts aujourd'hui</small>
                    </div>
                    <div class="col-4">
                        <h5 class="text-warning"><?php echo number_format($statistiques_jour['total_retraits'] ?? 0, 0, ',', ' '); ?> Kwz</h5>
                        <small class="text-muted">Retraits aujourd'hui</small>
                    </div>
                    <div class="col-4">
                        <h5 class="text-primary"><?php echo $statistiques_jour['total_operations'] ?? 0; ?></h5>
                        <small class="text-muted">Opérations</small>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-<?php echo ($solde_jour && $solde_jour['statut'] == 'ouvert') ? 'success' : 'secondary'; ?>">
                        <i class="bi bi-<?php echo ($solde_jour && $solde_jour['statut'] == 'ouvert') ? 'unlock' : 'lock'; ?>"></i>
                        Caisse <?php echo ($solde_jour && $solde_jour['statut'] == 'ouvert') ? 'Ouverte' : 'Fermée'; ?>
                    </span>
                    <small class="text-muted ms-2">
                        <?php if ($solde_jour): ?>
                        Solde d'ouverture: <?php echo number_format($solde_jour['solde_ouverture'], 0, ',', ' '); ?> Kwz
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cartes de statistiques détaillées -->
<div class="row g-4 mb-4">
    <div class="col-xl-2 col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="bi bi-cash-coin display-6"></i>
                <h5 class="mt-2">Solde Total</h5>
                <h4><?php echo number_format($solde_total, 0, ',', ' '); ?> Kwz</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <i class="bi bi-cash display-6"></i>
                <h5 class="mt-2">Solde Jour</h5>
                <h4><?php echo number_format($solde_du_jour, 0, ',', ' '); ?> Kwz</h4>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="bi bi-arrow-down-circle display-6"></i>
                <h5 class="mt-2">Dépôts</h5>
                <h4><?php echo number_format($statistiques_jour['total_depots'] ?? 0, 0, ',', ' '); ?> Kwz</h4>
                <small>Aujourd'hui</small>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <i class="bi bi-arrow-up-circle display-6"></i>
                <h5 class="mt-2">Retraits</h5>
                <h4><?php echo number_format($statistiques_jour['total_retraits'] ?? 0, 0, ',', ' '); ?> Kwz</h4>
                <small>Aujourd'hui</small>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <i class="bi bi-credit-card display-6"></i>
                <h5 class="mt-2">Modes</h5>
                <h4><?php echo $statistiques_jour['modes_paiement'] ?? 0; ?></h4>
                <small>Aujourd'hui</small>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4">
        <div class="card bg-dark text-white">
            <div class="card-body text-center">
                <i class="bi bi-calendar-day display-6"></i>
                <h5 class="mt-2">Date</h5>
                <h6><?php echo date('d/m/Y'); ?></h6>
            </div>
        </div>
    </div>
</div>

<!-- Paiements automatiques du jour -->
<?php if (count($paiements_auto) > 0): ?>
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="card-title mb-0">
            <i class="bi bi-credit-card"></i> Paiements Automatiques Aujourd'hui
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Étudiant</th>
                        <th>Type de Frais</th>
                        <th>Montant</th>
                        <th>Date</th>
                        <th>Mode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paiements_auto as $paiement): ?>
                    <tr>
                        <td>
                            <small><?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($paiement['type_frais']); ?></td>
                        <td class="text-success fw-bold">
                            <?php echo number_format($paiement['montant_paye'], 0, ',', ' '); ?> Kwz
                        </td>
                        <td>
                            <small><?php echo date('H:i', strtotime($paiement['date_paiement'])); ?></small>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?php echo $paiement['mode_paiement']; ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Opérations du jour -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-clock-history"></i> Opérations du Jour (<?php echo date('d/m/Y'); ?>)
        </h5>
        <span class="badge bg-primary"><?php echo count($operations_du_jour); ?> opération(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (count($operations_du_jour) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Heure</th>
                        <th>Type</th>
                        <th>Montant</th>
                        <th>Mode</th>
                        <th>Catégorie</th>
                        <th>Description</th>
                        <th>Référence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operations_du_jour as $operation): ?>
                    <tr>
                        <td><small><?php echo date('H:i', strtotime($operation['date_operation'])); ?></small></td>
                        <td>
                            <span class="badge bg-<?php echo $operation['type_operation'] == 'dépôt' ? 'success' : 'warning'; ?>">
                                <?php echo $operation['type_operation'] == 'dépôt' ? 'Dépôt' : 'Retrait'; ?>
                            </span>
                        </td>
                        <td>
                            <span class="fw-bold text-<?php echo $operation['type_operation'] == 'dépôt' ? 'success' : 'warning'; ?>">
                                <?php echo number_format(abs($operation['montant']), 0, ',', ' '); ?> Kwz
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo $operation['mode_operation']; ?></span>
                        </td>
                        <td>
                            <?php 
                            $categorie_info = array_filter($categories, function($cat) use ($operation) {
                                return $cat['nom'] == $operation['categorie'];
                            });
                            $categorie_info = reset($categorie_info);
                            $couleur = $categorie_info['couleur'] ?? '#6c757d';
                            ?>
                            <span class="badge" style="background-color: <?php echo $couleur; ?>">
                                <?php echo $operation['categorie']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($operation['description']); ?></td>
                        <td>
                            <?php if (!empty($operation['reference'])): ?>
                            <small class="text-muted"><?php echo htmlspecialchars($operation['reference']); ?></small>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-cash-stack display-1 text-muted"></i>
            <h4 class="text-muted mt-3">Aucune opération aujourd'hui</h4>
            <p class="text-muted">Commencez par effectuer votre première opération de caisse.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Dépôt -->
<div class="modal fade" id="depotModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nouveau Dépôt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="type_operation" value="dépôt">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="montant_depot" class="form-label">Montant (Kwz) *</label>
                            <input type="number" class="form-control" id="montant_depot" name="montant" 
                                   step="0.01" min="0.01" required placeholder="0.00">
                        </div>
                        <div class="col-12">
                            <label for="date_operation_depot" class="form-label">Date et heure *</label>
                            <input type="datetime-local" class="form-control" id="date_operation_depot" name="date_operation" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label for="mode_operation_depot" class="form-label">Mode de paiement *</label>
                            <select class="form-select" id="mode_operation_depot" name="mode_operation" required>
                                <option value="espèces">Espèces</option>
                                <option value="chèque">Chèque</option>
                                <option value="virement">Virement</option>
                                <option value="carte">Carte bancaire</option>
                                <option value="mobile">Paiement mobile</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label for="description_depot" class="form-label">Description *</label>
                            <textarea class="form-control" id="description_depot" name="description" 
                                      rows="3" required placeholder="Description du dépôt..."></textarea>
                        </div>
                        <div class="col-12">
                            <label for="reference_depot" class="form-label">Référence</label>
                            <input type="text" class="form-control" id="reference_depot" name="reference" 
                                   placeholder="Numéro de chèque, référence virement, etc.">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="operation_caisse" class="btn btn-success">
                        <i class="bi bi-save"></i> Enregistrer le dépôt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Retrait -->
<div class="modal fade" id="retraitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-dash-circle"></i> Nouveau Retrait</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="type_operation" value="retrait">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="montant_retrait" class="form-label">Montant (Kwz) *</label>
                            <input type="number" class="form-control" id="montant_retrait" name="montant" 
                                   step="0.01" min="0.01" required placeholder="0.00">
                            <div class="form-text">Solde disponible: <span id="solde_disponible" class="fw-bold text-success">
                                <?php echo number_format($solde_total, 0, ',', ' '); ?> Kwz
                            </span></div>
                        </div>
                        <div class="col-12">
                            <label for="date_operation_retrait" class="form-label">Date et heure *</label>
                            <input type="datetime-local" class="form-control" id="date_operation_retrait" name="date_operation" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                        <div class="col-12">
                            <label for="mode_operation_retrait" class="form-label">Mode de paiement *</label>
                            <select class="form-select" id="mode_operation_retrait" name="mode_operation" required>
                                <option value="espèces">Espèces</option>
                                <option value="chèque">Chèque</option>
                                <option value="virement">Virement</option>
                                <option value="carte">Carte bancaire</option>
                                <option value="mobile">Paiement mobile</option>
                            </select>
                        </div> 
                        <div class="col-12">
                            <label for="description_retrait" class="form-label">Description *</label>
                            <textarea class="form-control" id="description_retrait" name="description" 
                                      rows="3" required placeholder="Motif du retrait..."></textarea>
                        </div>
                        <div class="col-12">
                            <label for="reference_retrait" class="form-label">Référence</label>
                            <input type="text" class="form-control" id="reference_retrait" name="reference" 
                                   placeholder="Numéro de chèque, référence virement, etc.">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="operation_caisse" class="btn btn-warning">
                        <i class="bi bi-save"></i> Enregistrer le retrait
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Fermer Caisse -->
<?php if ($solde_jour && $solde_jour['statut'] == 'ouvert'): ?>
<div class="modal fade" id="fermerCaisseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-lock"></i> Fermer la Caisse</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Vérifiez le solde physique avant de fermer la caisse.
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="solde_physique" class="form-label">Solde physique constaté (Kwz) *</label>
                            <input type="number" class="form-control" id="solde_physique" 
                                   step="0.01" placeholder="Saisir le solde physique" 
                                   onchange="calculerEcart()">
                        </div>
                        <div class="col-12">
                            <label for="solde_fermeture" class="form-label">Solde de fermeture (Kwz) *</label>
                            <input type="number" class="form-control" id="solde_fermeture" name="solde_fermeture" 
                                   value="<?php echo $solde_total; ?>" step="0.01" required readonly>
                        </div>
                        <div class="col-12">
                            <div id="ecart_container" class="alert d-none">
                                <strong>Écart constaté: <span id="ecart_montant">0</span> Kwz</strong>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" 
                                      placeholder="Observations, écarts constatés..."></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-3 p-3 bg-light rounded">
                        <h6>Récapitulatif de la journée:</h6>
                        <ul class="list-unstyled">
                            <li>Solde d'ouverture: <strong><?php echo number_format($solde_jour['solde_ouverture'], 0, ',', ' '); ?> Kwz</strong></li>
                            <li>Total des dépôts: <strong class="text-success"><?php echo number_format($statistiques_jour['total_depots'] ?? 0, 0, ',', ' '); ?> Kwz</strong></li>
                            <li>Total des retraits: <strong class="text-warning"><?php echo number_format($statistiques_jour['total_retraits'] ?? 0, 0, ',', ' '); ?> Kwz</strong></li>
                            <li>Nombre d'opérations: <strong><?php echo $statistiques_jour['total_operations'] ?? 0; ?></strong></li>
                            <li>Solde théorique: <strong class="text-primary"><?php echo number_format($solde_total, 0, ',', ' '); ?> Kwz</strong></li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="fermer_caisse" class="btn btn-danger">
                        <i class="bi bi-lock-fill"></i> Confirmer la fermeture
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'layout-end.php'; ?>

<script>
// Activation des tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Validation du montant de retrait
document.getElementById('montant_retrait')?.addEventListener('blur', function() {
    var montant = parseFloat(this.value);
    var soldeDisponible = parseFloat(<?php echo $solde_total; ?>);
    
    if (montant > soldeDisponible) {
        alert('Montant de retrait supérieur au solde disponible!');
        this.value = soldeDisponible.toFixed(2);
    }
});

// Formatage automatique des montants
document.getElementById('montant_depot')?.addEventListener('blur', function() {
    if (this.value) {
        this.value = parseFloat(this.value).toFixed(2);
    }
});

document.getElementById('montant_retrait')?.addEventListener('blur', function() {
    if (this.value) {
        this.value = parseFloat(this.value).toFixed(2);
    }
});

// Calcul de l'écart pour la fermeture de caisse
function calculerEcart() {
    var soldePhysique = parseFloat(document.getElementById('solde_physique').value) || 0;
    var soldeTheorique = parseFloat(<?php echo $solde_total; ?>);
    var ecart = soldePhysique - soldeTheorique;
    
    var ecartContainer = document.getElementById('ecart_container');
    var ecartMontant = document.getElementById('ecart_montant');
    
    if (soldePhysique > 0) {
        ecartContainer.classList.remove('d-none');
        ecartMontant.textContent = new Intl.NumberFormat('fr-FR').format(ecart.toFixed(2));
        
        if (ecart > 0) {
            ecartContainer.className = 'alert alert-success';
            ecartMontant.innerHTML = '<i class="bi bi-plus-circle"></i> ' + Math.abs(ecart).toFixed(2);
        } else if (ecart < 0) {
            ecartContainer.className = 'alert alert-danger';
            ecartMontant.innerHTML = '<i class="bi bi-dash-circle"></i> ' + Math.abs(ecart).toFixed(2);
        } else {
            ecartContainer.className = 'alert alert-info';
            ecartMontant.innerHTML = '<i class="bi bi-check-circle"></i> ' + Math.abs(ecart).toFixed(2);
        }
    } else {
        ecartContainer.classList.add('d-none');
    }
}

// Rafraîchissement automatique du solde toutes les 30 secondes
setInterval(function() {
    // Ici vous pourriez implémenter un appel AJAX pour rafraîchir le solde
    // Pour l'instant, on recharge la page
    // window.location.reload();
}, 30000);
</script>