<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Vérifier les permissions (seul l'admin peut gérer les utilisateurs)
if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = $error = '';

// Ajouter un utilisateur
if ($_POST && isset($_POST['ajouter_utilisateur'])) {
    try {
        $nom_complet = $_POST['nom_complet'];
        $username = $_POST['username'];
        $role = $_POST['role'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Vérifier si le username existe déjà
        $query_check_username = "SELECT id FROM utilisateurs WHERE username = :username";
        $stmt_check_username = $db->prepare($query_check_username);
        $stmt_check_username->bindParam(':username', $username);
        $stmt_check_username->execute();
        
        if ($stmt_check_username->rowCount() > 0) {
            $error = "Ce nom d'utilisateur est déjà utilisé!";
        } else {
            $query = "INSERT INTO utilisateurs (nom_complet, username, password, role) 
                      VALUES (:nom_complet, :username, :password, :role)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nom_complet', $nom_complet);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':role', $role);
            
            if ($stmt->execute()) {
                $success = "Utilisateur ajouté avec succès!";
                $_POST = array(); // Vider le formulaire
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Modifier un utilisateur
if ($_POST && isset($_POST['modifier_utilisateur'])) {
    try {
        $utilisateur_id = $_POST['utilisateur_id'];
        $nom_complet = $_POST['nom_complet'];
        $username = $_POST['username'];
        $role = $_POST['role'];
        
        // Vérifier si le username existe déjà pour un autre utilisateur
        $query_check_username = "SELECT id FROM utilisateurs WHERE username = :username AND id != :id";
        $stmt_check_username = $db->prepare($query_check_username);
        $stmt_check_username->bindParam(':username', $username);
        $stmt_check_username->bindParam(':id', $utilisateur_id);
        $stmt_check_username->execute();
        
        if ($stmt_check_username->rowCount() > 0) {
            $error = "Ce nom d'utilisateur est déjà utilisé par un autre utilisateur!";
        } else {
            $query = "UPDATE utilisateurs SET nom_complet = :nom_complet, username = :username, role = :role 
                     WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nom_complet', $nom_complet);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':id', $utilisateur_id);
            
            if ($stmt->execute()) {
                $success = "Utilisateur modifié avec succès!";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Réinitialiser le mot de passe
if ($_POST && isset($_POST['reinitialiser_mdp'])) {
    try {
        $utilisateur_id = $_POST['utilisateur_id'];
        $nouveau_password = password_hash($_POST['nouveau_password'], PASSWORD_DEFAULT);
        
        $query = "UPDATE utilisateurs SET password = :password WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $nouveau_password);
        $stmt->bindParam(':id', $utilisateur_id);
        
        if ($stmt->execute()) {
            $success = "Mot de passe réinitialisé avec succès!";
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Supprimer un utilisateur
if (isset($_GET['supprimer_utilisateur'])) {
    try {
        $utilisateur_id = $_GET['supprimer_utilisateur'];
        
        // Empêcher la suppression de l'utilisateur connecté
        if ($utilisateur_id == $_SESSION['user_id']) {
            $error = "Vous ne pouvez pas supprimer votre propre compte!";
        } else {
            $query = "DELETE FROM utilisateurs WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $utilisateur_id);
            
            if ($stmt->execute()) {
                $success = "Utilisateur supprimé avec succès!";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer la liste des utilisateurs
$query = "SELECT * FROM utilisateurs ORDER BY nom_complet";
$stmt = $db->prepare($query);
$stmt->execute();
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer un utilisateur spécifique pour modification
$utilisateur_a_modifier = null;
if (isset($_GET['modifier_utilisateur'])) {
    $utilisateur_id = $_GET['modifier_utilisateur'];
    $query_utilisateur = "SELECT * FROM utilisateurs WHERE id = :id";
    $stmt_utilisateur = $db->prepare($query_utilisateur);
    $stmt_utilisateur->bindParam(':id', $utilisateur_id);
    $stmt_utilisateur->execute();
    $utilisateur_a_modifier = $stmt_utilisateur->fetch(PDO::FETCH_ASSOC);
}

// Options pour les selects
$roles = ['admin' => 'Administrateur', 'caissier' => 'Caissier', 'secretaire' => 'Secrétaire'];
?>

<?php 
$page_title = "Gestion des Utilisateurs";
include 'layout.php'; 
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i></a></li>
        <li class="breadcrumb-item active" aria-current="page">Utilisateurs</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people me-2"></i>Gestion des Utilisateurs</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterUtilisateurModal">
        <i class="bi bi-person-plus"></i> Nouvel Utilisateur
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
                <h4 class="mb-0"><?php echo count($utilisateurs); ?></h4>
                <small>Total Utilisateurs</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">
                    <?php 
                    $query_admins = "SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'admin'";
                    $stmt_admins = $db->prepare($query_admins);
                    $stmt_admins->execute();
                    echo $stmt_admins->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                </h4>
                <small>Administrateurs</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h4 class="mb-0">
                    <?php 
                    $query_caissiers = "SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'caissier'";
                    $stmt_caissiers = $db->prepare($query_caissiers);
                    $stmt_caissiers->execute();
                    echo $stmt_caissiers->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                </h4>
                <small>Caissiers</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h4 class="mb-0">
                    <?php 
                    $query_secretaires = "SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'secretaire'";
                    $stmt_secretaires = $db->prepare($query_secretaires);
                    $stmt_secretaires->execute();
                    echo $stmt_secretaires->fetch(PDO::FETCH_ASSOC)['total'];
                    ?>
                </h4>
                <small>Secrétaires</small>
            </div>
        </div>
    </div>
</div>

<!-- Tableau des utilisateurs -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0"><i class="bi bi-list-ul"></i> Liste des Utilisateurs</h5>
    </div>
    <div class="card-body">
        <?php if (count($utilisateurs) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Nom Complet</th>
                        <th>Nom d'utilisateur</th>
                        <th>Rôle</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($utilisateurs as $utilisateur): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($utilisateur['nom_complet']); ?></strong>
                            <?php if ($utilisateur['id'] == $_SESSION['user_id']): ?>
                            <span class="badge bg-primary ms-1">Vous</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code><?php echo htmlspecialchars($utilisateur['username']); ?></code>
                        </td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $utilisateur['role'] == 'admin' ? 'danger' : 
                                    ($utilisateur['role'] == 'caissier' ? 'success' : 'info'); 
                            ?>">
                                <?php echo $roles[$utilisateur['role']] ?? $utilisateur['role']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-warning" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modifierUtilisateurModal"
                                        onclick="chargerDonneesUtilisateur(<?php echo htmlspecialchars(json_encode($utilisateur)); ?>)"
                                        data-bs-toggle="tooltip" title="Modifier">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-info" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#reinitialiserMdpModal"
                                        onclick="chargerUtilisateurMdp(<?php echo htmlspecialchars(json_encode($utilisateur)); ?>)"
                                        data-bs-toggle="tooltip" title="Réinitialiser MDP">
                                    <i class="bi bi-key"></i>
                                </button>
                                <?php if ($utilisateur['id'] != $_SESSION['user_id']): ?>
                                <a href="?supprimer_utilisateur=<?php echo $utilisateur['id']; ?>" 
                                   class="btn btn-outline-danger" 
                                   data-bs-toggle="tooltip" 
                                   title="Supprimer"
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer l\\'utilisateur <?php echo htmlspecialchars(addslashes($utilisateur['nom_complet'])); ?> ?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled data-bs-toggle="tooltip" title="Impossible de supprimer votre compte">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
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
            <h4 class="text-muted mt-3">Aucun utilisateur enregistré</h4>
            <p class="text-muted">Commencez par créer le premier utilisateur.</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterUtilisateurModal">
                <i class="bi bi-plus-circle"></i> Créer le premier utilisateur
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajouter Utilisateur -->
<div class="modal fade" id="ajouterUtilisateurModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Nouvel Utilisateur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nom_complet" class="form-label">Nom Complet *</label>
                            <input type="text" class="form-control" id="nom_complet" name="nom_complet" required 
                                   placeholder="Nom et prénom complet">
                        </div>
                        <div class="col-12">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   placeholder="Nom de connexion">
                            <div class="form-text">Ce nom sera utilisé pour se connecter au système.</div>
                        </div>
                        <div class="col-12">
                            <label for="password" class="form-label">Mot de passe *</label>
                            <input type="password" class="form-control" id="password" name="password" required 
                                   placeholder="Mot de passe sécurisé">
                        </div>
                        <div class="col-12">
                            <label for="role" class="form-label">Rôle *</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <?php foreach ($roles as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="ajouter_utilisateur" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier Utilisateur -->
<div class="modal fade" id="modifierUtilisateurModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Modifier l'Utilisateur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="modifier_utilisateur_id" name="utilisateur_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="modifier_nom_complet" class="form-label">Nom Complet *</label>
                            <input type="text" class="form-control" id="modifier_nom_complet" name="nom_complet" required>
                        </div>
                        <div class="col-12">
                            <label for="modifier_username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="modifier_username" name="username" required>
                        </div>
                        <div class="col-12">
                            <label for="modifier_role" class="form-label">Rôle *</label>
                            <select class="form-control" id="modifier_role" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <?php foreach ($roles as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="modifier_utilisateur" class="btn btn-warning">
                        <i class="bi bi-save"></i> Modifier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Réinitialiser Mot de Passe -->
<div class="modal fade" id="reinitialiserMdpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-key"></i> Réinitialiser le Mot de Passe</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="mdp_utilisateur_id" name="utilisateur_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nouveau_password" class="form-label">Nouveau mot de passe *</label>
                        <input type="password" class="form-control" id="nouveau_password" name="nouveau_password" required 
                               placeholder="Nouveau mot de passe sécurisé">
                        <div class="form-text">Le mot de passe sera chiffré avant stockage.</div>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Attention :</strong> Cette action réinitialisera immédiatement le mot de passe de l'utilisateur.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="reinitialiser_mdp" class="btn btn-info">
                        <i class="bi bi-key"></i> Réinitialiser
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
function chargerDonneesUtilisateur(utilisateur) {
    document.getElementById('modifier_utilisateur_id').value = utilisateur.id;
    document.getElementById('modifier_nom_complet').value = utilisateur.nom_complet;
    document.getElementById('modifier_username').value = utilisateur.username;
    document.getElementById('modifier_role').value = utilisateur.role;
}

// Fonction pour charger l'utilisateur dans le modal de réinitialisation MDP
function chargerUtilisateurMdp(utilisateur) {
    document.getElementById('mdp_utilisateur_id').value = utilisateur.id;
    document.getElementById('reinitialiserMdpModal').querySelector('.modal-title').innerHTML = 
        '<i class="bi bi-key"></i> Réinitialiser MDP - ' + utilisateur.nom_complet;
}

// Génération automatique de mot de passe sécurisé
document.getElementById('password').addEventListener('focus', function() {
    if (!this.value) {
        // Générer un mot de passe sécurisé
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        let password = '';
        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        this.value = password;
    }
});

// Génération automatique pour le nouveau mot de passe
document.getElementById('nouveau_password').addEventListener('focus', function() {
    if (!this.value) {
        // Générer un mot de passe sécurisé
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        let password = '';
        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        this.value = password;
    }
});
</script>