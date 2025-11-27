<?php
include '../config.php';

$database = new Database();
$db = $database->getConnection();

$niveau = $_GET['niveau'] ?? '';

if (!empty($niveau)) {
    $query = "SELECT id, nom, niveau FROM classe WHERE niveau = :niveau ORDER BY nom";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':niveau', $niveau);
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($classes);
} else {
    echo json_encode([]);
}
?>