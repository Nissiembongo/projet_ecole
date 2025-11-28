<?php
include 'config.php';

// Vérifier si l'utilisateur est connecté avant de journaliser
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    // Journalisation de la déconnexion
    Logger::logSecurityEvent("Déconnexion utilisateur", $_SESSION['user_id']);
}

// Stocker les informations pour un éventuel message
$was_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$username = $_SESSION['username'] ?? '';

// Réinitialiser toutes les variables de session
$_SESSION = array();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Redirection avec message de confirmation
if ($was_logged_in) {
    header("Location: index.php?message=logout_success&user=" . urlencode($username));
} else {
    header("Location: index.php");
}
exit();
?>