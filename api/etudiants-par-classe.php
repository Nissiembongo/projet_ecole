<?php
include '../config.php';

header('Content-Type: application/json');

if (isset($_GET['classe_id'])) {
    $classe_id = intval($_GET['classe_id']);
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Récupérer les étudiants de la classe
    $query = "SELECT id, matricule, nom, prenom FROM etudiants WHERE classe_id = ? ORDER BY nom, prenom";
    $stmt = $db->prepare($query);
    $stmt->execute([$classe_id]);
    $etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($etudiants);
} else {
    echo json_encode([]);
}
?>