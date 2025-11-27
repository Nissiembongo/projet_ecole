<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = $error = '';

// Ajouter un type de frais
if ($_POST && isset($_POST['ajouter_frais'])) {
    try {
        $type_frais = $_POST['type_frais'];
        $montant = $_POST['montant'];
        $description = $_POST['description'];
        $annee_scolaire = $_POST['annee_scolaire'];
        
        // Vérifier si le type de frais existe déjà pour la même année
        $query_check = "SELECT id FROM frais WHERE type_frais = :type_frais AND annee_scolaire = :annee_scolaire";
        $stmt_check = $db->prepare($query_check);
        $stmt_check->bindParam(':type_frais', $type_frais);
        $stmt_check->bindParam(':annee_scolaire', $annee_scolaire);
        $stmt_check->execute();
        
        if ($stmt_check->rowCount() > 0) {
            $error = "Ce type de frais existe déjà pour l'année scolaire " . $annee_scolaire . "!";
        } else {
            $query = "INSERT INTO frais (type_frais, montant, description, annee_scolaire) 
                      VALUES (:type_frais, :montant, :description, :annee_scolaire)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':type_frais', $type_frais);
            $stmt->bindParam(':montant', $montant);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':annee_scolaire', $annee_scolaire);
            
            if ($stmt->execute()) {
                $success = "Type de frais ajouté avec succès!";
                $_POST = array();
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Modifier un type de frais
if ($_POST && isset($_POST['modifier_frais'])) {
    try {
        $id = $_POST['id'];
        $type_frais = $_POST['type_frais'];
        $montant = $_POST['montant'];
        $description = $_POST['description'];
        $annee_scolaire = $_POST['annee_scolaire'];
        
        $query = "UPDATE frais SET type_frais = :type_frais, montant = :montant, 
                  description = :description, annee_scolaire = :annee_scolaire 
                  WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':type_frais', $type_frais);
        $stmt->bindParam(':montant', $montant);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':annee_scolaire', $annee_scolaire);
        
        if ($stmt->execute()) {
            $success = "Type de frais modifié avec succès!";
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Supprimer un type de frais
if (isset($_GET['supprimer'])) {
    try {
        $id = $_GET['supprimer'];
        
        // Vérifier si ce frais est utilisé dans des paiements
        $query_check = "SELECT id FROM paiements WHERE frais_id = :id LIMIT 1";
        $stmt_check = $db->prepare($query_check);
        $stmt_check->bindParam(':id', $id);
        $stmt_check->execute();
        
        if ($stmt_check->rowCount() > 0) {
            $error = "Impossible de supprimer ce type de frais car il est utilisé dans des paiements!";
        } else {
            $query = "DELETE FROM frais WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $success = "Type de frais supprimé avec succès!";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer la liste des frais
$query = "SELECT * FROM frais ORDER BY annee_scolaire DESC, type_frais";
$stmt = $db->prepare($query);
$stmt->execute();
$frais = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer un frais pour modification
$frais_edit = null;
if (isset($_GET['modifier'])) {
    $query_edit = "SELECT * FROM frais WHERE id = :id";
    $stmt_edit = $db->prepare($query_edit);
    $stmt_edit->bindParam(':id', $_GET['modifier']);
    $stmt_edit->execute();
    $frais_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}
?>

<?php 
$page_title = "Gestion des Types de Frais";
include 'layout.php'; 
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i></a></li>
        <li class="breadcrumb-item active" aria-current="page">Types de Frais</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cash-coin me-2"></i>Gestion des Types de Frais</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterFraisModal">
        <i class="bi bi-plus-circle"></i> Nouveau Type de Frais
    </button>
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

<!-- Carte de statistiques -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-light">
            <div class="card-body py-3">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h4 class="text-primary mb-0"><?php echo count($frais); ?></h4>
                        <small class="text-muted">Total Types de Frais</small>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-success mb-0">
                            <?php 
                            $query_montant_total = "SELECT SUM(montant) as total FROM frais";
                            $stmt_montant_total = $db->prepare($query_montant_total);
                            $stmt_montant_total->execute();
                            $montant_total = $stmt_montant_total->fetch(PDO::FETCH_ASSOC)['total'];
                            echo number_format($montant_total, 0, ',', ' ') . ' Kwz';
                            ?>
                        </h4>
                        <small class="text-muted">Montant Total Configuré</small>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-warning mb-0">
                            <?php 
                            $query_annees = "SELECT COUNT(DISTINCT annee_scolaire) as total FROM frais";
                            $stmt_annees = $db->prepare($query_annees);
                            $stmt_annees->execute();
                            echo $stmt_annees->fetch(PDO::FETCH_ASSOC)['total'];
                            ?>
                        </h4>
                        <small class="text-muted">Années Scolaires</small>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-info mb-0">
                            <?php 
                            $query_actuelle = "SELECT COUNT(*) as total FROM frais WHERE annee_scolaire = '2024-2025'";
                            $stmt_actuelle = $db->prepare($query_actuelle);
                            $stmt_actuelle->execute();
                            echo $stmt_actuelle->fetch(PDO::FETCH_ASSOC)['total'];
                            ?>
                        </h4>
                        <small class="text-muted">Frais Actuels (2024-2025)</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des types de frais -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0"><i class="bi bi-list-ul"></i> Liste des Types de Frais</h5>
        <span class="badge bg-primary"><?php echo count($frais); ?> type(s) de frais</span>
    </div>
    <div class="card-body">
        <?php if (count($frais) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="tableFrais">
                <thead class="table-light">
                    <tr>
                        <th>Type de Frais</th>
                        <th>Montant</th>
                        <th>Description</th>
                        <th>Année Scolaire</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($frais as $fra): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($fra['type_frais']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-success fs-6">
                                <?php echo number_format($fra['montant'], 0, ',', ' '); ?> Kwz
                            </span>
                        </td>
                        <td>
                            <?php echo !empty($fra['description']) ? htmlspecialchars($fra['description']) : '<span class="text-muted">Aucune description</span>'; ?>
                        </td>
                        <td>
                            <span class="badge bg-info"><?php echo htmlspecialchars($fra['annee_scolaire']); ?></span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="?modifier=<?php echo $fra['id']; ?>" 
                                   class="btn btn-info" data-bs-toggle="tooltip" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="?supprimer=<?php echo $fra['id']; ?>" 
                                   class="btn btn-outline-danger" 
                                   data-bs-toggle="tooltip" 
                                   title="Supprimer"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce type de frais ?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-cash-coin display-1 text-muted"></i>
            <h4 class="text-muted mt-3">Aucun type de frais configuré</h4>
            <p class="text-muted">Commencez par ajouter le premier type de frais.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterFraisModal">
                <i class="bi bi-plus-circle"></i> Ajouter le premier type de frais
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter Frais -->
<div class="modal fade" id="ajouterFraisModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nouveau Type de Frais</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formFrais">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="type_frais" class="form-label">Type de Frais *</label>
                            <input type="text" class="form-control" id="type_frais" name="type_frais" 
                                   value="<?php echo $_POST['type_frais'] ?? ''; ?>" required
                                   placeholder="Ex: Frais de scolarité, Frais d'inscription...">
                        </div>
                        <div class="col-md-6">
                            <label for="montant" class="form-label">Montant (Kwz) *</label>
                            <input type="number" class="form-control" id="montant" name="montant" 
                                   value="<?php echo $_POST['montant'] ?? ''; ?>" 
                                   step="0.01" min="0" required
                                   placeholder="Ex: 150000.00">
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="3" placeholder="Description détaillée du frais..."><?php echo $_POST['description'] ?? ''; ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="annee_scolaire" class="form-label">Année Scolaire *</label>
                            <select class="form-select" id="annee_scolaire" name="annee_scolaire" required>
                                <option value="">Sélectionner une année</option>
                                <option value="2023-2024" <?php echo ($_POST['annee_scolaire'] ?? '') == '2023-2024' ? 'selected' : ''; ?>>2023-2024</option>
                                <option value="2024-2025" <?php echo ($_POST['annee_scolaire'] ?? '') == '2024-2025' ? 'selected' : ''; ?>>2024-2025</option>
                                <option value="2025-2026" <?php echo ($_POST['annee_scolaire'] ?? '') == '2025-2026' ? 'selected' : ''; ?>>2025-2026</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="ajouter_frais" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier Frais -->
<?php if ($frais_edit): ?>
<div class="modal fade show" id="modifierFraisModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Modifier Type de Frais</h5>
                <a href="frais.php" class="btn-close"></a>
            </div>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $frais_edit['id']; ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="type_frais_edit" class="form-label">Type de Frais *</label>
                            <input type="text" class="form-control" id="type_frais_edit" name="type_frais" 
                                   value="<?php echo htmlspecialchars($frais_edit['type_frais']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="montant_edit" class="form-label">Montant (Kwz) *</label>
                            <input type="number" class="form-control" id="montant_edit" name="montant" 
                                   value="<?php echo $frais_edit['montant']; ?>" 
                                   step="0.01" min="0" required>
                        </div>
                        <div class="col-12">
                            <label for="description_edit" class="form-label">Description</label>
                            <textarea class="form-control" id="description_edit" name="description" 
                                      rows="3"><?php echo htmlspecialchars($frais_edit['description']); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="annee_scolaire_edit" class="form-label">Année Scolaire *</label>
                            <select class="form-select" id="annee_scolaire_edit" name="annee_scolaire" required>
                                <option value="2023-2024" <?php echo $frais_edit['annee_scolaire'] == '2023-2024' ? 'selected' : ''; ?>>2023-2024</option>
                                <option value="2024-2025" <?php echo $frais_edit['annee_scolaire'] == '2024-2025' ? 'selected' : ''; ?>>2024-2025</option>
                                <option value="2025-2026" <?php echo $frais_edit['annee_scolaire'] == '2025-2026' ? 'selected' : ''; ?>>2025-2026</option>
                                <option value="autre" <?php echo !in_array($frais_edit['annee_scolaire'], ['2023-2024', '2024-2025', '2025-2026']) ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>
                        <?php if (!in_array($frais_edit['annee_scolaire'], ['2023-2024', '2024-2025', '2025-2026'])): ?>
                        <div class="col-md-6" id="autre_annee_container">
                            <label for="autre_annee" class="form-label">Autre année scolaire</label>
                            <input type="text" class="form-control" id="autre_annee" name="annee_scolaire_autre" 
                                   value="<?php echo htmlspecialchars($frais_edit['annee_scolaire']); ?>">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="frais.php" class="btn btn-secondary">Annuler</a>
                    <button type="submit" name="modifier_frais" class="btn btn-warning">
                        <i class="bi bi-save"></i> Modifier
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

// Gestion de la sélection "autre" pour l'année scolaire
document.getElementById('annee_scolaire')?.addEventListener('change', function() {
    if (this.value === 'autre') {
        const autreAnnee = prompt('Veuillez saisir l\'année scolaire (format: XXXX-XXXX):');
        if (autreAnnee && autreAnnee.match(/\d{4}-\d{4}/)) {
            // Créer une nouvelle option
            const nouvelleOption = new Option(autreAnnee, autreAnnee, true, true);
            this.add(nouvelleOption);
        } else {
            alert('Format d\'année scolaire invalide. Utilisez le format: XXXX-XXXX');
            this.value = '';
        }
    }
});

// Gestion similaire pour le modal de modification
document.getElementById('annee_scolaire_edit')?.addEventListener('change', function() {
    if (this.value === 'autre') {
        // Afficher le champ pour saisir une autre année
        let container = document.getElementById('autre_annee_container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'autre_annee_container';
            container.className = 'col-md-6';
            container.innerHTML = `
                <label for="autre_annee" class="form-label">Autre année scolaire</label>
                <input type="text" class="form-control" id="autre_annee" name="annee_scolaire_autre" 
                       placeholder="XXXX-XXXX" pattern="\\d{4}-\\d{4}" required>
            `;
            this.closest('.row').appendChild(container);
        }
    } else {
        // Cacher le champ autre année
        const container = document.getElementById('autre_annee_container');
        if (container) {
            container.remove();
        }
    }
});

// Formatage automatique du montant
document.getElementById('montant')?.addEventListener('blur', function() {
    if (this.value) {
        this.value = parseFloat(this.value).toFixed(2);
    }
});

document.getElementById('montant_edit')?.addEventListener('blur', function() {
    if (this.value) {
        this.value = parseFloat(this.value).toFixed(2);
    }
});

$(document).ready(function() {
    // Vérifier si DataTable est déjà initialisé
    if (!$.fn.DataTable.isDataTable('#tableFrais')) {
        $('#tableFrais').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            },
            pageLength: 10,
            responsive: true,
            order: [[3, 'desc']], // Tri par année scolaire décroissante
            columnDefs: [
                { orderable: false, targets: 4 } // Désactiver le tri sur la colonne actions
            ]
        });
    }
});

// Afficher automatiquement le modal de modification si nécessaire
<?php if ($frais_edit): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = new bootstrap.Modal(document.getElementById('modifierFraisModal'));
        modal.show();
    });
<?php endif; ?>
</script>