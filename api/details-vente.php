<?php
include '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$vente_id = $_GET['vente_id'] ?? 0;

if ($vente_id) {
    try {
        $query = "SELECT dv.*, a.nom as article_nom, a.prix as prix_unitaire
                  FROM details_ventes dv
                  JOIN articles a ON dv.article_id = a.id
                  WHERE dv.vente_id = :vente_id
                  ORDER BY a.nom";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':vente_id', $vente_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'details' => $details
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'ID de vente non spécifié'
    ]);
}
?>