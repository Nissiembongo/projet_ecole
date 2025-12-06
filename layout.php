<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Gestion Finance École'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- CSRF Token pour les requêtes AJAX -->
    <meta name="csrf-token" content="<?php echo CSRF::generateToken(); ?>">
    
    <style>
        /* Styles pour les sous-menus */
        .sidebar .nav-link {
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            transition: all 0.2s;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link.active {
            background-color: #0d6efd;
            color: white !important;
        }

        .sidebar .nav-link[aria-expanded="true"] {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link[aria-expanded="true"] .bi-chevron-down {
            transform: rotate(180deg);
            transition: transform 0.3s;
        }

        .sidebar .nav-link .bi-chevron-down {
            transition: transform 0.3s;
        }

        /* Styles spécifiques pour les sous-menus */
        .sidebar .nav.flex-column.ms-3 {
            padding-left: 1rem;
            margin-top: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .sidebar .nav.flex-column.ms-3 .nav-link {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        .sidebar .nav.flex-column.ms-3 .nav-link.active {
            background-color: #0d6efd;
            color: white !important;
            border-radius: 0.25rem;
        }

        /* Ajout d'une icône différente pour le menu configuration */
        .sidebar .bi-gear {
            color: #6c757d;
        }

        .sidebar .nav-link[aria-expanded="true"] .bi-gear {
            color: #0d6efd;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 15px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
            transform: translateX(5px);
        }
        .sidebar .nav-link.active {
            background: #e74c3c;
            color: #fff;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            border-left: 4px solid #007bff;
        }
        .btn {
            border-radius: 8px;
            padding: 8px 20px;
        }
        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .logout-form {
            display: inline;
        }
    </style>
</head>
<body>
    <!-- Vérification de l'authentification -->
    <?php 
    // Inclure la classe d'authentification
    if (!isset($auth_included)) {
        require_once 'auth.php';
        $auth_included = true;
    }
    
    // Vérifier que l'utilisateur est connecté
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        header('Location: index.php');
        exit();
    }
    
    // Vérifier l'inactivité
    Auth::checkAuth();
    ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <img src="assets/images/logo.png" alt="Logo École" style="max-width: 30px;" class="logo">  C.S FRANCOPHONE LES BAMBINS SAGES
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> 
                            <?php echo htmlspecialchars($_SESSION['nom_complet'] ?? 'Utilisateur'); ?>
                            <small class="badge bg-secondary ms-1"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></small>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <span class="dropdown-item-text small text-muted">
                                    Connecté depuis: <?php echo date('H:i', $_SESSION['login_time'] ?? time()); ?>
                                </span>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="profil.php"><i class="bi bi-person"></i> Mon Profil</a></li>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                    <i class="bi bi-shield-lock"></i> Changer mot de passe
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="logout.php" class="logout-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                                    <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?')">
                                        <i class="bi bi-box-arrow-right"></i> Déconnexion
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar d-md-block">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Tableau de Bord
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'etudiants.php' ? 'active' : ''; ?>" href="etudiants.php">
                                <i class="bi bi-people"></i> Élèves
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'paiements.php' ? 'active' : ''; ?>" href="paiements.php">
                                <i class="bi bi-credit-card"></i> Paiements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'vente.php' ? 'active' : ''; ?>" href="vente.php">
                                <i class="bi bi-cart"></i> Ventes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'caisse.php' ? 'active' : ''; ?>" href="caisse.php">
                                <i class="bi bi-safe"></i> Caisse
                            </a>
                        </li>
                        
                        
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'rapport.php' ? 'active' : ''; ?>" href="rapport.php">
                                <i class="bi bi-graph-up"></i> Rapports
                            </a>
                        </li>
                        <!-- Menu Configuration avec sous-menus -->
                        <li class="nav-item">
                            <a class="nav-link d-flex justify-content-between align-items-center" 
                            data-bs-toggle="collapse" 
                            href="#configurationSubmenu" 
                            role="button" 
                            aria-expanded="false" 
                            aria-controls="configurationSubmenu">
                                <span>
                                    <i class="bi bi-gear text-white"></i> Configuration
                                </span>
                                <i class="bi bi-chevron-down"></i>
                            </a>
                            <div class="collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['classe.php', 'frais.php', 'articles.php']) ? 'show' : ''; ?>" 
                                id="configurationSubmenu">
                                <ul class="nav flex-column ms-3" style="border-left: 2px solid #495057;">
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'classe.php' ? 'active' : ''; ?>" 
                                        href="classe.php">
                                            <i class="bi bi-journals me-2"></i> Classes
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frais.php' ? 'active' : ''; ?>" 
                                        href="frais.php">
                                            <i class="bi bi-cash-coin me-2"></i> Types de Frais
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'articles.php' ? 'active' : ''; ?>" 
                                        href="articles.php">
                                            <i class="bi bi-box-seam me-2"></i> Articles
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <?php if (($_SESSION['role'] ?? '') == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'utilisateur.php' ? 'active' : ''; ?>" href="utilisateur.php">
                                <i class="bi bi-person-gear"></i> Utilisateurs
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <!-- Information de session -->
                    <div class="mt-4 p-3 text-light small">
                        <hr>
                        <div class="text-warning">Session active</div>
                        <div class="text-warning">
                            <!-- <i class="bi bi-clock"></i>  -->
                            <?php 
                            // $inactive_time = time() - ($_SESSION['last_activity'] ?? time());
                            // $remaining_time = 1800 - $inactive_time; // 30 minutes timeout
                            // $minutes = floor($remaining_time / 60);
                            // echo "Expire dans: " . max(0, $minutes) . " min";
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
                
                <!-- Messages d'alerte -->
                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> 
                    <?php echo htmlspecialchars(urldecode($_GET['success'])); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

<!-- Modal Changer Mot de Passe -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-shield-lock"></i> Changer le Mot de Passe</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="change_password.php" id="changePasswordForm">
                <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                <div class="modal-body">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="changer_mot_de_passe" class="btn btn-primary" id="submit-password">
                        <i class="bi bi-key"></i> Changer le mot de passe
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>