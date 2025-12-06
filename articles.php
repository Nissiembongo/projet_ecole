<?php
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = $error = '';

// Opérations CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['ajouter_article'])) {
        // Ajouter un article
        $nom = $_POST['nom'] ?? '';
        $description = $_POST['description'] ?? '';
        $prix = $_POST['prix'] ?? 0;
        $quantite_stock = $_POST['quantite_stock'] ?? 0;
        $seuil_alerte = $_POST['seuil_alerte'] ?? 10;
        $est_actif = isset($_POST['est_actif']) ? 1 : 0;
        
        try {
            $query = "INSERT INTO articles (nom, description, prix, quantite_stock, seuil_alerte, est_actif) 
                      VALUES (:nom, :description, :prix, :quantite_stock, :seuil_alerte, :est_actif)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':prix', $prix);
            $stmt->bindParam(':quantite_stock', $quantite_stock);
            $stmt->bindParam(':seuil_alerte', $seuil_alerte);
            $stmt->bindParam(':est_actif', $est_actif);
            
            if ($stmt->execute()) {
                $success = "Article ajouté avec succès!";
            }
        } catch (PDOException $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['modifier_article'])) {
        // Modifier un article
        $id = $_POST['id'] ?? 0;
        $nom = $_POST['nom'] ?? '';
        $description = $_POST['description'] ?? '';
        $prix = $_POST['prix'] ?? 0;
        $quantite_stock = $_POST['quantite_stock'] ?? 0;
        $seuil_alerte = $_POST['seuil_alerte'] ?? 10;
        $est_actif = isset($_POST['est_actif']) ? 1 : 0;
        
        try {
            $query = "UPDATE articles 
                      SET nom = :nom, 
                          description = :description, 
                          prix = :prix, 
                          quantite_stock = :quantite_stock, 
                          seuil_alerte = :seuil_alerte, 
                          est_actif = :est_actif,
                          updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':nom', $nom);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':prix', $prix);
            $stmt->bindParam(':quantite_stock', $quantite_stock);
            $stmt->bindParam(':seuil_alerte', $seuil_alerte);
            $stmt->bindParam(':est_actif', $est_actif);
            
            if ($stmt->execute()) {
                $success = "Article modifié avec succès!";
            }
        } catch (PDOException $e) {
            $error = "Erreur: " . $e->getMessage();
        }
    }
}

// Supprimer un article
if (isset($_GET['supprimer'])) {
    $id = $_GET['supprimer'];
    
    try {
        // Vérifier si l'article est utilisé dans des ventes
        $query_check = "SELECT COUNT(*) as count FROM details_ventes WHERE article_id = :id";
        $stmt_check = $db->prepare($query_check);
        $stmt_check->bindParam(':id', $id);
        $stmt_check->execute();
        $result = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            $error = "Impossible de supprimer cet article car il est utilisé dans des ventes!";
        } else {
            $query = "DELETE FROM articles WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $success = "Article supprimé avec succès!";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer tous les articles
$query = "SELECT * FROM articles ORDER BY nom";
$stmt = $db->prepare($query);
$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$query_stats = "SELECT 
    COUNT(*) as total_articles,
    SUM(quantite_stock) as total_stock,
    COUNT(CASE WHEN quantite_stock <= seuil_alerte THEN 1 END) as articles_alerte,
    COUNT(CASE WHEN est_actif = 1 THEN 1 END) as articles_actifs
FROM articles";
$stmt_stats = $db->prepare($query_stats);
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

$page_title = "Gestion des Articles";
include 'layout.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- En-tête avec bouton -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-box-seam me-2"></i>Gestion des Articles</h2>
                <div>
                    <span class="badge bg-info me-2">
                        Total: <?php echo $stats['total_articles'] ?? 0; ?> articles
                    </span>
                    <span class="badge bg-warning me-2">
                        Stock bas: <?php echo $stats['articles_alerte'] ?? 0; ?>
                    </span>
                    <span class="badge bg-success me-2">
                        Actifs: <?php echo $stats['articles_actifs'] ?? 0; ?>
                    </span>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterArticleModal">
                        <i class="bi bi-plus-circle"></i> Nouvel Article
                    </button>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?php echo $stats['total_articles'] ?? 0; ?></h4>
                                    <small>Total Articles</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-box fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?php echo $stats['total_stock'] ?? 0; ?></h4>
                                    <small>Stock Total</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-boxes fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?php echo $stats['articles_alerte'] ?? 0; ?></h4>
                                    <small>Stock à Réapprovisionner</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-exclamation-triangle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h4 class="mb-0"><?php echo $stats['articles_actifs'] ?? 0; ?></h4>
                                    <small>Articles Actifs</small>
                                </div>
                                <div class="align-self-center">
                                    <i class="bi bi-check-circle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des articles -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-list-ul"></i> Liste des Articles</h5>
                    <span class="badge bg-primary"><?php echo count($articles); ?> article(s)</span>
                </div>
                <div class="card-body">
                    <?php if (count($articles) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped" id="tableArticles">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nom</th>
                                        <th>Description</th>
                                        <th class="text-end">Prix</th>
                                        <th class="text-end">Stock</th>
                                        <th>Seuil</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($articles as $article): 
                                        $stock_bas = $article['quantite_stock'] <= $article['seuil_alerte'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($article['nom']); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo !empty($article['description']) ? htmlspecialchars(substr($article['description'], 0, 50)) . '...' : '-'; ?>
                                            </small>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-success">
                                                <?php echo number_format($article['prix'], 0, ',', ' '); ?> Kwz
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <span class="badge bg-<?php echo $stock_bas ? 'warning' : 'info'; ?>">
                                                <?php echo $article['quantite_stock']; ?>
                                            </span>
                                            <?php if ($stock_bas): ?>
                                            <br><small class="text-danger">Stock bas!</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo $article['seuil_alerte']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $article['est_actif'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $article['est_actif'] ? 'Actif' : 'Inactif'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-warning" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#modifierArticleModal"
                                                        onclick="chargerArticle(<?php echo $article['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <a href="?supprimer=<?php echo $article['id']; ?>" 
                                                   class="btn btn-danger"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-box display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">Aucun article enregistré</h4>
                            <p class="text-muted">Commencez par ajouter le premier article.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterArticleModal">
                                <i class="bi bi-plus-circle"></i> Ajouter le premier article
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter Article -->
<div class="modal fade" id="ajouterArticleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Ajouter un Article</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="form-ajouter-article">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom de l'article <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" maxlength="500"></textarea>
                        <small class="text-muted">Maximum 500 caractères</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="prix" class="form-label">Prix (Kwz) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="prix" name="prix" step="0.01" min="0" required>
                                    <span class="input-group-text">Kwz</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quantite_stock" class="form-label">Quantité en stock</label>
                                <input type="number" class="form-control" id="quantite_stock" name="quantite_stock" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="seuil_alerte" class="form-label">Seuil d'alerte</label>
                                <input type="number" class="form-control" id="seuil_alerte" name="seuil_alerte" min="1" value="10">
                                <small class="text-muted">Alerte quand le stock est inférieur ou égal à cette valeur</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" checked>
                                    <label class="form-check-label" for="est_actif">Article actif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="ajouter_article" class="btn btn-primary">
                        <i class="bi bi-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier Article -->
<div class="modal fade" id="modifierArticleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Modifier l'Article</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="form-modifier-article">
                <input type="hidden" id="modifier_id" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modifier_nom" class="form-label">Nom de l'article <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modifier_nom" name="nom" required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label for="modifier_description" class="form-label">Description</label>
                        <textarea class="form-control" id="modifier_description" name="description" rows="3" maxlength="500"></textarea>
                        <small class="text-muted">Maximum 500 caractères</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modifier_prix" class="form-label">Prix (Kwz) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="modifier_prix" name="prix" step="0.01" min="0" required>
                                    <span class="input-group-text">Kwz</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modifier_quantite_stock" class="form-label">Quantité en stock</label>
                                <input type="number" class="form-control" id="modifier_quantite_stock" name="quantite_stock" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modifier_seuil_alerte" class="form-label">Seuil d'alerte</label>
                                <input type="number" class="form-control" id="modifier_seuil_alerte" name="seuil_alerte" min="1" value="10">
                                <small class="text-muted">Alerte quand le stock est inférieur ou égal à cette valeur</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" id="modifier_est_actif" name="est_actif">
                                    <label class="form-check-label" for="modifier_est_actif">Article actif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="modifier_article" class="btn btn-warning">
                        <i class="bi bi-save"></i> Modifier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'layout-end.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Activation des tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Fonction pour charger les données d'un article dans le modal de modification
function chargerArticle(id) {
    fetch(`api/get_article.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const article = data.article;
                document.getElementById('modifier_id').value = article.id;
                document.getElementById('modifier_nom').value = article.nom;
                document.getElementById('modifier_description').value = article.description || '';
                document.getElementById('modifier_prix').value = article.prix;
                document.getElementById('modifier_quantite_stock').value = article.quantite_stock;
                document.getElementById('modifier_seuil_alerte').value = article.seuil_alerte;
                document.getElementById('modifier_est_actif').checked = article.est_actif == 1;
            } else {
                alert('Erreur lors du chargement de l\'article');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur de connexion');
        });
}

// Validation du formulaire d'ajout
document.getElementById('form-ajouter-article').addEventListener('submit', function(e) {
    const prix = document.getElementById('prix').value;
    if (parseFloat(prix) <= 0) {
        e.preventDefault();
        alert('Le prix doit être supérieur à 0');
        document.getElementById('prix').focus();
    }
});

// Validation du formulaire de modification
document.getElementById('form-modifier-article').addEventListener('submit', function(e) {
    const prix = document.getElementById('modifier_prix').value;
    if (parseFloat(prix) <= 0) {
        e.preventDefault();
        alert('Le prix doit être supérieur à 0');
        document.getElementById('modifier_prix').focus();
    }
});
</script>