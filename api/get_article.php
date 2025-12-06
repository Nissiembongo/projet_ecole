<?php
include '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$article_id = $_GET['id'] ?? 0;

if ($article_id) {
    try {
        $query = "SELECT * FROM articles WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $article_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $article = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($article) {
            echo json_encode([
                'success' => true,
                'article' => $article
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Article non trouvé'
            ]);
        }
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'ID d\'article non spécifié'
    ]);
}
?>