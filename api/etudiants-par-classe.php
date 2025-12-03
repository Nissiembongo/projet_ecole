<?php
include '../config.php';
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$classe_id = $_GET['classe_id'] ?? '';

$query = "SELECT e.id, e.matricule, e.nom, e.prenom, e.telephone, e.email, 
                 c.nom as classe_nom, c.niveau as classe_niveau, c.filiere as classe_filiere 
          FROM etudiants e 
          LEFT JOIN classe c ON e.classe_id = c.id 
          WHERE e.classe_id = :classe_id 
          ORDER BY e.nom, e.prenom";
$stmt = $db->prepare($query);
$stmt->bindParam(':classe_id', $classe_id);
$stmt->execute();
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($etudiants);
?>