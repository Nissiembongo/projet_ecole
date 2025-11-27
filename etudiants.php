<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Récupérer la liste des classes pour les selects
$query_classes = "SELECT * FROM classe ORDER BY niveau, nom";
$stmt_classes = $db->prepare($query_classes);
$stmt_classes->execute();
$classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

$success = $error = '';

// Ajouter un étudiant
if ($_POST && isset($_POST['ajouter_etudiant'])) {
    try {
        $matricule = $_POST['matricule'];
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $classe_id = $_POST['classe_id'];
        $telephone = $_POST['telephone'];
        $email = $_POST['email'];
        
        // Vérifier si le matricule existe déjà
        $query_check_matricule = "SELECT id FROM etudiants WHERE matricule = :matricule";
        $stmt_check_matricule = $db->prepare($query_check_matricule);
        $stmt_check_matricule->bindParam(':matricule', $matricule);
        $stmt_check_matricule->execute();
        
        if ($stmt_check_matricule->rowCount() > 0) {
            $error = "Un étudiant avec ce matricule existe déjà!";
        } else {
            $query = "INSERT INTO etudiants (matricule, nom, prenom, classe_id, telephone, email, date_inscription) 
                      VALUES (:matricule, :nom, :prenom, :classe_id, :telephone, :email, CURDATE())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':matricule', $matricule);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':prenom', $prenom);
            $stmt->bindParam(':classe_id', $classe_id);
            $stmt->bindParam(':telephone', $telephone);
            $stmt->bindParam(':email', $email);
            
            if ($stmt->execute()) {
                $success = "Étudiant ajouté avec succès!";
                $_POST = array(); // Vider le formulaire
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Modifier un étudiant
if ($_POST && isset($_POST['modifier_etudiant'])) {
    try {
        $etudiant_id = $_POST['etudiant_id'];
        $matricule = $_POST['matricule'];
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $classe_id = $_POST['classe_id'];
        $telephone = $_POST['telephone'];
        $email = $_POST['email'];
        
        // Vérifier si le matricule existe déjà pour un autre étudiant
        $query_check_matricule = "SELECT id FROM etudiants WHERE matricule = :matricule AND id != :id";
        $stmt_check_matricule = $db->prepare($query_check_matricule);
        $stmt_check_matricule->bindParam(':matricule', $matricule);
        $stmt_check_matricule->bindParam(':id', $etudiant_id);
        $stmt_check_matricule->execute();
        
        if ($stmt_check_matricule->rowCount() > 0) {
            $error = "Un autre étudiant avec ce matricule existe déjà!";
        } else {
            $query = "UPDATE etudiants SET matricule = :matricule, nom = :nom, prenom = :prenom, 
                     classe_id = :classe_id, telephone = :telephone, email = :email 
                     WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':matricule', $matricule);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':prenom', $prenom);
            $stmt->bindParam(':classe_id', $classe_id);
            $stmt->bindParam(':telephone', $telephone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $etudiant_id);
            
            if ($stmt->execute()) {
                $success = "Étudiant modifié avec succès!";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Supprimer un étudiant
if (isset($_GET['supprimer_etudiant'])) {
    try {
        $etudiant_id = $_GET['supprimer_etudiant'];
        
        // Vérifier s'il y a des paiements associés à cet étudiant
        $query_check_paiements = "SELECT COUNT(*) as total FROM paiements WHERE etudiant_id = :etudiant_id";
        $stmt_check_paiements = $db->prepare($query_check_paiements);
        $stmt_check_paiements->bindParam(':etudiant_id', $etudiant_id);
        $stmt_check_paiements->execute();
        $has_paiements = $stmt_check_paiements->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        
        if ($has_paiements) {
            $error = "Impossible de supprimer cet étudiant car il a des paiements enregistrés. Vous devez d'abord supprimer ses paiements.";
        } else {
            $query = "DELETE FROM etudiants WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $etudiant_id);
            
            if ($stmt->execute()) {
                $success = "Étudiant supprimé avec succès!";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer le filtre classe
$filtre_classe = $_GET['classe'] ?? '';

// Construire la requête avec filtre
$where_condition = '';
$params = [];

if (!empty($filtre_classe) && $filtre_classe !== 'all') {
    $where_condition = "WHERE e.classe_id = :classe_id";
    $params[':classe_id'] = $filtre_classe;
}

// Récupérer la liste des étudiants avec les noms des classes
$query = "SELECT e.*, c.nom as classe_nom, c.niveau 
          FROM etudiants e 
          LEFT JOIN classe c ON e.classe_id = c.id 
          $where_condition
          ORDER BY e.nom, e.prenom";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le nom de la classe filtrée pour l'affichage
$classe_filtree_nom = '';
if (!empty($filtre_classe) && $filtre_classe !== 'all') {
    $query_classe_filtree = "SELECT nom FROM classe WHERE id = :classe_id";
    $stmt_classe_filtree = $db->prepare($query_classe_filtree);
    $stmt_classe_filtree->bindParam(':classe_id', $filtre_classe);
    $stmt_classe_filtree->execute();
    $classe_filtree = $stmt_classe_filtree->fetch(PDO::FETCH_ASSOC);
    $classe_filtree_nom = $classe_filtree['nom'] ?? '';
}

// Récupérer un étudiant spécifique pour modification
$etudiant_a_modifier = null;
if (isset($_GET['modifier_etudiant'])) {
    $etudiant_id = $_GET['modifier_etudiant'];
    $query_etudiant = "SELECT * FROM etudiants WHERE id = :id";
    $stmt_etudiant = $db->prepare($query_etudiant);
    $stmt_etudiant->bindParam(':id', $etudiant_id);
    $stmt_etudiant->execute();
    $etudiant_a_modifier = $stmt_etudiant->fetch(PDO::FETCH_ASSOC);
}
?>

<?php 
$page_title = "Gestion des Élèves";
include 'layout.php'; 
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i></a></li>
        <li class="breadcrumb-item active" aria-current="page">Élèves</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people me-2"></i>Gestion des Élèves</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterEtudiantModal">
        <i class="bi bi-person-plus"></i> Nouvel Élève
    </button>
</div>

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

<!-- Indicateur de filtre actif -->
<?php if (!empty($filtre_classe) && $filtre_classe !== 'all'): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="bi bi-funnel"></i> 
    <strong>Filtre actif :</strong> Affichage des élèves de la classe 
    <strong>"<?php echo $classe_filtree_nom; ?>"</strong>
    <a href="etudiants.php" class="btn btn-sm btn-outline-secondary ms-2">
        <i class="bi bi-x-circle"></i> Afficher tous les élèves
    </a>
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
                        <h4 class="text-primary mb-0"><?php echo count($etudiants); ?></h4>
                        <small class="text-muted">
                            <?php echo !empty($filtre_classe) && $filtre_classe !== 'all' ? 'Élèves dans la classe' : 'Total Élèves'; ?>
                        </small>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-success mb-0">
                            <?php echo count($classes); ?>
                        </h4>
                        <small class="text-muted">Classes</small>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-warning mb-0">
                            <?php 
                            $query_annee = "SELECT DISTINCT annee_scolaire FROM classe LIMIT 1";
                            $stmt_annee = $db->prepare($query_annee);
                            $stmt_annee->execute();
                            $annee = $stmt_annee->fetch(PDO::FETCH_ASSOC);
                            echo $annee ? $annee['annee_scolaire'] : date('Y') . '-' . (date('Y') + 1);
                            ?>
                        </h4>
                        <small class="text-muted">Année Scolaire</small>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-info mb-0">
                            <?php 
                            // Ajuster la requête pour le filtre
                            $query_aujourdhui = "SELECT COUNT(*) as total FROM etudiants e";
                            if (!empty($filtre_classe) && $filtre_classe !== 'all') {
                                $query_aujourdhui .= " WHERE e.classe_id = :classe_id AND";
                            } else {
                                $query_aujourdhui .= " WHERE";
                            }
                            $query_aujourdhui .= " DATE(e.date_inscription) = CURDATE()";
                            
                            $stmt_aujourdhui = $db->prepare($query_aujourdhui);
                            if (!empty($filtre_classe) && $filtre_classe !== 'all') {
                                $stmt_aujourdhui->bindParam(':classe_id', $filtre_classe);
                            }
                            $stmt_aujourdhui->execute();
                            echo $stmt_aujourdhui->fetch(PDO::FETCH_ASSOC)['total'];
                            ?>
                        </h4>
                        <small class="text-muted">
                            <?php echo !empty($filtre_classe) && $filtre_classe !== 'all' ? 'Inscrits aujourd\'hui (classe)' : 'Inscrits Aujourd\'hui'; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des étudiants -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-list-ul"></i> Liste des Élèves
            <?php if (!empty($filtre_classe) && $filtre_classe !== 'all'): ?>
            <span class="badge bg-info ms-2">Classe: <?php echo $classe_filtree_nom; ?></span>
            <?php endif; ?>
        </h5>
        <div class="btn-group">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                <i class="bi bi-filter"></i> 
                <?php echo !empty($filtre_classe) && $filtre_classe !== 'all' ? 'Filtré' : 'Filtrer par classe'; ?>
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item <?php echo empty($filtre_classe) || $filtre_classe === 'all' ? 'active' : ''; ?>" 
                       href="?classe=all">
                    <i class="bi bi-people"></i> Toutes les classes
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <?php foreach ($classes as $classe): ?>
                <li><a class="dropdown-item <?php echo $filtre_classe == $classe['id'] ? 'active' : ''; ?>" 
                       href="?classe=<?php echo $classe['id']; ?>">
                    <i class="bi bi-person"></i> <?php echo $classe['nom']; ?>
                </a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($etudiants) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Matricule</th>
                        <th>Nom et Prénom</th>
                        <th>Classe</th>
                        <th>Niveau</th>
                        <th>Contact</th>
                        <th>Date Inscription</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($etudiants as $etudiant): ?>
                    <tr>
                        <td>
                            <span class="badge bg-primary"><?php echo $etudiant['matricule']; ?></span>
                        </td>
                        <td>
                            <strong><?php echo $etudiant['nom'] . ' ' . $etudiant['prenom']; ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?php echo $etudiant['classe_nom'] ?? 'Non assigné'; ?></span>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo $etudiant['niveau'] ?? '-'; ?></small>
                        </td>
                        <td>
                            <div>
                                <?php if ($etudiant['telephone']): ?>
                                <small class="text-muted"><i class="bi bi-phone"></i> <?php echo $etudiant['telephone']; ?></small><br>
                                <?php endif; ?>
                                <?php if ($etudiant['email']): ?>
                                <small class="text-muted"><i class="bi bi-envelope"></i> <?php echo $etudiant['email']; ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <small><?php echo date('d/m/Y', strtotime($etudiant['date_inscription'])); ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="paiements.php?etudiant_id=<?php echo $etudiant['id']; ?>" 
                                   class="btn btn-success" data-bs-toggle="tooltip" title="Enregistrer paiement">
                                    <i class="bi bi-credit-card"></i>
                                </a>
                                <button class="btn btn-info" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modifierEtudiantModal"
                                        onclick="chargerDonneesEtudiant(<?php echo htmlspecialchars(json_encode($etudiant)); ?>)"
                                        data-bs-toggle="tooltip" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="?supprimer_etudiant=<?php echo $etudiant['id']; ?>" 
                                   class="btn btn-outline-danger" 
                                   data-bs-toggle="tooltip" 
                                   title="Supprimer"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer l\\'élève <?php echo htmlspecialchars(addslashes($etudiant['nom'] . ' ' . $etudiant['prenom'])); ?> ? Cette action est irréversible.')">
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
            <i class="bi bi-people display-1 text-muted"></i>
            <h4 class="text-muted mt-3">
                <?php if (!empty($filtre_classe) && $filtre_classe !== 'all'): ?>
                Aucun élève dans cette classe
                <?php else: ?>
                Aucun élève enregistré
                <?php endif; ?>
            </h4>
            <p class="text-muted">
                <?php if (!empty($filtre_classe) && $filtre_classe !== 'all'): ?>
                Aucun élève n'est actuellement inscrit dans la classe "<?php echo $classe_filtree_nom; ?>"
                <?php else: ?>
                Commencez par ajouter le premier élève.
                <?php endif; ?>
            </p>
            <?php if (empty($filtre_classe) || $filtre_classe === 'all'): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterEtudiantModal">
                <i class="bi bi-plus-circle"></i> Ajouter le premier élève
            </button>
            <?php else: ?>
            <a href="etudiants.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Voir tous les élèves
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter Étudiant -->
<div class="modal fade" id="ajouterEtudiantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Nouvel Élève</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="matricule" class="form-label">Matricule *</label>
                            <input type="text" class="form-control" id="matricule" name="matricule" required 
                                   placeholder="Ex: MAT2024001">
                        </div>
                        <div class="col-md-6">
                            <label for="classe_id" class="form-label">Classe *</label>
                            <select class="form-control" id="classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>" 
                                    <?php echo (!empty($filtre_classe) && $filtre_classe !== 'all' && $filtre_classe == $classe['id']) ? 'selected' : ''; ?>>
                                    <?php echo $classe['nom'] . ' - ' . $classe['niveau']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required 
                                   placeholder="Nom de famille">
                        </div>
                        <div class="col-md-6">
                            <label for="prenom" class="form-label">Prénom *</label>
                            <input type="text" class="form-control" id="prenom" name="prenom" required 
                                   placeholder="Prénom">
                        </div>
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="telephone" name="telephone" 
                                   placeholder="+243 XX XXX XX XX">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="email@exemple.com">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="ajouter_etudiant" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier Étudiant -->
<div class="modal fade" id="modifierEtudiantModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Modifier l'Élève</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="modifier_etudiant_id" name="etudiant_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modifier_matricule" class="form-label">Matricule *</label>
                            <input type="text" class="form-control" id="modifier_matricule" name="matricule" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modifier_classe_id" class="form-label">Classe *</label>
                            <select class="form-control" id="modifier_classe_id" name="classe_id" required>
                                <option value="">Sélectionner une classe</option>
                                <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>">
                                    <?php echo $classe['nom'] . ' - ' . $classe['niveau']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modifier_nom" class="form-label">Nom *</label>
                            <input type="text" class="form-control" id="modifier_nom" name="nom" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modifier_prenom" class="form-label">Prénom *</label>
                            <input type="text" class="form-control" id="modifier_prenom" name="prenom" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modifier_telephone" class="form-label">Téléphone</label>
                            <input type="text" class="form-control" id="modifier_telephone" name="telephone">
                        </div>
                        <div class="col-md-6">
                            <label for="modifier_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="modifier_email" name="email">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="modifier_etudiant" class="btn btn-warning">
                        <i class="bi bi-save"></i> Modifier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout-end.php'; ?>

<script>
// Activation des tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Génération automatique du matricule
document.addEventListener('DOMContentLoaded', function() {
    const matriculeInput = document.getElementById('matricule');
    
    // Générer un matricule si le champ est vide
    if (!matriculeInput.value) {
        const now = new Date();
        const year = now.getFullYear();
        const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
        matriculeInput.value = `MAT${year}${random}`;
    }
});

// Fonction pour charger les données dans le modal de modification
function chargerDonneesEtudiant(etudiant) {
    document.getElementById('modifier_etudiant_id').value = etudiant.id;
    document.getElementById('modifier_matricule').value = etudiant.matricule;
    document.getElementById('modifier_nom').value = etudiant.nom;
    document.getElementById('modifier_prenom').value = etudiant.prenom;
    document.getElementById('modifier_classe_id').value = etudiant.classe_id;
    document.getElementById('modifier_telephone').value = etudiant.telephone || '';
    document.getElementById('modifier_email').value = etudiant.email || '';
}
</script>