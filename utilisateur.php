<?php
include 'config.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
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
    // Validation CSRF
    if (empty($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        try {
            // Validation et nettoyage des données
            $nom_complet = Validator::validateText($_POST['nom_complet'] ?? '');
            $email = Validator::validateText($_POST['email'] ?? ''); 
            $username = Validator::validateText($_POST['username'] ?? '');
            $role = Validator::validateText($_POST['role'] ?? '');
            $password = $_POST['password'] ?? '';

            // Validation des champs obligatoires
            if (!$nom_complet || !$username || !$role || empty($password)) {
                $error = "Tous les champs obligatoires doivent être remplis correctement.";
            } elseif (strlen($password) < 8) {
                $error = "Le mot de passe doit contenir au moins 8 caractères.";
            } else {
                // Vérifier si le username existe déjà
                $query_check_username = "SELECT id FROM utilisateurs WHERE username = :username";
                $stmt_check_username = $db->prepare($query_check_username);
                $stmt_check_username->bindParam(':username', $username);
                $stmt_check_username->execute();
                
                if ($stmt_check_username->rowCount() > 0) {
                    $error = "Ce nom d'utilisateur est déjà utilisé!";
                } else {
                    $current_date = date('Y-m-d H:i:s');
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $query = "INSERT INTO utilisateurs (nom_complet, email, username, created_at,  password, role, statut) 
                              VALUES (:nom_complet, :email, :username, :created_at, :password, :role, 'actif')";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':nom_complet', $nom_complet);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':created_at', $current_date);
                    $stmt->bindParam(':password', $password_hash);
                    $stmt->bindParam(':role', $role);
                    
                    if ($stmt->execute()) {
                        $success = "Utilisateur ajouté avec succès!";
                        Logger::logSecurityEvent("Nouvel utilisateur créé: " . $username, $_SESSION['user_id']);
                        $_POST = array(); // Vider le formulaire
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur ajout utilisateur: " . $e->getMessage());
            $error = "Une erreur est survenue lors de l'ajout de l'utilisateur.";
        }
    }
}

// Modifier un utilisateur
if ($_POST && isset($_POST['modifier_utilisateur'])) {
    // Validation CSRF
    if (empty($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        try {
            // Validation et nettoyage des données
            $utilisateur_id = Validator::validateNumber($_POST['utilisateur_id'] ?? 0);
            $nom_complet = Validator::validateText($_POST['nom_complet'] ?? '');
            $username = Validator::validateText($_POST['username'] ?? '');
            $email = Validator::validateText($_POST['email'] ?? '');
            $role = Validator::validateText($_POST['role'] ?? '');

            if (!$utilisateur_id || !$nom_complet || !$username || !$role) {
                $error = "Tous les champs obligatoires doivent être remplis correctement.";
            } else {
                // Vérifier si le username existe déjà pour un autre utilisateur
                $query_check_username = "SELECT id FROM utilisateurs WHERE username = :username AND id != :id";
                $stmt_check_username = $db->prepare($query_check_username);
                $stmt_check_username->bindParam(':username', $username);
                $stmt_check_username->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
                $stmt_check_username->execute();
                
                if ($stmt_check_username->rowCount() > 0) {
                    $error = "Ce nom d'utilisateur est déjà utilisé par un autre utilisateur!";
                } else {
                    $query = "UPDATE utilisateurs SET nom_complet = :nom_complet, username = :username,  email = :email, role = :role 
                             WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':nom_complet', $nom_complet);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':role', $role);
                    $stmt->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $success = "Utilisateur modifié avec succès!";
                        Logger::logSecurityEvent("Utilisateur modifié: " . $username, $_SESSION['user_id']);
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur modification utilisateur: " . $e->getMessage());
            $error = "Une erreur est survenue lors de la modification de l'utilisateur.";
        }
    }
}

// Réinitialiser le mot de passe
if ($_POST && isset($_POST['reinitialiser_mdp'])) {
    // Validation CSRF
    if (empty($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        try {
            $utilisateur_id = Validator::validateNumber($_POST['utilisateur_id'] ?? 0);
            $nouveau_password = $_POST['nouveau_password'] ?? '';
            
            if (!$utilisateur_id || empty($nouveau_password)) {
                $error = "Tous les champs doivent être remplis correctement.";
            } elseif (strlen($nouveau_password) < 8) {
                $error = "Le mot de passe doit contenir au moins 8 caractères.";
            } else {
                $nouveau_password_hash = password_hash($nouveau_password, PASSWORD_DEFAULT);
                
                $query = "UPDATE utilisateurs SET password = :password WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':password', $nouveau_password_hash);
                $stmt->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success = "Mot de passe réinitialisé avec succès!";
                    Logger::logSecurityEvent("Mot de passe réinitialisé pour l'utilisateur ID: " . $utilisateur_id, $_SESSION['user_id']);
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur réinitialisation MDP: " . $e->getMessage());
            $error = "Une erreur est survenue lors de la réinitialisation du mot de passe.";
        }
    }
}

// Supprimer un utilisateur
if (isset($_GET['supprimer_utilisateur'])) {
    $utilisateur_id = Validator::validateNumber($_GET['supprimer_utilisateur'] ?? 0);
    
    if ($utilisateur_id) {
        try {
            // Empêcher la suppression de l'utilisateur connecté
            if ($utilisateur_id == $_SESSION['user_id']) {
                $error = "Vous ne pouvez pas supprimer votre propre compte!";
            } else {
                // Récupérer les infos de l'utilisateur pour le log
                $query_info = "SELECT username, nom_complet FROM utilisateurs WHERE id = :id";
                $stmt_info = $db->prepare($query_info);
                $stmt_info->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
                $stmt_info->execute();
                $utilisateur_info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                
                $query = "DELETE FROM utilisateurs WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
                
                if ($stmt->execute()) {
                    $success = "Utilisateur supprimé avec succès!";
                    Logger::logSecurityEvent("Utilisateur supprimé: " . $utilisateur_info['username'] . " - " . $utilisateur_info['nom_complet'], $_SESSION['user_id']);
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur suppression utilisateur: " . $e->getMessage());
            $error = "Une erreur est survenue lors de la suppression de l'utilisateur.";
        }
    } else {
        $error = "ID utilisateur invalide.";
    }
}

// Récupérer la liste des utilisateurs
try {
    $query = "SELECT * FROM utilisateurs ORDER BY nom_complet";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur récupération utilisateurs: " . $e->getMessage());
    $utilisateurs = [];
}

// Récupérer un utilisateur spécifique pour modification
$utilisateur_a_modifier = null;
if (isset($_GET['modifier_utilisateur'])) {
    $utilisateur_id = Validator::validateNumber($_GET['modifier_utilisateur'] ?? 0);
    if ($utilisateur_id) {
        try {
            $query_utilisateur = "SELECT * FROM utilisateurs WHERE id = :id";
            $stmt_utilisateur = $db->prepare($query_utilisateur);
            $stmt_utilisateur->bindParam(':id', $utilisateur_id, PDO::PARAM_INT);
            $stmt_utilisateur->execute();
            $utilisateur_a_modifier = $stmt_utilisateur->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur récupération utilisateur: " . $e->getMessage());
            $utilisateur_a_modifier = null;
        }
    }
}

// Options pour les selects (adaptées à votre structure de table)
$roles = ['admin' => 'Administrateur', 'caissier' => 'Caissier'];
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
                    try {
                        $query_admins = "SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'admin'";
                        $stmt_admins = $db->prepare($query_admins);
                        $stmt_admins->execute();
                        echo htmlspecialchars($stmt_admins->fetch(PDO::FETCH_ASSOC)['total']);
                    } catch (PDOException $e) {
                        echo '0';
                    }
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
                    try {
                        $query_caissiers = "SELECT COUNT(*) as total FROM utilisateurs WHERE role = 'caissier'";
                        $stmt_caissiers = $db->prepare($query_caissiers);
                        $stmt_caissiers->execute();
                        echo htmlspecialchars($stmt_caissiers->fetch(PDO::FETCH_ASSOC)['total']);
                    } catch (PDOException $e) {
                        echo '0';
                    }
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
                    try {
                        $query_actifs = "SELECT COUNT(*) as total FROM utilisateurs WHERE statut = 'actif'";
                        $stmt_actifs = $db->prepare($query_actifs);
                        $stmt_actifs->execute();
                        echo htmlspecialchars($stmt_actifs->fetch(PDO::FETCH_ASSOC)['total']);
                    } catch (PDOException $e) {
                        echo count($utilisateurs);
                    }
                    ?>
                </h4>
                <small>Utilisateurs Actifs</small>
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
                        <th>Email</th>
                        <th>Date de création</th>
                        <th>Statut</th>
                        <th>Dernière Connexion</th>
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
                                echo $utilisateur['role'] == 'admin' ? 'danger' : 'success'; 
                            ?>">
                                <?php echo htmlspecialchars($roles[$utilisateur['role']] ?? $utilisateur['role']); ?>
                            </span>
                        </td>
                        <td> 
                            <?php echo htmlspecialchars($utilisateur['email']); ?> 
                        </td>
                        <td> 
                            <?php echo htmlspecialchars($utilisateur['created_at']); ?> 
                        </td>
                        <td>
                            <span class="badge bg-<?php echo ($utilisateur['statut'] ?? 'actif') == 'actif' ? 'success' : 'secondary'; ?>">
                                <?php echo htmlspecialchars($utilisateur['statut'] ?? 'actif'); ?>
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?php 
                                if (!empty($utilisateur['derniere_connexion'])) {
                                    echo date('d/m/Y H:i', strtotime($utilisateur['derniere_connexion']));
                                } else {
                                    echo 'Jamais';
                                }
                                ?>
                            </small>
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
                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer l\\'utilisateur <?php echo htmlspecialchars(addslashes($utilisateur['nom_complet'])); ?> ? Cette action est irréversible.')">
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
                <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="nom_complet" class="form-label">Nom Complet *</label>
                            <input type="text" class="form-control" id="nom_complet" name="nom_complet" required 
                                   placeholder="Nom et prénom complet" maxlength="100"
                                   value="<?php echo isset($_POST['nom_complet']) ? htmlspecialchars($_POST['nom_complet']) : ''; ?>">
                        </div>
                        <div class="col-12">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   placeholder="Nom de connexion" maxlength="50"
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            <div class="form-text">Ce nom sera utilisé pour se connecter au système.</div>
                        </div>
                        <div class="col-12">
                            <label for="email" class="form-label">Nom d'utilisateur *</label>
                            <input type="mail" class="form-control" id="email" name="email" required 
                                   placeholder="Ex admin@gmail.com" maxlength="100"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"> 
                        </div>
                        <div class="col-12">
                            <label for="password" class="form-label">Mot de passe *</label>
                            <input type="password" class="form-control" id="password" name="password" required 
                                   placeholder="Mot de passe sécurisé (min. 8 caractères)" minlength="8"
                                   value="<?php echo isset($_POST['password']) ? htmlspecialchars($_POST['password']) : ''; ?>">
                            <div class="form-text">Le mot de passe doit contenir au moins 8 caractères.</div>
                        </div>
                        <div class="col-12">
                            <label for="role" class="form-label">Rôle *</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <?php foreach ($roles as $key => $value): ?>
                                <option value="<?php echo $key; ?>" <?php echo (isset($_POST['role']) && $_POST['role'] == $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($value); ?>
                                </option>
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
                <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                <input type="hidden" id="modifier_utilisateur_id" name="utilisateur_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="modifier_nom_complet" class="form-label">Nom Complet *</label>
                            <input type="text" class="form-control" id="modifier_nom_complet" name="nom_complet" required maxlength="100">
                        </div>
                        <div class="col-12">
                            <label for="modifier_username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="modifier_username" name="username" required maxlength="50">
                        </div>
                        <div class="col-12">
                            <label for="modifier_email" class="form-label">Email</label>
                            <input type="mail" class="form-control" id="modifier_email" name="email" required maxlength="50">
                        </div>
                        <div class="col-12">
                            <label for="modifier_role" class="form-label">Rôle *</label>
                            <select class="form-control" id="modifier_role" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <?php foreach ($roles as $key => $value): ?>
                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
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
                <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                <input type="hidden" id="mdp_utilisateur_id" name="utilisateur_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nouveau_password" class="form-label">Nouveau mot de passe *</label>
                        <input type="password" class="form-control" id="nouveau_password" name="nouveau_password" required 
                               placeholder="Nouveau mot de passe sécurisé (min. 8 caractères)" minlength="8">
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
    document.getElementById('modifier_email').value = utilisateur.email;
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

// Validation côté client pour les mots de passe
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const passwordInputs = form.querySelectorAll('input[type="password"]');
            passwordInputs.forEach(function(input) {
                if (input.value.length < 8) {
                    e.preventDefault();
                    alert('Le mot de passe doit contenir au moins 8 caractères.');
                    input.focus();
                    return false;
                }
            });
        });
    });
});
</script>