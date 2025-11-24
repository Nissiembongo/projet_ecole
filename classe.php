<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = $error = '';

// Ajouter une classe
if ($_POST && isset($_POST['ajouter_classe'])) {
    try {
        $nom = $_POST['nom'];
        $niveau = $_POST['niveau'];
        $filiere = $_POST['filiere'];
        $capacite_max = $_POST['capacite_max'];
        $annee_scolaire = $_POST['annee_scolaire'];
        
        // Vérifier si la classe existe déjà
        $query_check = "SELECT id FROM classe WHERE nom = :nom AND annee_scolaire = :annee_scolaire";
        $stmt_check = $db->prepare($query_check);
        $stmt_check->bindParam(':nom', $nom);
        $stmt_check->bindParam(':annee_scolaire', $annee_scolaire);
        $stmt_check->execute();
        
        if ($stmt_check->rowCount() > 0) {
            $error = "Une classe avec ce nom existe déjà pour l'année scolaire sélectionnée!";
        } else {
            $query = "INSERT INTO classe (nom, niveau, filiere, capacite_max, annee_scolaire) 
                      VALUES (:nom, :niveau, :filiere, :capacite_max, :annee_scolaire)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':niveau', $niveau);
            $stmt->bindParam(':filiere', $filiere);
            $stmt->bindParam(':capacite_max', $capacite_max);
            $stmt->bindParam(':annee_scolaire', $annee_scolaire);
            
            if ($stmt->execute()) {
                $success = "Classe ajoutée avec succès!";
                $_POST = array(); // Vider le formulaire
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Modifier une classe
if ($_POST && isset($_POST['modifier_classe'])) {
    try {
        $classe_id = $_POST['classe_id'];
        $nom = $_POST['nom'];
        $niveau = $_POST['niveau'];
        $filiere = $_POST['filiere'];
        $capacite_max = $_POST['capacite_max'];
        $annee_scolaire = $_POST['annee_scolaire'];
        
        // Vérifier si une autre classe a le même nom pour la même année
        $query_check = "SELECT id FROM classe WHERE nom = :nom AND annee_scolaire = :annee_scolaire AND id != :id";
        $stmt_check = $db->prepare($query_check);
        $stmt_check->bindParam(':nom', $nom);
        $stmt_check->bindParam(':annee_scolaire', $annee_scolaire);
        $stmt_check->bindParam(':id', $classe_id);
        $stmt_check->execute();
        
        if ($stmt_check->rowCount() > 0) {
            $error = "Une autre classe avec ce nom existe déjà pour l'année scolaire sélectionnée!";
        } else {
            $query = "UPDATE classe SET nom = :nom, niveau = :niveau, filiere = :filiere, 
                      capacite_max = :capacite_max, annee_scolaire = :annee_scolaire 
                      WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':niveau', $niveau);
            $stmt->bindParam(':filiere', $filiere);
            $stmt->bindParam(':capacite_max', $capacite_max);
            $stmt->bindParam(':annee_scolaire', $annee_scolaire);
            $stmt->bindParam(':id', $classe_id);
            
            if ($stmt->execute()) {
                $success = "Classe modifiée avec succès!";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Supprimer une classe
if (isset($_GET['supprimer_classe'])) {
    try {
        $classe_id = $_GET['supprimer_classe'];
        
        // Vérifier s'il y a des étudiants dans cette classe
        $query_check_etudiants = "SELECT COUNT(*) as total FROM etudiants WHERE classe_id = :classe_id";
        $stmt_check_etudiants = $db->prepare($query_check_etudiants);
        $stmt_check_etudiants->bindParam(':classe_id', $classe_id);
        $stmt_check_etudiants->execute();
        $has_etudiants = $stmt_check_etudiants->fetch(PDO::FETCH_ASSOC)['total'] > 0;
        
        if ($has_etudiants) {
            $error = "Impossible de supprimer cette classe car elle contient des étudiants. Veuillez d'abord réaffecter les étudiants à une autre classe.";
        } else {
            $query = "DELETE FROM classe WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $classe_id);
            
            if ($stmt->execute()) {
                $success = "Classe supprimée avec succès!";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer la liste des classes avec statistiques
$query = "SELECT c.*, 
                 COUNT(e.id) as nombre_etudiants,
                 COALESCE(SUM(p.montant_paye), 0) as total_paiements
          FROM classe c 
          LEFT JOIN etudiants e ON c.id = e.classe_id 
          LEFT JOIN paiements p ON e.id = p.etudiant_id AND p.statut = 'payé'
          GROUP BY c.id 
          ORDER BY c.niveau, c.nom";
$stmt = $db->prepare($query);
$stmt->execute();
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer une classe spécifique pour modification
$classe_a_modifier = null;
if (isset($_GET['modifier_classe'])) {
    $classe_id = $_GET['modifier_classe'];
    $query_classe = "SELECT * FROM classe WHERE id = :id";
    $stmt_classe = $db->prepare($query_classe);
    $stmt_classe->bindParam(':id', $classe_id);
    $stmt_classe->execute();
    $classe_a_modifier = $stmt_classe->fetch(PDO::FETCH_ASSOC);
}

// Options pour les selects
$niveaux = ['8ème','7ème', '6ème', '5ème', '4ème', '3ème', '2nde', '1ère', 'Terminale'];
$filieres = ['Générale', 'Scientifique', 'Littéraire', 'Technologique', 'Professionnelle'];
$annees_scolaires = [
    (date('Y')-1) . '-' . date('Y'),
    date('Y') . '-' . (date('Y')+1),
    (date('Y')+1) . '-' . (date('Y')+2)
];
?>

<?php 
$page_title = "Gestion des Classes";
include 'layout.php'; 
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house"></i></a></li>
        <li class="breadcrumb-item active" aria-current="page">Classes</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-building me-2"></i>Gestion des Classes</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterClasseModal">
        <i class="bi bi-plus-circle"></i> Nouvelle Classe
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

<!-- Cartes de statistiques -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h4 class="mb-0"><?php echo count($classes); ?></h4>
                <small>Total Classes</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">
                    <?php 
                    $query_total_etudiants = "SELECT COUNT(*) as total FROM etudiants";
                    $stmt_total_etudiants = $db->prepare($query_total_etudiants);
                    $stmt_total_etudiants->execute();
                    echo $stmt_total_etudiants->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                </h4>
                <small>Total Élèves</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">
                    <?php 
                    $query_capacite = "SELECT SUM(capacite_max) as total FROM classe";
                    $stmt_capacite = $db->prepare($query_capacite);
                    $stmt_capacite->execute();
                    echo $stmt_capacite->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                </h4>
                <small>Capacité Totale</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h4 class="mb-0">
                    <?php 
                    $query_annee = "SELECT DISTINCT annee_scolaire FROM classe LIMIT 1";
                    $stmt_annee = $db->prepare($query_annee);
                    $stmt_annee->execute();
                    $annee = $stmt_annee->fetch(PDO::FETCH_ASSOC);
                    echo $annee ? $annee['annee_scolaire'] : date('Y') . '-' . (date('Y') + 1);
                    ?>
                </h4>
                <small>Année Scolaire</small>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des classes -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0"><i class="bi bi-list-ul"></i> Liste des Classes</h5>
    </div>
    <div class="card-body">
        <?php if (count($classes) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Nom</th>
                        <th>Niveau</th>
                        <th>Filière</th>
                        <th>Élèves</th>
                        <th>Capacité</th>
                        <th>Paiements</th>
                        <th>Année Scolaire</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classes as $classe): 
                        $pourcentage_remplissage = $classe['capacite_max'] > 0 ? 
                            round(($classe['nombre_etudiants'] / $classe['capacite_max']) * 100, 1) : 0;
                        $bg_class = $pourcentage_remplissage >= 90 ? 'bg-danger' : 
                                   ($pourcentage_remplissage >= 75 ? 'bg-warning' : 'bg-success');
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($classe['nom']); ?></strong>
                        </td>
                        <td>
                            <span class="badge bg-primary"><?php echo htmlspecialchars($classe['niveau']); ?></span>
                        </td>
                        <td>
                            <small><?php echo htmlspecialchars($classe['filiere']); ?></small>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="me-2"><?php echo $classe['nombre_etudiants']; ?></span>
                                <div class="progress flex-grow-1" style="height: 8px; width: 80px;">
                                    <div class="progress-bar <?php echo $bg_class; ?>" 
                                         style="width: <?php echo min($pourcentage_remplissage, 100); ?>%">
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <small><?php echo $classe['capacite_max']; ?> places</small>
                        </td>
                        <td>
                            <span class="badge bg-success">
                                <?php echo number_format($classe['total_paiements'], 0, ',', ' '); ?> AOA
                            </span>
                        </td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars($classe['annee_scolaire']); ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="etudiants.php?classe=<?php echo $classe['id']; ?>" 
                                   class="btn btn-info" data-bs-toggle="tooltip" title="Voir les élèves">
                                    <i class="bi bi-people"></i>
                                </a>
                                <button class="btn btn-warning" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modifierClasseModal"
                                        onclick="chargerDonneesClasse(<?php echo htmlspecialchars(json_encode($classe)); ?>)"
                                        data-bs-toggle="tooltip" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="?supprimer_classe=<?php echo $classe['id']; ?>" 
                                   class="btn btn-outline-danger" 
                                   data-bs-toggle="tooltip" 
                                   title="Supprimer"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer la classe <?php echo htmlspecialchars($classe['nom']); ?> ?')">
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
            <i class="bi bi-building display-1 text-muted"></i>
            <h4 class="text-muted mt-3">Aucune classe enregistrée</h4>
            <p class="text-muted">Commencez par créer la première classe.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterClasseModal">
                <i class="bi bi-plus-circle"></i> Créer la première classe
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter Classe -->
<div class="modal fade" id="ajouterClasseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nouvelle Classe</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom de la classe *</label>
                            <input type="text" class="form-control" id="nom" name="nom" required 
                                   placeholder="Ex: 6ème A">
                        </div>
                        <div class="col-md-6">
                            <label for="niveau" class="form-label">Niveau *</label>
                            <select class="form-control" id="niveau" name="niveau" required>
                                <option value="">Sélectionner un niveau</option>
                                <?php foreach ($niveaux as $niveau): ?>
                                <option value="<?php echo $niveau; ?>"><?php echo $niveau; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="filiere" class="form-label">Filière</label>
                            <select class="form-control" id="filiere" name="filiere">
                                <option value="Générale">Générale</option>
                                <?php foreach ($filieres as $filiere): ?>
                                <option value="<?php echo $filiere; ?>"><?php echo $filiere; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="capacite_max" class="form-label">Capacité maximale *</label>
                            <input type="number" class="form-control" id="capacite_max" name="capacite_max" 
                                   required min="1" max="100" value="30">
                        </div>
                        <div class="col-12">
                            <label for="annee_scolaire" class="form-label">Année scolaire *</label>
                            <select class="form-control" id="annee_scolaire" name="annee_scolaire" required>
                                <option value="">Sélectionner l'année scolaire</option>
                                <?php foreach ($annees_scolaires as $annee): ?>
                                <option value="<?php echo $annee; ?>" 
                                    <?php echo $annee == date('Y') . '-' . (date('Y')+1) ? 'selected' : ''; ?>>
                                    <?php echo $annee; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="ajouter_classe" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier Classe -->
<div class="modal fade" id="modifierClasseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Modifier la Classe</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="modifier_classe_id" name="classe_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modifier_nom" class="form-label">Nom de la classe *</label>
                            <input type="text" class="form-control" id="modifier_nom" name="nom" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modifier_niveau" class="form-label">Niveau *</label>
                            <select class="form-control" id="modifier_niveau" name="niveau" required>
                                <option value="">Sélectionner un niveau</option>
                                <?php foreach ($niveaux as $niveau): ?>
                                <option value="<?php echo $niveau; ?>"><?php echo $niveau; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modifier_filiere" class="form-label">Filière</label>
                            <select class="form-control" id="modifier_filiere" name="filiere">
                                <?php foreach ($filieres as $filiere): ?>
                                <option value="<?php echo $filiere; ?>"><?php echo $filiere; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="modifier_capacite_max" class="form-label">Capacité maximale *</label>
                            <input type="number" class="form-control" id="modifier_capacite_max" name="capacite_max" 
                                   required min="1" max="100">
                        </div>
                        <div class="col-12">
                            <label for="modifier_annee_scolaire" class="form-label">Année scolaire *</label>
                            <select class="form-control" id="modifier_annee_scolaire" name="annee_scolaire" required>
                                <option value="">Sélectionner l'année scolaire</option>
                                <?php foreach ($annees_scolaires as $annee): ?>
                                <option value="<?php echo $annee; ?>"><?php echo $annee; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="modifier_classe" class="btn btn-warning">
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

// Fonction pour charger les données dans le modal de modification
function chargerDonneesClasse(classe) {
    document.getElementById('modifier_classe_id').value = classe.id;
    document.getElementById('modifier_nom').value = classe.nom;
    document.getElementById('modifier_niveau').value = classe.niveau;
    document.getElementById('modifier_filiere').value = classe.filiere;
    document.getElementById('modifier_capacite_max').value = classe.capacite_max;
    document.getElementById('modifier_annee_scolaire').value = classe.annee_scolaire;
}

// Génération automatique du nom de classe basé sur le niveau
document.getElementById('niveau').addEventListener('change', function() {
    const niveau = this.value;
    const nomInput = document.getElementById('nom');
    
    if (niveau && !nomInput.value) {
        // Générer un nom basé sur le niveau
        const niveauxClasses = {
            '8ème': ['8ème A', '8ème B', '8ème C'],
            '7ème': ['7ème A', '7ème B', '7ème C'],
            '6ème': ['6ème A', '6ème B', '6ème C'],
            '5ème': ['5ème A', '5ème B', '5ème C'],
            '4ème': ['4ème A', '4ème B', '4ème C'],
            '3ème': ['3ème A', '3ème B', '3ème C'],
            '2nde': ['2nde A', '2nde B', '2nde C'],
            '1ère': ['1ère A', '1ère B', '1ère C'],
            'Terminale': ['Tle A', 'Tle B', 'Tle C']
        };
        
        if (niveauxClasses[niveau]) {
            nomInput.placeholder = "Ex: " + niveauxClasses[niveau][0];
        }
    }
});
</script>