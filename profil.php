<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Ajouter un étudiant
if ($_POST && isset($_POST['ajouter_etudiant'])) {
    try {
        $matricule = $_POST['matricule'];
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $classe = $_POST['classe'];
        $telephone = $_POST['telephone'];
        $email = $_POST['email'];
        
        $query = "INSERT INTO etudiants (matricule, nom, prenom, classe, telephone, email, date_inscription) 
                  VALUES (:matricule, :nom, :prenom, :classe, :telephone, :email, CURDATE())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':matricule', $matricule);
        $stmt->bindParam(':nom', $nom);
        $stmt->bindParam(':prenom', $prenom);
        $stmt->bindParam(':classe', $classe);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->bindParam(':email', $email);
        
        if ($stmt->execute()) {
            $success = "Étudiant ajouté avec succès!";
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer le profile
$user_id = intval($_SESSION['user_id']);
$query = "SELECT * FROM utilisateurs WHERE id = $user_id";
$stmt = $db->prepare($query);
$stmt->execute();
$profil = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php 
$page_title = "Mon Profil";
include 'layout.php'; 
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="dashboard.php"><i class="bi bi-house"></i></a></li>
        <li class="breadcrumb-item active" aria-current="page">Profil</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-people me-2"></i>Mon Profil</h2>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modifUser">
        <i class="bi bi-pencil"></i> Modifier
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
 <div class="col-lg-6">
    <div class="card">
        <?php foreach ($profil as $prof): ?>
        <div class="card-header bg-white">
            <h3 class="card-title mb-0"><?php echo $prof['nom_complet']?></h3>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <div class="list-group-item">

                    <h1><i class="bi bi-person"></i></h1>

                    <div class="mt-3">
                        <div class="d-flex mb-2">
                            <div class="col-6"><h5 class="mb-0 me-2">Nom d'utilisateur </h5></div>
                            <div class="col-6"><h6 class="mb-0"><?php echo  ": ".htmlspecialchars($prof['username']); ?></h6></div>
                        </div>

                        <div class="d-flex">
                            <div class="col-6"><h5 class="mb-0">Rôl  </h5></div>
                            <div class="col-6"><h6 class="mb-0"><?php echo ": ".htmlspecialchars($prof['role']); ?></h6></div>
                            
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <?php endforeach; ?>
    </div>
</div>

<!-- Modal MODIFIER USER -->
<div class="modal fade" id="modifUser" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-person-plus"></i> Modifier le profil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php foreach ($profil as $prof): ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="username" value="<?php echo  htmlspecialchars($prof['username']); ?>" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nom_complet" class="form-label">Nom Complet *</label>
                            <input type="text" class="form-control" id="nom_complet" value="<?php echo  htmlspecialchars($prof['nom_complet']); ?>" name="nom_complet" required>
                        </div> 
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="ajouter_etudiant" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout-end.php'; ?>

<script>
// Activation des tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});
</script>