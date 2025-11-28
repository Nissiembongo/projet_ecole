<?php
include 'config.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Récupérer la liste des classes pour le filtre
$query_classes = "SELECT * FROM classe ORDER BY niveau, nom";
$stmt_classes = $db->prepare($query_classes);
$stmt_classes->execute();
$classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les années scolaires disponibles
$query_annees = "SELECT DISTINCT annee_scolaire FROM classe ORDER BY annee_scolaire DESC";
$stmt_annees = $db->prepare($query_annees);
$stmt_annees->execute();
$annees_scolaires = $stmt_annees->fetchAll(PDO::FETCH_ASSOC);

// Récupérer et valider les filtres
$filtre_classe = Validator::validateNumber($_GET['classe'] ?? 0) ?: '';
$filtre_annee_scolaire = Validator::validateText($_GET['annee_scolaire'] ?? '');

// Si aucune année n'est sélectionnée, prendre la plus récente
if (empty($filtre_annee_scolaire) && !empty($annees_scolaires)) {
    $filtre_annee_scolaire = htmlspecialchars($annees_scolaires[0]['annee_scolaire']);
}

// Construire les conditions WHERE pour les requêtes
$where_conditions = [];
$params = [];

if (!empty($filtre_classe)) {
    $where_conditions[] = "e.classe_id = :classe_id";
    $params[':classe_id'] = $filtre_classe;
}

if (!empty($filtre_annee_scolaire)) {
    $where_conditions[] = "c.annee_scolaire = :annee_scolaire";
    $params[':annee_scolaire'] = $filtre_annee_scolaire;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Statistiques
$stats = [];

// Requête pour les étudiants
try {
    $query_etudiants = "SELECT COUNT(*) as total FROM etudiants e 
                       LEFT JOIN classe c ON e.classe_id = c.id";
    if (!empty($where_conditions)) {
        $query_etudiants .= " WHERE " . implode(" AND ", $where_conditions);
    }
    $stmt_etudiants = $db->prepare($query_etudiants);
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt_etudiants->bindValue($key, $value);
        }
    }
    $stmt_etudiants->execute();
    $stats['etudiants'] = $stmt_etudiants->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    error_log("Erreur statistiques étudiants: " . $e->getMessage());
    $stats['etudiants'] = 0;
}

// Requête pour les paiements totaux
try {
    $query_paiements = "SELECT SUM(p.montant_paye) as total 
                       FROM paiements p 
                       JOIN etudiants e ON p.etudiant_id = e.id 
                       LEFT JOIN classe c ON e.classe_id = c.id 
                       WHERE p.statut = 'payé'";
    if (!empty($where_conditions)) {
        $query_paiements .= " AND " . implode(" AND ", $where_conditions);
    }
    $stmt_paiements = $db->prepare($query_paiements);
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt_paiements->bindValue($key, $value);
        }
    }
    $stmt_paiements->execute();
    $stats['paiements'] = $stmt_paiements->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    error_log("Erreur statistiques paiements: " . $e->getMessage());
    $stats['paiements'] = 0;
}

// Requête pour les frais (pas de filtre par classe/année)
try {
    $query_frais = "SELECT COUNT(*) as total FROM frais WHERE statut = 'actif'";
    $stmt_frais = $db->prepare($query_frais);
    $stmt_frais->execute();
    $stats['frais'] = $stmt_frais->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    error_log("Erreur statistiques frais: " . $e->getMessage());
    $stats['frais'] = 0;
}

// Requête pour les paiements du mois (avec année scolaire)
try {
    $query_paiements_mois = "SELECT SUM(p.montant_paye) as total 
                            FROM paiements p 
                            JOIN etudiants e ON p.etudiant_id = e.id 
                            LEFT JOIN classe c ON e.classe_id = c.id 
                            WHERE MONTH(p.date_paiement) = MONTH(CURDATE()) 
                            AND YEAR(p.date_paiement) = YEAR(CURDATE())
                            AND p.statut = 'payé'";
    if (!empty($where_conditions)) {
        $query_paiements_mois .= " AND " . implode(" AND ", $where_conditions);
    }
    $stmt_paiements_mois = $db->prepare($query_paiements_mois);
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt_paiements_mois->bindValue($key, $value);
        }
    }
    $stmt_paiements_mois->execute();
    $stats['paiements_mois'] = $stmt_paiements_mois->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    error_log("Erreur statistiques paiements mois: " . $e->getMessage());
    $stats['paiements_mois'] = 0;
}

// Derniers paiements
try {
    $query_paiements_recent = "SELECT p.*, e.nom, e.prenom, e.matricule, c.nom as classe_nom, f.type_frais 
                       FROM paiements p 
                       JOIN etudiants e ON p.etudiant_id = e.id 
                       LEFT JOIN classe c ON e.classe_id = c.id 
                       JOIN frais f ON p.frais_id = f.id 
                       WHERE p.statut = 'payé'";
    if (!empty($where_conditions)) {
        $query_paiements_recent .= " AND " . implode(" AND ", $where_conditions);
    }
    $query_paiements_recent .= " ORDER BY p.date_paiement DESC LIMIT 6";

    $stmt_paiements_recent = $db->prepare($query_paiements_recent);
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt_paiements_recent->bindValue($key, $value);
        }
    }
    $stmt_paiements_recent->execute();
    $paiements = $stmt_paiements_recent->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur derniers paiements: " . $e->getMessage());
    $paiements = [];
}

// Graphique des paiements par mois (avec année scolaire)
try {
    $query_graph = "SELECT MONTH(p.date_paiement) as mois, SUM(p.montant_paye) as total 
                   FROM paiements p 
                   JOIN etudiants e ON p.etudiant_id = e.id 
                   LEFT JOIN classe c ON e.classe_id = c.id 
                   WHERE YEAR(p.date_paiement) = YEAR(CURDATE()) 
                   AND p.statut = 'payé'";
    if (!empty($where_conditions)) {
        $query_graph .= " AND " . implode(" AND ", $where_conditions);
    }
    $query_graph .= " GROUP BY MONTH(p.date_paiement) ORDER BY mois";

    $stmt_graph = $db->prepare($query_graph);
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $stmt_graph->bindValue($key, $value);
        }
    }
    $stmt_graph->execute();
    $data_graph = $stmt_graph->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur graphique paiements: " . $e->getMessage());
    $data_graph = [];
}

// Statistiques par classe (pour le graphique circulaire) avec année scolaire
try {
    $query_stats_classes = "SELECT c.nom as classe_nom, COUNT(e.id) as nb_etudiants, 
                                   COALESCE(SUM(p.montant_paye), 0) as total_paiements
                            FROM classe c 
                            LEFT JOIN etudiants e ON c.id = e.classe_id 
                            LEFT JOIN paiements p ON e.id = p.etudiant_id AND p.statut = 'payé'";
    $params_classes = [];
    if (!empty($filtre_annee_scolaire)) {
        $query_stats_classes .= " WHERE c.annee_scolaire = :annee_scolaire";
        $params_classes[':annee_scolaire'] = $filtre_annee_scolaire;
    }
    $query_stats_classes .= " GROUP BY c.id, c.nom 
                            ORDER BY total_paiements DESC 
                            LIMIT 8";

    $stmt_stats_classes = $db->prepare($query_stats_classes);
    foreach ($params_classes as $key => $value) {
        $stmt_stats_classes->bindValue($key, $value);
    }
    $stmt_stats_classes->execute();
    $stats_classes = $stmt_stats_classes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur statistiques classes: " . $e->getMessage());
    $stats_classes = [];
}

// Récupérer le nom de la classe filtrée pour l'affichage
$classe_filtree_nom = '';
if (!empty($filtre_classe)) {
    try {
        $query_classe_filtree = "SELECT nom FROM classe WHERE id = :classe_id";
        $stmt_classe_filtree = $db->prepare($query_classe_filtree);
        $stmt_classe_filtree->bindParam(':classe_id', $filtre_classe, PDO::PARAM_INT);
        $stmt_classe_filtree->execute();
        $classe_filtree = $stmt_classe_filtree->fetch(PDO::FETCH_ASSOC);
        $classe_filtree_nom = htmlspecialchars($classe_filtree['nom'] ?? '');
    } catch (PDOException $e) {
        error_log("Erreur récupération nom classe: " . $e->getMessage());
        $classe_filtree_nom = '';
    }
}
?>

<?php 
$page_title = "Tableau de Bord";
include 'layout.php'; 
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item active" aria-current="page"><i class="bi bi-house"></i> Tableau de Bord</li>
    </ol>
</nav>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h6 class="card-title mb-0"><i class="bi bi-funnel"></i> Filtres</h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
            <div class="col-md-3">
                <label for="filtre_annee_scolaire" class="form-label">Année Scolaire</label>
                <select class="form-control" id="filtre_annee_scolaire" name="annee_scolaire">
                    <?php foreach ($annees_scolaires as $annee): ?>
                    <option value="<?php echo htmlspecialchars($annee['annee_scolaire']); ?>" 
                        <?php echo ($filtre_annee_scolaire == $annee['annee_scolaire']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($annee['annee_scolaire']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="filtre_classe" class="form-label">Classe</label>
                <select class="form-control" id="filtre_classe" name="classe">
                    <option value="">Toutes les classes</option>
                    <?php 
                    // Filtrer les classes par année scolaire sélectionnée
                    $classes_filtrees = $classes;
                    if (!empty($filtre_annee_scolaire)) {
                        $classes_filtrees = array_filter($classes, function($classe) use ($filtre_annee_scolaire) {
                            return $classe['annee_scolaire'] == $filtre_annee_scolaire;
                        });
                    }
                    ?>
                    <?php foreach ($classes_filtrees as $classe): ?>
                    <option value="<?php echo $classe['id']; ?>" <?php echo ($filtre_classe == $classe['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($classe['nom'] . ' - ' . $classe['niveau']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-filter"></i> Appliquer
                </button>
            </div>
            <div class="col-md-4 text-end">
                <?php if (!empty($filtre_classe) || !empty($filtre_annee_scolaire)): ?>
                <span class="badge bg-info fs-6">
                    <i class="bi bi-info-circle"></i> Filtres actifs :
                    <?php 
                    $filtres_actifs = [];
                    if (!empty($filtre_annee_scolaire)) {
                        $filtres_actifs[] = "Année: " . htmlspecialchars($filtre_annee_scolaire);
                    }
                    if (!empty($filtre_classe)) {
                        $filtres_actifs[] = "Classe: " . $classe_filtree_nom;
                    }
                    echo htmlspecialchars(implode(' | ', $filtres_actifs));
                    ?>
                </span>
                <a href="dashboard.php" class="btn btn-sm btn-outline-secondary ms-2">
                    <i class="bi bi-x-circle"></i> Effacer
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Cartes de statistiques -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-start border-primary border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title text-primary">Élèves</h5>
                        <h2 class="text-primary"><?php echo htmlspecialchars($stats['etudiants']); ?></h2>
                        <p class="card-text">
                            <?php 
                            if (!empty($filtre_classe)) {
                                echo 'Dans la classe';
                            } elseif (!empty($filtre_annee_scolaire)) {
                                echo "Année " . htmlspecialchars($filtre_annee_scolaire);
                            } else {
                                echo 'Total inscrits';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-people display-4 text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-start border-success border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title text-success">Totaux</h5>
                        <h2 class="text-success"><?php echo htmlspecialchars(number_format($stats['paiements'], 0, ',', ' ')); ?> Kwz</h2>
                        <p class="card-text">
                            <?php 
                            if (!empty($filtre_classe)) {
                                echo 'Collectés classe';
                            } elseif (!empty($filtre_annee_scolaire)) {
                                echo "Année " . htmlspecialchars($filtre_annee_scolaire);
                            } else {
                                echo 'Le Cumul';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-cash-coin display-4 text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-start border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title text-warning">Types de Frais</h5>
                        <h2 class="text-warning"><?php echo htmlspecialchars($stats['frais']); ?></h2>
                        <p class="card-text">Catégories</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-list-ul display-4 text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card border-start border-info border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h5 class="card-title text-info">Ce Mois</h5>
                        <h2 class="text-info"><?php echo htmlspecialchars(number_format($stats['paiements_mois'], 0, ',', ' ')); ?> Kwz</h2>
                        <p class="card-text">
                            <?php 
                            if (!empty($filtre_classe)) {
                                echo 'Mensuels classe';
                            } elseif (!empty($filtre_annee_scolaire)) {
                                echo "Mensuels " . htmlspecialchars($filtre_annee_scolaire);
                            } else {
                                echo 'Mensuels';
                            }
                            ?>
                        </p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-calendar-month display-4 text-info opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques et Derniers paiements -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bar-chart"></i> Paiements par Mois (<?php echo htmlspecialchars(date('Y')); ?>)
                    <?php if (!empty($filtre_annee_scolaire)): ?>
                    <span class="badge bg-info ms-2"><?php echo htmlspecialchars($filtre_annee_scolaire); ?></span>
                    <?php endif; ?>
                </h5>
                <?php if (!empty($filtre_classe)): ?>
                <span class="badge bg-primary">Filtré</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <canvas id="paiementsChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pie-chart"></i> Répartition par Classe
                    <?php if (!empty($filtre_annee_scolaire)): ?>
                    <span class="badge bg-info ms-2"><?php echo htmlspecialchars($filtre_annee_scolaire); ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <canvas id="classesChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-clock-history"></i> Derniers Paiements</h5>
                <?php if (!empty($filtre_classe) || !empty($filtre_annee_scolaire)): ?>
                <span class="badge bg-primary">Filtré</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (count($paiements) > 0): ?>
                        <?php foreach ($paiements as $paiement): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($paiement['nom'] . ' ' . $paiement['prenom']); ?></h6>
                                <small class="text-success"><?php echo htmlspecialchars(number_format($paiement['montant_paye'], 0, ',', ' ')); ?> Kwz</small>
                            </div>
                            <p class="mb-1 small"><?php echo htmlspecialchars($paiement['type_frais']); ?></p>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted"><?php echo htmlspecialchars(date('d/m/Y', strtotime($paiement['date_paiement']))); ?></small>
                                <small class="text-info"><?php echo htmlspecialchars($paiement['classe_nom'] ?? '-'); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="bi bi-credit-card display-4"></i>
                            <p class="mt-2 mb-0">Aucun paiement</p>
                            <?php if (!empty($filtre_classe) || !empty($filtre_annee_scolaire)): ?>
                            <small class="text-muted">Aucun paiement trouvé avec les filtres actuels</small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-lightning"></i> Actions Rapides</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="etudiants.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-person-plus"></i><br>
                            Nouvel Étudiant
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="paiements.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-credit-card"></i><br>
                            Enregistrer Paiement
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="caisse.php" class="btn btn-outline-warning w-100">
                            <i class="bi bi-cash-stack"></i><br>
                            Gérer Caisse
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="rapports.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-file-earmark-text"></i><br>
                            Générer Rapport
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layout-end.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique des paiements par mois
const ctx = document.getElementById('paiementsChart').getContext('2d');
const paiementsChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
        datasets: [{
            label: 'Paiements (Kwz)',
            data: [<?php
                $monthly_data = array_fill(0, 12, 0);
                foreach ($data_graph as $data) {
                    $month_index = intval($data['mois']) - 1;
                    if ($month_index >= 0 && $month_index < 12) {
                        $monthly_data[$month_index] = floatval($data['total']);
                    }
                }
                echo implode(', ', $monthly_data);
            ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.8)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + ' Kwz';
                    }
                }
            }
        }
    }
});

// Graphique circulaire des classes
const ctxClasses = document.getElementById('classesChart').getContext('2d');
const classesChart = new Chart(ctxClasses, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            $labels = [];
            foreach ($stats_classes as $c) {
                $labels[] = "'" . addslashes(htmlspecialchars($c['classe_nom'])) . "'";
            }
            echo implode(', ', $labels);
        ?>],
        datasets: [{
            data: [<?php 
                $data = [];
                foreach ($stats_classes as $c) {
                    $data[] = floatval($c['total_paiements']);
                }
                echo implode(', ', $data);
            ?>],
            backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    font: {
                        size: 10
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.parsed || 0;
                        return label + ': ' + value.toLocaleString() + ' Kwz';
                    }
                }
            }
        }
    }
});

// Mettre à jour la liste des classes quand l'année scolaire change
document.getElementById('filtre_annee_scolaire').addEventListener('change', function() {
    // Le formulaire se soumet automatiquement
});

// Protection contre les injections XSS dans les données dynamiques
function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>