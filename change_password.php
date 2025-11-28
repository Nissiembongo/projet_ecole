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

// Changement de mot de passe
if ($_POST && isset($_POST['changer_mot_de_passe'])) {
    // Validation CSRF
    if (empty($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        try {
            // Validation et nettoyage des données
            $ancien_mot_de_passe = $_POST['ancien_mot_de_passe'] ?? '';
            $nouveau_mot_de_passe = $_POST['nouveau_mot_de_passe'] ?? '';
            $confirmation_mot_de_passe = $_POST['confirmation_mot_de_passe'] ?? '';

            // Validation des champs obligatoires
            if (empty($ancien_mot_de_passe) || empty($nouveau_mot_de_passe) || empty($confirmation_mot_de_passe)) {
                $error = "Tous les champs doivent être remplis.";
            } elseif (strlen($nouveau_mot_de_passe) < 8) {
                $error = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
            } elseif ($nouveau_mot_de_passe !== $confirmation_mot_de_passe) {
                $error = "Les nouveaux mots de passe ne correspondent pas.";
            } else {
                // Récupérer l'utilisateur actuel
                $query_utilisateur = "SELECT id, username, password FROM utilisateurs WHERE id = :id";
                $stmt_utilisateur = $db->prepare($query_utilisateur);
                $stmt_utilisateur->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
                $stmt_utilisateur->execute();
                $utilisateur = $stmt_utilisateur->fetch(PDO::FETCH_ASSOC);

                if (!$utilisateur) {
                    $error = "Utilisateur non trouvé.";
                } elseif (!password_verify($ancien_mot_de_passe, $utilisateur['password'])) {
                    $error = "L'ancien mot de passe est incorrect.";
                } else {
                    // Vérifier que le nouveau mot de passe est différent de l'ancien
                    if (password_verify($nouveau_mot_de_passe, $utilisateur['password'])) {
                        $error = "Le nouveau mot de passe doit être différent de l'ancien.";
                    } else {
                        // Hasher le nouveau mot de passe
                        $nouveau_mot_de_passe_hash = password_hash($nouveau_mot_de_passe, PASSWORD_DEFAULT);
                        
                        // Mettre à jour le mot de passe
                        $query_update = "UPDATE utilisateurs SET password = :password WHERE id = :id";
                        $stmt_update = $db->prepare($query_update);
                        $stmt_update->bindParam(':password', $nouveau_mot_de_passe_hash);
                        $stmt_update->bindParam(':id', $_SESSION['user_id'], PDO::PARAM_INT);
                        
                        if ($stmt_update->execute()) {
                            // Journaliser l'action
                            Logger::logSecurityEvent("Mot de passe changé", $_SESSION['user_id'], 'auth', 'change_password', $_SESSION['user_id'], 'utilisateur');
                            
                            // Préparer la déconnexion
                            $success = "Mot de passe changé avec succès. Vous allez être déconnecté...";
                            
                            // Redirection après 3 secondes
                            echo '<script>
                                setTimeout(function() {
                                    window.location.href = "logout.php?password_changed=1";
                                }, 3000);
                            </script>';
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur changement mot de passe: " . $e->getMessage());
            $error = "Une erreur est survenue lors du changement de mot de passe.";
        }
    }
}
?>

<?php 
$page_title = "Changer le Mot de Passe";
include 'layout.php'; 
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i></a></li>
        <li class="breadcrumb-item active" aria-current="page">Changer le Mot de Passe</li>
    </ol>
</nav>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-shield-lock"></i> Changer le Mot de Passe
                </h5>
            </div>
            <div class="card-body">
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

                <form method="POST" id="changePasswordForm">
                    <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Conseil de sécurité :</strong> Utilisez un mot de passe fort d'au moins 8 caractères.
                    </div>
                    
                    <div class="mb-3">
                        <label for="ancien_mot_de_passe" class="form-label">Ancien mot de passe *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="ancien_mot_de_passe" name="ancien_mot_de_passe" 
                                   required placeholder="Votre mot de passe actuel">
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="ancien_mot_de_passe">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nouveau_mot_de_passe" class="form-label">Nouveau mot de passe *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" 
                                   required placeholder="Nouveau mot de passe (min. 8 caractères)" minlength="8">
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="nouveau_mot_de_passe">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            <div id="password-strength" class="mt-2">
                                <div class="progress" style="height: 5px;">
                                    <div id="password-strength-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small id="password-strength-text" class="text-muted"></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirmation_mot_de_passe" class="form-label">Confirmation du mot de passe *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirmation_mot_de_passe" name="confirmation_mot_de_passe" 
                                   required placeholder="Confirmez le nouveau mot de passe">
                            <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirmation_mot_de_passe">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            <span id="password-match" class="d-none">
                                <i class="bi bi-check-circle text-success"></i> Les mots de passe correspondent
                            </span>
                            <span id="password-mismatch" class="d-none">
                                <i class="bi bi-x-circle text-danger"></i> Les mots de passe ne correspondent pas
                            </span>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Attention :</strong> Vous serez déconnecté après le changement de mot de passe.
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="changer_mot_de_passe" class="btn btn-primary" id="submit-password">
                            <i class="bi bi-key"></i> Changer le mot de passe
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Retour au tableau de bord
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'layout-end.php'; ?>

<script>
// Fonction pour basculer la visibilité du mot de passe
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const passwordInput = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    });
});

// Vérification de la force du mot de passe
document.getElementById('nouveau_mot_de_passe').addEventListener('input', function() {
    const password = this.value;
    const strengthBar = document.getElementById('password-strength-bar');
    const strengthText = document.getElementById('password-strength-text');
    
    let strength = 0;
    let text = '';
    let color = '';
    
    if (password.length >= 8) strength += 25;
    if (password.match(/[a-z]/)) strength += 25;
    if (password.match(/[A-Z]/)) strength += 25;
    if (password.match(/[0-9]/)) strength += 15;
    if (password.match(/[^a-zA-Z0-9]/)) strength += 10;
    
    if (password.length === 0) {
        text = '';
        color = '';
    } else if (strength < 50) {
        text = 'Faible';
        color = 'danger';
    } else if (strength < 75) {
        text = 'Moyen';
        color = 'warning';
    } else {
        text = 'Fort';
        color = 'success';
    }
    
    strengthBar.style.width = strength + '%';
    strengthBar.className = 'progress-bar bg-' + color;
    strengthText.textContent = text;
    strengthText.className = 'text-' + color;
});

// Vérification de la correspondance des mots de passe
function checkPasswordMatch() {
    const password = document.getElementById('nouveau_mot_de_passe').value;
    const confirmPassword = document.getElementById('confirmation_mot_de_passe').value;
    const matchElement = document.getElementById('password-match');
    const mismatchElement = document.getElementById('password-mismatch');
    
    if (confirmPassword === '') {
        matchElement.classList.add('d-none');
        mismatchElement.classList.add('d-none');
    } else if (password === confirmPassword) {
        matchElement.classList.remove('d-none');
        mismatchElement.classList.add('d-none');
    } else {
        matchElement.classList.add('d-none');
        mismatchElement.classList.remove('d-none');
    }
}

document.getElementById('nouveau_mot_de_passe').addEventListener('input', checkPasswordMatch);
document.getElementById('confirmation_mot_de_passe').addEventListener('input', checkPasswordMatch);

// Validation du formulaire
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const password = document.getElementById('nouveau_mot_de_passe').value;
    const confirmPassword = document.getElementById('confirmation_mot_de_passe').value;
    
    if (password.length < 8) {
        e.preventDefault();
        alert('Le mot de passe doit contenir au moins 8 caractères.');
        return false;
    }
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Les mots de passe ne correspondent pas.');
        return false;
    }
});
</script>