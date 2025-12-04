<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = $error = '';

// Ajouter un étudiant
if ($_POST && isset($_POST['modifi_profil'])) {
    try {
        $user_id = intval($_SESSION['user_id']);
        $nom_complet = $_POST['nom_complet'];
        $username = $_POST['username']; 
        
        $query = "UPDATE utilisateurs SET nom_complet = :nom_complet, username = :username WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nom_complet', $nom_complet);
        $stmt->bindParam(':username', $username); 
        $stmt->bindParam(':id', $user_id); 
        
        if ($stmt->execute()) {
            $success = "Profil modifié avec succès!";
            // Mettre à jour les données du profil après modification
            $_SESSION['nom_complet'] = $nom_complet;
            $_SESSION['username'] = $username;
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer le profile
$user_id = intval($_SESSION['user_id']);
$query = "SELECT * FROM utilisateurs WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$profil = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php 
$page_title = "Mon Profil";
include 'layout.php'; 
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i></a></li>
        <li class="breadcrumb-item active" aria-current="page">Profil</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-person me-2"></i>Mon Profil</h2>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modifUser">
        <i class="bi bi-pencil"></i> Modifier
    </button>
</div>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <?php if (!empty($profil)): ?>
            <?php foreach ($profil as $prof): ?>
            <div class="card-header bg-white">
                <h3 class="card-title mb-0"><?php echo htmlspecialchars($prof['nom_complet'] ?? ''); ?></h3>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <div class="list-group-item p-4">
                        <div class="text-center mb-4">
                            <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                <i class="bi bi-person-fill text-white" style="font-size: 3rem;"></i>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="d-flex mb-3">
                                <div class="col-6">
                                    <h5 class="mb-0 fw-bold">Nom d'utilisateur</h5>
                                </div>
                                <div class="col-6">
                                    <h6 class="mb-0 text-muted"><?php echo htmlspecialchars($prof['username'] ?? ''); ?></h6>
                                </div>
                            </div>

                            <div class="d-flex mb-3">
                                <div class="col-6">
                                    <h5 class="mb-0 fw-bold">Rôle</h5>
                                </div>
                                <div class="col-6">
                                    <h6 class="mb-0">
                                        <span class="badge bg-<?php 
                                            echo $prof['role'] == 'admin' ? 'danger' : 
                                                ($prof['role'] == 'caissier' ? 'success' : 'info'); 
                                        ?>">
                                            <?php echo htmlspecialchars($prof['role'] ?? ''); ?>
                                        </span>
                                    </h6>
                                </div>
                            </div>

                            <div class="d-flex mb-3">
                                <div class="col-6">
                                    <h5 class="mb-0 fw-bold">Email</h5>
                                </div>
                                <div class="col-6">
                                    <h6 class="mb-0 text-muted"><?php echo htmlspecialchars($prof['email'] ?? 'Non spécifié'); ?></h6>
                                </div>
                            </div>

                            <div class="d-flex">
                                <div class="col-6">
                                    <h5 class="mb-0 fw-bold">Date d'inscription</h5>
                                </div>
                                <div class="col-6">
                                    <h6 class="mb-0 text-muted">
                                        <?php echo !empty($prof['created_at']) ? date('d/m/Y', strtotime($prof['created_at'])) : 'Non spécifié'; ?>
                                    </h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="card-body text-center py-5">
                <i class="bi bi-person-x display-1 text-muted"></i>
                <h4 class="text-muted mt-3">Profil non trouvé</h4>
                <p class="text-muted">Votre profil n'a pas pu être chargé.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Section statistiques ou informations supplémentaires -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-info-circle"></i> À propos</h5>
            </div>
            <div class="card-body">
                <p class="card-text">
                    Bienvenue sur votre page de profil. Vous pouvez modifier vos informations personnelles
                    en cliquant sur le bouton "Modifier".
                </p>
                <div class="alert alert-info">
                    <i class="bi bi-shield-check"></i>
                    <strong>Sécurité :</strong> Pour des raisons de sécurité, certains champs comme le rôle
                    ne peuvent pas être modifiés directement depuis cette interface.
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-activity"></i> Dernières activités</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item border-0 px-0">
                        <small class="text-muted">Dernière connexion :</small>
                        <div><?php echo date('d/m/Y H:i'); ?></div>
                    </div>
                    <div class="list-group-item border-0 px-0">
                        <small class="text-muted">Statut :</small>
                        <div><span class="badge bg-success">Connecté</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal MODIFIER USER -->
<div class="modal fade" id="modifUser" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Modifier le profil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php if (!empty($profil)): ?>
                    <?php foreach ($profil as $prof): ?>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="nom_complet" class="form-label">Nom Complet *</label>
                            <input type="text" class="form-control" id="nom_complet" 
                                   value="<?php echo htmlspecialchars($prof['nom_complet'] ?? ''); ?>" 
                                   name="nom_complet" required>
                        </div>
                        <div class="col-md-12">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="username" 
                                   value="<?php echo htmlspecialchars($prof['username'] ?? ''); ?>" 
                                   name="username" required>
                        </div>
                        <div class="col-md-12">
                            <div class="form-text">
                                <i class="bi bi-info-circle"></i> Le nom d'utilisateur doit être unique.
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="modifi_profil" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer
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

// Validation du formulaire
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[method="POST"]');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const nomComplet = document.getElementById('nom_complet').value.trim();
            const username = document.getElementById('username').value.trim();
            
            if (!nomComplet || !username) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
                return false;
            }
            
            if (username.length < 3) {
                e.preventDefault();
                alert('Le nom d\'utilisateur doit contenir au moins 3 caractères.');
                return false;
            }
        });
    }
});
</script>