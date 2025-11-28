<?php
// Protection contre l'accès direct
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    exit('Accès direct interdit');
}

class Database {
    private $host = "localhost";
    private $db_name = "gestion_finance_ecole";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                $this->username, 
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $exception) {
            error_log("Erreur de connexion BD: " . $exception->getMessage());
            throw new Exception("Erreur de connexion à la base de données");
        }
        return $this->conn;
    }
}

class CSRF {
    public static function generateToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

class Validator {
    public static function validateEmail($email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return filter_var($email, FILTER_SANITIZE_EMAIL);
        }
        return false;
    }
    
    public static function validateText($text, $maxLength = 255) {
        if ($text === null || $text === '') {
            return $text;
        }
        $text = trim($text);
        $text = strip_tags($text);
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        if ($maxLength > 0 && strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength);
        }
        
        return $text;
    }
    
    public static function validateNumber($number, $min = null, $max = null) {
        if (!is_numeric($number)) return 0;
        
        $number = (int)$number;
        
        if ($min !== null && $number < $min) return $min;
        if ($max !== null && $number > $max) return $max;
        
        return $number;
    }
}

class Logger {
    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        // Créer le dossier logs s'il n'existe pas
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Écrire dans un fichier de log sécurisé
        file_put_contents($logDir . '/app.log', $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public static function logSecurityEvent($event, $user = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $userId = $user ?? $_SESSION['user_id'] ?? 'anonymous';
        
        $message = "Security Event: $event - User: $userId - IP: $ip - User-Agent: $userAgent";
        self::log($message, 'SECURITY');
    }
}

// Configuration des sessions sécurisées
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Mettre à 1 si HTTPS
ini_set('session.use_strict_mode', 1);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Régénérer l'ID de session périodiquement
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
?>