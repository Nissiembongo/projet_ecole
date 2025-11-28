<?php
class Auth {
    public static function login($username, $password) {
        // Validation des entrées
        $username = trim($username);
        $password = trim($password);
        
        if (empty($username) || empty($password)) {
            return false;
        }
        
        $db = new Database();
        $conn = $db->getConnection();
        
       $query = "SELECT id, username, password, role, nom_complet, statut, derniere_connexion 
                      FROM utilisateurs 
                      WHERE username = :username";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($user = $stmt->fetch()) {
            // Vérification du mot de passe hashé
            if (password_verify($password, $user['password']) && $user['statut']) {
                // Régénération de session après connexion
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time();
                
                return true;
            }
        }
        
        return false;
    }
    
    public static function checkAuth() {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            header('Location: login.php');
            exit;
        }
        
        // Vérification d'inactivité (30 minutes)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            self::logout();
            header('Location: login.php?timeout=1');
            exit;
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    public static function logout() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
}
?>