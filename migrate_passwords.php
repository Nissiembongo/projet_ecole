<?php
// migrate_passwords.php - À exécuter une seule fois
include 'config.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Récupérer tous les utilisateurs avec des mots de passe MD5
    $query = "SELECT id, password FROM utilisateurs WHERE LENGTH(password) = 32 AND password REGEXP '^[a-f0-9]{32}$'";
    $stmt = $db->query($query);
    
    $updated = 0;
    
    while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Le mot de passe est déjà en MD5, on le hache avec Bcrypt
        $hashedPassword = password_hash($user['password'], PASSWORD_BCRYPT);
        
        $updateQuery = "UPDATE utilisateurs SET password = :password WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([':password' => $hashedPassword, ':id' => $user['id']]);
        
        $updated++;
    }
    
    echo "{$updated} mots de passe migrés de MD5 vers Bcrypt avec succès.";
    
} catch (Exception $e) {
    echo "Erreur lors de la migration: " . $e->getMessage();
}
?>