<?php
include 'config.php';

// Rediriger si déjà connecté
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_POST) {
    // Validation du token CSRF
    if (empty($_POST['csrf_token']) || !CSRF::validateToken($_POST['csrf_token'])) {
        $error = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Validation et nettoyage des entrées
        $username = Validator::validateText($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!$username || empty($password)) {
            $error = "Nom d'utilisateur ou mot de passe invalide";
        } else {
            // Recherche de l'utilisateur - CORRIGÉ selon la structure de la table
            $query = "SELECT id, username, password, role, nom_complet, statut, derniere_connexion 
                      FROM utilisateurs 
                      WHERE username = :username";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Vérification du mot de passe avec password_verify - CORRIGÉ pour utiliser 'statut' au lieu de 'is_active'
                if (password_verify($password, $user['password']) && $user['statut'] === 'actif') {
                    // Mettre à jour la dernière connexion - CORRIGÉ selon le nom du champ
                    updateLastLogin($db, $user['id']);
                    
                    // Régénération de session
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['nom_complet'] = $user['nom_complet'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    $_SESSION['last_activity'] = time();
                    
                    // Journalisation
                    Logger::logSecurityEvent("Connexion réussie", $user['id']);
                    
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Nom d'utilisateur ou mot de passe incorrect";
                    // Journalisation
                    Logger::logSecurityEvent("Tentative de connexion échouée", $user['id'] ?? 'unknown');
                }
            } else {
                // Utilisateur non trouvé - délai artificiel pour éviter l'énumération
                sleep(2);
                $error = "Nom d'utilisateur ou mot de passe incorrect";
            }
        }
    }
}

/**
 * Met à jour la dernière connexion - CORRIGÉ selon le nom du champ
 */
function updateLastLogin($db, $user_id) {
    $query = "UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion Finance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .logo-container {
            margin-bottom: 8px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .security-info {
            font-size: 0.875rem;
        }
         .logo {
                max-width: 70px;
            }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card p-5">
                    <div class="text-center mb-4">
                        <!-- <i class="bi bi-building display-1 text-primary"></i> -->
                        <div class="logo-container"> 
                            <img src="assets/images/logo.png" alt="Logo École" class="logo"> 
                        </div> 
                        <h2 class="mt-3 fw-bold">Connexion</h2>
                        <p class="text-muted">C.S FRANCOPHONE LES BAMBINS SAGES</p>
                    </div>

                    <?php if (isset($error) && !empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold">Nom d'utilisateur</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-person text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0" id="username" name="username" 
                                       placeholder="Votre nom d'utilisateur" required
                                       maxlength="50"
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Mot de passe</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control border-start-0" id="password" name="password" 
                                       placeholder="Votre mot de passe" required
                                       minlength="8"
                                       maxlength="100">
                            </div>
                            <div class="form-text security-info">
                                <i class="bi bi-info-circle"></i> Le mot de passe doit contenir au moins 8 caractères
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-login w-100 mb-3">
                            <i class="bi bi-box-arrow-in-right"></i> Se connecter
                        </button>
                    </form>

                    <!-- Aide pour le test -->
                    <div class="text-center security-info">
                        <p class="text-warning small">
                            <i class="bi bi-shield-check"></i> Votre connexion est sécurisée
                        </p>
                        <?php if (empty($error)): ?>
                        <!-- <p class="text-muted small mt-2">
                            <strong>Test:</strong> admin / password
                        </p> -->
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validation côté client
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            if (password.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères');
                return false;
            }
        });
        
        // Masquer les messages d'erreur après 5 secondes
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>