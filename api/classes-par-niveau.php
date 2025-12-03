<?php
include '../config.php';
header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$niveau = $_GET['niveau'] ?? '';

$query = "SELECT id, nom, niveau, filiere FROM classe WHERE niveau = :niveau ORDER BY nom";
$stmt = $db->prepare($query);
$stmt->bindParam(':niveau', $niveau);
$stmt->execute();
$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($classes);
?>