<?php
include 'config.php';
include_once 'auth.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = $error = ''; 

// Récupérer la liste des classes avec filière
$query_classes = "SELECT id, nom, niveau, filiere FROM classe ORDER BY niveau, nom";
$stmt_classes = $db->prepare($query_classes);
$stmt_classes->execute();
$classes = $stmt_classes->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des niveaux distincts
$query_niveaux = "SELECT DISTINCT niveau FROM classe ORDER BY niveau";
$stmt_niveaux = $db->prepare($query_niveaux);
$stmt_niveaux->execute();
$niveaux = $stmt_niveaux->fetchAll(PDO::FETCH_COLUMN);

// Récupérer la liste des articles avec stock
$query_article = "SELECT *, quantite_stock as stock_disponible FROM articles ORDER BY nom";
$stmt_article = $db->prepare($query_article);
$stmt_article->execute();
$article_list = $stmt_article->fetchAll(PDO::FETCH_ASSOC);

// Filtrer les classes par niveau si un niveau est sélectionné dans les filtres
$filtre_niveau = $_GET['niveau'] ?? '';
$classes_filtrees = $classes;
if (!empty($filtre_niveau)) {
    $classes_filtrees = array_filter($classes, function($classe) use ($filtre_niveau) {
        return $classe['niveau'] == $filtre_niveau;
    });
}

// Fonction pour vérifier la disponibilité du stock - CORRIGÉE
function verifierStockDisponible($db, $articles, $quantites) {
    $erreurs = [];
    $articles_details = [];
    
    foreach ($articles as $index => $article_id) {
        if (!empty($article_id) && isset($quantites[$index]) && $quantites[$index] > 0) {
            $quantite_demande = (int)$quantites[$index];
            
            // Vérifier le stock disponible - CORRECTION : ajouter seuil_alerte
            $query_stock = "SELECT id, nom, prix, quantite_stock, seuil_alerte FROM articles WHERE id = :article_id";
            $stmt_stock = $db->prepare($query_stock);
            $stmt_stock->bindParam(':article_id', $article_id);
            $stmt_stock->execute();
            $article = $stmt_stock->fetch(PDO::FETCH_ASSOC);
            
            if ($article) {
                if ($article['quantite_stock'] < $quantite_demande) {
                    $erreurs[] = "Stock insuffisant pour '{$article['nom']}'. Disponible: {$article['quantite_stock']}, Demandé: {$quantite_demande}";
                } else {
                    $articles_details[] = [
                        'id' => $article_id,
                        'nom' => $article['nom'],
                        'prix' => $article['prix'],
                        'quantite' => $quantite_demande,
                        'stock_disponible' => $article['quantite_stock'],
                        'seuil_alerte' => $article['seuil_alerte'],
                        'sous_total' => $article['prix'] * $quantite_demande
                    ];
                }
            } else {
                $erreurs[] = "Article ID {$article_id} introuvable";
            }
        }
    }
    
    return ['erreurs' => $erreurs, 'articles_details' => $articles_details];
}

// Enregistrer une vente
if ($_POST && isset($_POST['enregistrer_vente'])) {
    try {
        $etudiant_id = $_POST['etudiant_id'];
        $date_vente = $_POST['date_vente'];
        $mode_vente = $_POST['mode_vente'];
        $reference = !empty($_POST['reference']) ? $_POST['reference'] : null;
        $statut = 'payé';
        $utilisateur_id = $_SESSION['user_id'];
        
        // Récupérer les articles sélectionnés avec leurs quantités
        $articles = $_POST['articles'] ?? [];
        $quantites = $_POST['quantites'] ?? [];
        
        if (empty($articles)) {
            $error = "Veuillez sélectionner au moins un article!";
        } else {
            // Vérifier si l'étudiant existe
            $query_check_etudiant = "SELECT id FROM etudiants WHERE id = :etudiant_id";
            $stmt_check_etudiant = $db->prepare($query_check_etudiant);
            $stmt_check_etudiant->bindParam(':etudiant_id', $etudiant_id);
            $stmt_check_etudiant->execute();
            
            if ($stmt_check_etudiant->rowCount() == 0) {
                $error = "Étudiant sélectionné introuvable!";
            } else {
                // Vérifier la disponibilité du stock avant de continuer
                $verification_stock = verifierStockDisponible($db, $articles, $quantites);
                
                if (!empty($verification_stock['erreurs'])) {
                    $error = "Problèmes de stock:<br>" . implode("<br>", $verification_stock['erreurs']);
                } else {
                    $articles_details = $verification_stock['articles_details'];
                    
                    // Calculer le montant total
                    $montant_total = array_sum(array_column($articles_details, 'sous_total'));
                    
                    if ($montant_total <= 0) {
                        $error = "Le montant total doit être supérieur à 0!";
                    } else {
                        // Commencer une transaction
                        $db->beginTransaction();
                        
                        try {
                            // 1. Enregistrer la vente principale avec utilisateur_id - CORRIGÉ
                            $query_vente = "INSERT INTO ventes (etudiant_id, montant_total, date_vente, mode_vente, reference, statut, utilisateur_id, created_at, updated_at) 
                                            VALUES (:etudiant_id, :montant_total, :date_vente, :mode_vente, :reference, :statut, :utilisateur_id, NOW(), NOW())";
                            
                            $stmt_vente = $db->prepare($query_vente);
                            $stmt_vente->bindParam(':etudiant_id', $etudiant_id);
                            $stmt_vente->bindParam(':montant_total', $montant_total);
                            $stmt_vente->bindParam(':date_vente', $date_vente);
                            $stmt_vente->bindParam(':mode_vente', $mode_vente);
                            $stmt_vente->bindParam(':reference', $reference);
                            $stmt_vente->bindParam(':statut', $statut);
                            $stmt_vente->bindParam(':utilisateur_id', $utilisateur_id);
                            $stmt_vente->execute();
                            
                            $vente_id = $db->lastInsertId();
                            
                            // 2. Enregistrer les détails de la vente et mettre à jour le stock
                            foreach ($articles_details as $article_detail) {
                                // Enregistrer le détail de vente
                                $query_details = "INSERT INTO details_ventes (vente_id, article_id, quantite, prix_unitaire, sous_total, created_at) 
                                                VALUES (:vente_id, :article_id, :quantite, :prix_unitaire, :sous_total, NOW())";
                                $stmt_details = $db->prepare($query_details);
                                $stmt_details->bindParam(':vente_id', $vente_id);
                                $stmt_details->bindParam(':article_id', $article_detail['id']);
                                $stmt_details->bindParam(':quantite', $article_detail['quantite']);
                                $stmt_details->bindParam(':prix_unitaire', $article_detail['prix']);
                                $stmt_details->bindParam(':sous_total', $article_detail['sous_total']);
                                $stmt_details->execute();
                                
                                // Mettre à jour le stock de l'article - CORRECTION : pas de quantite_vendue
                                $query_update_stock = "UPDATE articles 
                                                    SET quantite_stock = quantite_stock - :quantite_vendue,
                                                        updated_at = NOW()
                                                    WHERE id = :article_id";
                                $stmt_update_stock = $db->prepare($query_update_stock);
                                $stmt_update_stock->bindParam(':quantite_vendue', $article_detail['quantite']);
                                $stmt_update_stock->bindParam(':article_id', $article_detail['id']);
                                $stmt_update_stock->execute();
                                
                                // Vérifier et enregistrer l'alerte de stock si nécessaire
                                $query_check_alert = "SELECT quantite_stock, seuil_alerte, nom FROM articles WHERE id = :article_id";
                                $stmt_check_alert = $db->prepare($query_check_alert);
                                $stmt_check_alert->bindParam(':article_id', $article_detail['id']);
                                $stmt_check_alert->execute();
                                $stock_info = $stmt_check_alert->fetch(PDO::FETCH_ASSOC);
                                
                                if ($stock_info && $stock_info['quantite_stock'] <= $stock_info['seuil_alerte']) {
                                    // Vérifier si une alerte existe déjà
                                    // $query_check_existing_alert = "SELECT id FROM alertes_stock WHERE article_id = :article_id AND statut = 'active'";
                                    // $stmt_check_existing = $db->prepare($query_check_existing_alert);
                                    // $stmt_check_existing->bindParam(':article_id', $article_detail['id']);
                                    // $stmt_check_existing->execute();
                                    
                                    // if ($stmt_check_existing->rowCount() == 0) {
                                    //     // Enregistrer l'alerte dans la table des alertes
                                    //     $query_insert_alerte = "INSERT INTO alertes_stock 
                                    //                             (article_id, quantite_actuelle, seuil_alerte, message, date_alerte, statut) 
                                    //                             VALUES (:article_id, :quantite_actuelle, :seuil_alerte, :message, NOW(), 'active')";
                                    //     $stmt_insert_alerte = $db->prepare($query_insert_alerte);
                                    //     $message = "Stock bas pour '{$stock_info['nom']}'. Stock actuel: {$stock_info['quantite_stock']}, Seuil: {$stock_info['seuil_alerte']}";
                                    //     $stmt_insert_alerte->bindParam(':article_id', $article_detail['id']);
                                    //     $stmt_insert_alerte->bindParam(':quantite_actuelle', $stock_info['quantite_stock']);
                                    //     $stmt_insert_alerte->bindParam(':seuil_alerte', $stock_info['seuil_alerte']);
                                    //     $stmt_insert_alerte->bindParam(':message', $message);
                                    //     $stmt_insert_alerte->execute();
                                    // }
                                }
                            }
                            
                            // 3. Enregistrer automatiquement en caisse
                            $query_info = "SELECT e.nom, e.prenom, e.matricule, c.nom as classe_nom, c.niveau as classe_niveau, c.filiere
                                           FROM etudiants e 
                                           LEFT JOIN classe c ON e.classe_id = c.id 
                                           WHERE e.id = :etudiant_id";
                            $stmt_info = $db->prepare($query_info);
                            $stmt_info->bindParam(':etudiant_id', $etudiant_id);
                            $stmt_info->execute();
                            $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                            
                            if ($info) {
                                // Construire la description avec les articles
                                $description = "Vente fournitures - " . $info['nom'] . " " . $info['prenom'] . 
                                              " (" . $info['matricule'] . ") - " . $info['classe_nom'] . " (Niv. " . $info['classe_niveau'];
                                if (!empty($info['filiere'])) {
                                    $description .= " - Filière: " . $info['filiere'];
                                }
                                $description .= ")";
                                
                                $categorie = 'Vente fournitures'; 
                                
                                // Vérifier si la table caisse a un champ utilisateur_id
                                $query_check_caisse = "SHOW COLUMNS FROM caisse LIKE 'utilisateur_id'";
                                $stmt_check_caisse = $db->prepare($query_check_caisse);
                                $stmt_check_caisse->execute();
                                $has_utilisateur_id = $stmt_check_caisse->rowCount() > 0;
                                
                                if ($has_utilisateur_id) {
                                    $query_caisse = "INSERT INTO caisse (type_operation, montant, date_operation, mode_operation, description, reference, categorie, utilisateur_id, vente_id) 
                                        VALUES ('dépôt', :montant, :date_operation, :mode_operation, :description, :reference, :categorie, :utilisateur_id, :vente_id)";
                                } else {
                                    $query_caisse = "INSERT INTO caisse (type_operation, montant, date_operation, mode_operation, description, reference, categorie, vente_id) 
                                        VALUES ('dépôt', :montant, :date_operation, :mode_operation, :description, :reference, :categorie, :vente_id)";
                                }
                                
                                $stmt_caisse = $db->prepare($query_caisse);
                                $stmt_caisse->bindParam(':montant', $montant_total);
                                $stmt_caisse->bindParam(':date_operation', $date_vente);
                                $stmt_caisse->bindParam(':mode_operation', $mode_vente);
                                $stmt_caisse->bindParam(':description', $description);
                                $stmt_caisse->bindParam(':reference', $reference);
                                $stmt_caisse->bindParam(':categorie', $categorie);
                                $stmt_caisse->bindParam(':vente_id', $vente_id);
                                if ($has_utilisateur_id) {
                                    $stmt_caisse->bindParam(':utilisateur_id', $utilisateur_id);
                                }
                                $stmt_caisse->execute();
                                
                                $operation_caisse_id = $db->lastInsertId();
                                
                                // Lier la vente à l'opération de caisse
                                $query_lier = "UPDATE ventes SET operation_caisse_id = :operation_caisse_id WHERE id = :vente_id";
                                $stmt_lier = $db->prepare($query_lier);
                                $stmt_lier->bindParam(':operation_caisse_id', $operation_caisse_id);
                                $stmt_lier->bindParam(':vente_id', $vente_id);
                                $stmt_lier->execute();
                                
                                // Mettre à jour le solde du jour
                                if (function_exists('mettreAJourSoldeJour')) {
                                    mettreAJourSoldeJour($db, $date_vente);
                                }
                                
                                // Valider la transaction
                                $db->commit();
                                
                                $success = "Vente enregistrée avec succès! " . count($articles_details) . " article(s) vendu(s). Le stock a été mis à jour et le dépôt a été automatiquement effectué en caisse.";
                                $_POST = array();
                            } else {
                                $db->rollBack();
                                $error = "Erreur lors de la récupération des informations de l'étudiant!";
                            }
                            
                        } catch (Exception $e) {
                            // Annuler la transaction en cas d'erreur
                            $db->rollBack();
                            throw $e;
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Modifier le statut d'une vente avec gestion du stock
if (isset($_GET['changer_statut'])) {
    try {
        $vente_id = $_GET['changer_statut'];
        $nouveau_statut = $_GET['statut'];
        $utilisateur_id = $_SESSION['user_id'];
        
        // Récupérer les infos de la vente
        $query_info_vente = "SELECT v.*, e.nom, e.prenom, e.matricule, c.nom as classe_nom, c.niveau as classe_niveau, c.filiere
                            FROM ventes v 
                            JOIN etudiants e ON v.etudiant_id = e.id 
                            LEFT JOIN classe c ON e.classe_id = c.id 
                            WHERE v.id = :id";
        $stmt_info_vente = $db->prepare($query_info_vente);
        $stmt_info_vente->bindParam(':id', $vente_id);
        $stmt_info_vente->execute();
        $vente_info = $stmt_info_vente->fetch(PDO::FETCH_ASSOC);
        
        if ($vente_info) {
            // Récupérer les détails de la vente
            $query_details_vente = "SELECT dv.*, a.nom as article_nom 
                                   FROM details_ventes dv 
                                   JOIN articles a ON dv.article_id = a.id 
                                   WHERE dv.vente_id = :vente_id";
            $stmt_details_vente = $db->prepare($query_details_vente);
            $stmt_details_vente->bindParam(':vente_id', $vente_id);
            $stmt_details_vente->execute();
            $details_vente = $stmt_details_vente->fetchAll(PDO::FETCH_ASSOC);
            
            // Commencer une transaction
            $db->beginTransaction();
            
            try {
                if ($nouveau_statut == 'annulé' && $vente_info['statut'] != 'annulé') {
                    // Si on annule une vente, remettre le stock - CORRECTION : pas de quantite_vendue
                    foreach ($details_vente as $detail) {
                        $query_restock = "UPDATE articles 
                                         SET quantite_stock = quantite_stock + :quantite,
                                             updated_at = NOW()
                                         WHERE id = :article_id";
                        $stmt_restock = $db->prepare($query_restock);
                        $stmt_restock->bindParam(':quantite', $detail['quantite']);
                        $stmt_restock->bindParam(':article_id', $detail['article_id']);
                        $stmt_restock->execute();
                        
                        // Vérifier si une alerte de stock doit être supprimée
                        $query_check_alert = "SELECT a.quantite_stock, a.seuil_alerte 
                                             FROM articles a 
                                             WHERE a.id = :article_id";
                        $stmt_check_alert = $db->prepare($query_check_alert);
                        $stmt_check_alert->bindParam(':article_id', $detail['article_id']);
                        $stmt_check_alert->execute();
                        $stock_info = $stmt_check_alert->fetch(PDO::FETCH_ASSOC);
                        
                        // if ($stock_info && $stock_info['quantite_stock'] > $stock_info['seuil_alerte']) {
                        //     // Marquer les alertes comme résolues
                        //     $query_update_alerte = "UPDATE alertes_stock 
                        //                            SET statut = 'résolu', date_resolution = NOW() 
                        //                            WHERE article_id = :article_id AND statut = 'active'";
                        //     $stmt_update_alerte = $db->prepare($query_update_alerte);
                        //     $stmt_update_alerte->bindParam(':article_id', $detail['article_id']);
                        //     $stmt_update_alerte->execute();
                        // }
                    }
                    
                    // Supprimer l'opération de caisse si elle existe
                    if ($vente_info['operation_caisse_id']) {
                        $query_delete_caisse = "DELETE FROM caisse WHERE id = :operation_caisse_id";
                        $stmt_delete_caisse = $db->prepare($query_delete_caisse);
                        $stmt_delete_caisse->bindParam(':operation_caisse_id', $vente_info['operation_caisse_id']);
                        $stmt_delete_caisse->execute();
                    }
                    
                    $query_update = "UPDATE ventes SET statut = :statut, operation_caisse_id = NULL, updated_at = NOW() WHERE id = :id";
                    $stmt_update = $db->prepare($query_update);
                    $stmt_update->bindParam(':statut', $nouveau_statut);
                    $stmt_update->bindParam(':id', $vente_id);
                    $stmt_update->execute();
                    
                    $message = "Vente annulée. Le stock a été restauré.";
                    
                } elseif ($nouveau_statut == 'payé' && $vente_info['statut'] == 'annulé') {
                    // Si on repaye une vente annulée, vérifier le stock d'abord
                    $stock_ok = true;
                    $erreurs_stock = [];
                    
                    foreach ($details_vente as $detail) {
                        // Vérifier si le stock est suffisant
                        $query_check_stock = "SELECT quantite_stock, nom FROM articles WHERE id = :article_id";
                        $stmt_check_stock = $db->prepare($query_check_stock);
                        $stmt_check_stock->bindParam(':article_id', $detail['article_id']);
                        $stmt_check_stock->execute();
                        $article_info = $stmt_check_stock->fetch(PDO::FETCH_ASSOC);
                        
                        if ($article_info && $article_info['quantite_stock'] < $detail['quantite']) {
                            $stock_ok = false;
                            $erreurs_stock[] = "Stock insuffisant pour '{$article_info['nom']}'. Disponible: {$article_info['quantite_stock']}, Nécessaire: {$detail['quantite']}";
                        }
                    }
                    
                    if (!$stock_ok) {
                        throw new Exception(implode("<br>", $erreurs_stock));
                    }
                    
                    // Retirer du stock - CORRECTION : pas de quantite_vendue
                    foreach ($details_vente as $detail) {
                        $query_update_stock = "UPDATE articles 
                                              SET quantite_stock = quantite_stock - :quantite,
                                                  updated_at = NOW()
                                              WHERE id = :article_id";
                        $stmt_update_stock = $db->prepare($query_update_stock);
                        $stmt_update_stock->bindParam(':quantite', $detail['quantite']);
                        $stmt_update_stock->bindParam(':article_id', $detail['article_id']);
                        $stmt_update_stock->execute();
                    }
                    
                    // Créer l'opération de caisse
                    $description = "Vente fournitures - " . $vente_info['nom'] . " " . $vente_info['prenom'] . 
                                  " (" . $vente_info['matricule'] . ") - " . $vente_info['classe_nom'] . " (Niv. " . $vente_info['classe_niveau'];
                    if (!empty($vente_info['filiere'])) {
                        $description .= " - Filière: " . $vente_info['filiere'];
                    }
                    $description .= ")";
                    
                    $categorie = 'Vente fournitures';
                    
                    // Vérifier si la table caisse a un champ utilisateur_id
                    $query_check_caisse = "SHOW COLUMNS FROM caisse LIKE 'utilisateur_id'";
                    $stmt_check_caisse = $db->prepare($query_check_caisse);
                    $stmt_check_caisse->execute();
                    $has_utilisateur_id = $stmt_check_caisse->rowCount() > 0;
                    
                    if ($has_utilisateur_id) {
                        $query_caisse = "INSERT INTO caisse (type_operation, montant, date_operation, mode_operation, description, reference, categorie, utilisateur_id, vente_id) 
                         VALUES ('dépôt', :montant, :date_operation, :mode_operation, :description, :reference, :categorie, :utilisateur_id, :vente_id)";
                    } else {
                        $query_caisse = "INSERT INTO caisse (type_operation, montant, date_operation, mode_operation, description, reference, categorie, vente_id) 
                         VALUES ('dépôt', :montant, :date_operation, :mode_operation, :description, :reference, :categorie, :vente_id)";
                    }
                    
                    $stmt_caisse = $db->prepare($query_caisse);
                    $stmt_caisse->bindParam(':montant', $vente_info['montant_total']);
                    $stmt_caisse->bindParam(':date_operation', $vente_info['date_vente']);
                    $stmt_caisse->bindParam(':mode_operation', $vente_info['mode_vente']);
                    $stmt_caisse->bindParam(':description', $description);
                    $stmt_caisse->bindParam(':reference', $vente_info['reference']);
                    $stmt_caisse->bindParam(':categorie', $categorie);
                    $stmt_caisse->bindParam(':vente_id', $vente_id);
                    if ($has_utilisateur_id) {
                        $stmt_caisse->bindParam(':utilisateur_id', $utilisateur_id);
                    }
                    $stmt_caisse->execute();
                    
                    $operation_caisse_id = $db->lastInsertId();
                    
                    $query_update = "UPDATE ventes SET statut = :statut, operation_caisse_id = :operation_caisse_id, updated_at = NOW() WHERE id = :id";
                    $stmt_update = $db->prepare($query_update);
                    $stmt_update->bindParam(':statut', $nouveau_statut);
                    $stmt_update->bindParam(':operation_caisse_id', $operation_caisse_id);
                    $stmt_update->bindParam(':id', $vente_id);
                    $stmt_update->execute();
                    
                    $message = "Vente marquée comme payée. Le stock a été déduit et le dépôt enregistré en caisse.";
                    
                } else {
                    // Simple changement de statut
                    $query_update = "UPDATE ventes SET statut = :statut, updated_at = NOW() WHERE id = :id";
                    $stmt_update = $db->prepare($query_update);
                    $stmt_update->bindParam(':statut', $nouveau_statut);
                    $stmt_update->bindParam(':id', $vente_id);
                    $stmt_update->execute();
                    
                    $message = "Statut de la vente mis à jour.";
                }
                
                // Mettre à jour le solde du jour si nécessaire
                if (function_exists('mettreAJourSoldeJour') && in_array($nouveau_statut, ['payé', 'annulé'])) {
                    mettreAJourSoldeJour($db, $vente_info['date_vente']);
                }
                
                $db->commit();
                $success = $message;
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } else {
            $error = "Vente introuvable!";
        }
        
    } catch (PDOException $e) {
        $error = "Erreur: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Configuration de la pagination
$items_par_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_par_page;

// Récupérer l'historique des ventes avec filtres
$where_conditions = [];
$params = [];

// Filtres
$filtre_etudiant = $_GET['etudiant'] ?? '';
$filtre_classe = $_GET['classe'] ?? '';
$filtre_statut = $_GET['statut'] ?? '';
$filtre_mois = $_GET['mois'] ?? '';
$filtre_annee = $_GET['annee'] ?? '';
$filtre_niveau = $_GET['niveau'] ?? '';

if (!empty($filtre_etudiant)) {
    $where_conditions[] = "v.etudiant_id = :etudiant_id";
    $params[':etudiant_id'] = $filtre_etudiant;
}

if (!empty($filtre_classe)) {
    $where_conditions[] = "e.classe_id = :classe_id";
    $params[':classe_id'] = $filtre_classe;
}

if (!empty($filtre_niveau)) {
    $where_conditions[] = "c.niveau = :niveau";
    $params[':niveau'] = $filtre_niveau;
}

if (!empty($filtre_statut)) {
    $where_conditions[] = "v.statut = :statut";
    $params[':statut'] = $filtre_statut;
}

if (!empty($filtre_mois)) {
    $where_conditions[] = "MONTH(v.date_vente) = :mois";
    $params[':mois'] = $filtre_mois;
}

if (!empty($filtre_annee)) {
    $where_conditions[] = "YEAR(v.date_vente) = :annee";
    $params[':annee'] = $filtre_annee;
}

// Construction de la clause WHERE
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Requête pour le nombre total d'éléments
$query_total = "SELECT COUNT(*) as total 
               FROM ventes v 
               JOIN etudiants e ON v.etudiant_id = e.id 
               LEFT JOIN classe c ON e.classe_id = c.id";

// Ajouter la clause WHERE si nécessaire
if (!empty($where_clause)) {
    $query_total .= " " . $where_clause;
}

$stmt_total = $db->prepare($query_total);
foreach ($params as $key => $value) {
    $stmt_total->bindValue($key, $value);
}
$stmt_total->execute();
$total_result = $stmt_total->fetch(PDO::FETCH_ASSOC);
$total_items = $total_result['total'];
$total_pages = ceil($total_items / $items_par_page);

// Assurer que la page est dans les limites
if ($page < 1) $page = 1;
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// Requête pour les données paginées
$query_ventes = "SELECT v.*, e.nom, e.prenom, e.matricule, e.classe_id,
                        c.nom as classe_nom, c.niveau as classe_niveau, c.filiere,
                        ca.id as caisse_id,
                        u.nom_complet as utilisateur_nom
                 FROM ventes v 
                 JOIN etudiants e ON v.etudiant_id = e.id 
                 LEFT JOIN classe c ON e.classe_id = c.id 
                 LEFT JOIN caisse ca ON v.operation_caisse_id = ca.id
                 LEFT JOIN utilisateurs u ON v.utilisateur_id = u.id";

// Ajouter la clause WHERE si nécessaire
if (!empty($where_clause)) {
    $query_ventes .= " " . $where_clause;
}

$query_ventes .= " ORDER BY v.date_vente DESC, v.id DESC
                 LIMIT :limit OFFSET :offset";

$stmt_ventes = $db->prepare($query_ventes);
foreach ($params as $key => $value) {
    $stmt_ventes->bindValue($key, $value);
}
$stmt_ventes->bindValue(':limit', $items_par_page, PDO::PARAM_INT);
$stmt_ventes->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_ventes->execute();
$ventes = $stmt_ventes->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails des articles pour chaque vente
foreach ($ventes as &$vente) {
    $query_details = "SELECT dv.*, a.nom as article_nom, a.prix as prix_unitaire
                      FROM details_ventes dv
                      JOIN articles a ON dv.article_id = a.id
                      WHERE dv.vente_id = :vente_id";
    $stmt_details = $db->prepare($query_details);
    $stmt_details->bindParam(':vente_id', $vente['id']);
    $stmt_details->execute();
    $vente['details'] = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
}
unset($vente);

// Statistiques des ventes payées avec filtres
$where_stats_conditions = ["v.statut = 'payé'"];
$stats_params = [];

if (!empty($filtre_etudiant)) {
    $where_stats_conditions[] = "v.etudiant_id = :etudiant_id";
    $stats_params[':etudiant_id'] = $filtre_etudiant;
}

if (!empty($filtre_classe)) {
    $where_stats_conditions[] = "e.classe_id = :classe_id";
    $stats_params[':classe_id'] = $filtre_classe;
}

if (!empty($filtre_niveau)) {
    $where_stats_conditions[] = "c.niveau = :niveau";
    $stats_params[':niveau'] = $filtre_niveau;
}

if (!empty($filtre_mois)) {
    $where_stats_conditions[] = "MONTH(v.date_vente) = :mois";
    $stats_params[':mois'] = $filtre_mois;
}

if (!empty($filtre_annee)) {
    $where_stats_conditions[] = "YEAR(v.date_vente) = :annee";
    $stats_params[':annee'] = $filtre_annee;
}

$where_stats_clause = '';
if (!empty($where_stats_conditions)) {
    $where_stats_clause = "WHERE " . implode(" AND ", $where_stats_conditions);
}

// Statistiques de vente
$query_stats = "SELECT 
    COUNT(*) as total_ventes,
    SUM(v.montant_total) as total_montant,
    AVG(v.montant_total) as moyenne_vente,
    COUNT(DISTINCT v.etudiant_id) as etudiants_payants
FROM ventes v 
JOIN etudiants e ON v.etudiant_id = e.id 
LEFT JOIN classe c ON e.classe_id = c.id";

if (!empty($where_stats_clause)) {
    $query_stats .= " " . $where_stats_clause;
}

$stmt_stats = $db->prepare($query_stats);
foreach ($stats_params as $key => $value) {
    $stmt_stats->bindValue($key, $value);
}
$stmt_stats->execute();
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

// Statistiques globales pour les badges
$query_stats_supplementaires = "SELECT 
    COUNT(*) as total_tous_ventes,
    SUM(montant_total) as total_tous_montants,
    COUNT(CASE WHEN statut = 'en attente' THEN 1 END) as ventes_attente,
    COUNT(CASE WHEN statut = 'annulé' THEN 1 END) as ventes_annules
FROM ventes v 
JOIN etudiants e ON v.etudiant_id = e.id 
LEFT JOIN classe c ON e.classe_id = c.id";

if (!empty($where_clause)) {
    $query_stats_supplementaires .= " " . $where_clause;
}

$stmt_stats_supp = $db->prepare($query_stats_supplementaires);
foreach ($params as $key => $value) {
    $stmt_stats_supp->bindValue($key, $value);
}
$stmt_stats_supp->execute();
$stats_supp = $stmt_stats_supp->fetch(PDO::FETCH_ASSOC);

// Statistiques de stock
$query_stock_stats = "SELECT 
    SUM(COALESCE(quantite_stock, 0)) as total_stock,
    COUNT(CASE WHEN quantite_stock <= seuil_alerte AND quantite_stock > 0 THEN 1 END) as alertes_stock
FROM articles";

$stmt_stock_stats = $db->prepare($query_stock_stats);
$stmt_stock_stats->execute();
$stock_stats = $stmt_stock_stats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des ventes de fournitures</title>
    <link href="assets/bootstrap-5.1.3-dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
   <?php 
        $page_title = "Gestion des ventes de fournitures";
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
                    <h2><i class="bi bi-cart-plus me-2"></i>Gestion des ventes de fournitures</h2>
                    <div>
                        <span class="badge bg-info me-2" data-bs-toggle="tooltip" title="Total des ventes">
                            Total: <?php echo $stats_supp['total_tous_ventes'] ?? 0; ?> ventes
                        </span>
                        <span class="badge bg-warning me-2" data-bs-toggle="tooltip" title="Ventes en attente">
                            En attente: <?php echo $stats_supp['ventes_attente'] ?? 0; ?>
                        </span>
                        <span class="badge bg-danger me-2" data-bs-toggle="tooltip" title="Ventes annulées">
                            Annulés: <?php echo $stats_supp['ventes_annules'] ?? 0; ?>
                        </span>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterVenteModal">
                            <i class="bi bi-plus-circle"></i> Nouvelle vente
                        </button>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="card-title mb-0"><i class="bi bi-funnel"></i> Filtres</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="filtre_niveau" class="form-label">Niveau</label>
                                <select class="form-control" id="filtre_niveau" name="niveau">
                                    <option value="">Tous les niveaux</option>
                                    <?php foreach ($niveaux as $niveau): ?>
                                    <option value="<?php echo $niveau; ?>" <?php echo ($filtre_niveau == $niveau) ? 'selected' : ''; ?>>
                                        Niveau <?php echo $niveau; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filtre_classe" class="form-label">Classe</label>
                                <select class="form-control" id="filtre_classe" name="classe">
                                    <option value="">Toutes les classes</option>
                                    <?php foreach ($classes_filtrees as $classe): ?>
                                    <option value="<?php echo $classe['id']; ?>" <?php echo ($filtre_classe == $classe['id']) ? 'selected' : ''; ?>>
                                        <?php 
                                        $texte_classe = htmlspecialchars($classe['nom']) . ' - Niv. ' . htmlspecialchars($classe['niveau']);
                                        if (!empty($classe['filiere'])) {
                                            $texte_classe .= ' (' . htmlspecialchars($classe['filiere']) . ')';
                                        }
                                        echo $texte_classe;
                                        ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="filtre_statut" class="form-label">Statut</label>
                                <select class="form-control" id="filtre_statut" name="statut">
                                    <option value="">Tous les statuts</option>
                                    <option value="payé" <?php echo ($filtre_statut == 'payé') ? 'selected' : ''; ?>>Payé</option>
                                    <option value="en attente" <?php echo ($filtre_statut == 'en attente') ? 'selected' : ''; ?>>En attente</option>
                                    <option value="annulé" <?php echo ($filtre_statut == 'annulé') ? 'selected' : ''; ?>>Annulé</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="filtre_mois" class="form-label">Mois</label>
                                <select class="form-control" id="filtre_mois" name="mois">
                                    <option value="">Tous les mois</option>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($filtre_mois == $i) ? 'selected' : ''; ?>>
                                            <?php echo DateTime::createFromFormat('!m', $i)->format('F'); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="filtre_annee" class="form-label">Année</label>
                                <select class="form-control" id="filtre_annee" name="annee">
                                    <option value="">Toutes les années</option>
                                    <?php for ($i = date('Y') - 5; $i <= date('Y') + 1; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($filtre_annee == $i) ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-filter"></i> Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Résumé des filtres actifs -->
                <?php if (!empty($filtre_niveau) || !empty($filtre_classe) || !empty($filtre_statut) || !empty($filtre_mois)|| !empty($filtre_annee) ): ?>
                    <div class="alert alert-info mb-4">
                        <h6><i class="bi bi-info-circle"></i> Filtres actifs :</h6>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <?php if (!empty($filtre_niveau)): ?>
                            <span class="badge bg-primary">
                                Niveau: <?php echo $filtre_niveau; ?>
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['niveau' => ''])); ?>" class="text-white ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($filtre_classe)): 
                                $classe_nom = '';
                                $classe_niveau = '';
                                $classe_filiere = '';
                                foreach ($classes as $classe) {
                                    if ($classe['id'] == $filtre_classe) {
                                        $classe_nom = $classe['nom'];
                                        $classe_niveau = $classe['niveau'];
                                        $classe_filiere = $classe['filiere'] ?? '';
                                        break;
                                    }
                                }
                            ?>
                            <span class="badge bg-secondary">
                                Classe: <?php echo $classe_nom . ' (Niv. ' . $classe_niveau; 
                                if (!empty($classe_filiere)) {
                                    echo ' - ' . $classe_filiere;
                                }
                                echo ')'; ?>
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['classe' => ''])); ?>" class="text-white ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($filtre_statut)): ?>
                            <span class="badge bg-success">
                                Statut: <?php echo ucfirst($filtre_statut); ?>
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['statut' => ''])); ?>" class="text-white ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($filtre_mois)): ?>
                            <span class="badge bg-info">
                                Mois: <?php echo DateTime::createFromFormat('!m', $filtre_mois)->format('F'); ?>
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['mois' => ''])); ?>" class="text-white ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($filtre_annee) && $filtre_annee != date('Y')): ?>
                            <span class="badge bg-warning">
                                Année: <?php echo $filtre_annee; ?>
                                <a href="?<?php echo http_build_query(array_diff_key($_GET, ['annee' => ''])); ?>" class="text-white ms-1">
                                    <i class="bi bi-x"></i>
                                </a>
                            </span>
                            <?php endif; ?>

                            <a href="?" class="badge bg-danger">
                                <i class="bi bi-x-circle"></i> Supprimer tous les filtres
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo number_format($stats['total_montant'] ?? 0, 0, ',', ' '); ?> Kwz</h4>
                                        <small>Total Collecté</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-cash-coin fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stats['total_ventes'] ?? 0; ?></h4>
                                        <small>Ventes Validées</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stats['etudiants_payants'] ?? 0; ?></h4>
                                        <small>Élèves Ayant Acheté</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-people fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-dark">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo number_format($stats['moyenne_vente'] ?? 0, 0, ',', ' '); ?> Kwz</h4>
                                        <small>Moyenne par vente</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-graph-up fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo number_format($stock_stats['total_stock'] ?? 0, 0, ',', ' '); ?></h4>
                                        <small>Articles en stock</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-box fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card <?php echo ($stock_stats['alertes_stock'] ?? 0) > 0 ? 'bg-danger' : 'bg-success'; ?> text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="mb-0"><?php echo $stock_stats['alertes_stock'] ?? 0; ?></h4>
                                        <small>Alertes stock</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-exclamation-triangle fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historique des ventes -->
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="bi bi-list-ul"></i> Historique des ventes</h5>
                        <div>
                            <span class="badge bg-primary"><?php echo $total_items; ?> vente(s) au total</span>
                            <span class="badge bg-secondary ms-2">Page <?php echo $page; ?> sur <?php echo $total_pages; ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($ventes) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped" id="tableVentes">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Élève</th>
                                        <th>Classe</th>
                                        <th>Articles</th>
                                        <th>Montant</th>
                                        <th>Mode</th>
                                        <th>Statut</th>
                                        <th>Caisse</th>
                                        <th>Enregistré par</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ventes as $vente): ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('d/m/Y', strtotime($vente['date_vente'])); ?></small><br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($vente['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($vente['nom'] . ' ' . $vente['prenom']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($vente['matricule']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <span class="badge bg-secondary">
                                                    <?php echo htmlspecialchars($vente['classe_nom'] ?? 'Non assigné'); ?>
                                                </span>
                                                <br>
                                                <small class="text-muted">
                                                    Niv. <?php echo htmlspecialchars($vente['classe_niveau'] ?? '-'); ?> 
                                                    <?php if (!empty($vente['filiere'])): ?>
                                                    | <?php echo htmlspecialchars($vente['filiere']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($vente['details'])): 
                                                // Générer le contenu HTML pour le popover
                                                $popoverContent = '<div style="max-width: 300px;">';
                                                $popoverContent .= '<table class="table table-sm table-borderless">';
                                                $popoverContent .= '<thead><tr><th>Article</th><th class="text-end">Qté</th><th class="text-end">Prix</th><th class="text-end">Total</th></tr></thead>';
                                                $popoverContent .= '<tbody>';
                                                
                                                foreach ($vente['details'] as $detail) {
                                                    $popoverContent .= '<tr>';
                                                    $popoverContent .= '<td>' . htmlspecialchars($detail['article_nom']) . '</td>';
                                                    $popoverContent .= '<td class="text-end">' . $detail['quantite'] . '</td>';
                                                    $popoverContent .= '<td class="text-end">' . number_format($detail['prix_unitaire'], 0, ',', ' ') . ' Kwz</td>';
                                                    $popoverContent .= '<td class="text-end">' . number_format($detail['sous_total'], 0, ',', ' ') . ' Kwz</td>';
                                                    $popoverContent .= '</tr>';
                                                }
                                                
                                                $popoverContent .= '</tbody>';
                                                $popoverContent .= '<tfoot><tr><td colspan="3" class="text-end"><strong>Total:</strong></td>';
                                                $popoverContent .= '<td class="text-end"><strong>' . number_format($vente['montant_total'], 0, ',', ' ') . ' Kwz</strong></td></tr></tfoot>';
                                                $popoverContent .= '</table></div>';
                                                
                                                // Échapper les guillemets pour JavaScript
                                                $popoverContent = htmlspecialchars($popoverContent, ENT_QUOTES);
                                            ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-info btn-details-vente" 
                                                        data-bs-toggle="popover" 
                                                        data-bs-title="Détails des articles"
                                                        data-bs-html="true"
                                                        data-bs-content="<?php echo $popoverContent; ?>"
                                                        data-vente-id="<?php echo $vente['id']; ?>">
                                                    <i class="bi bi-eye"></i> <?php echo count($vente['details']); ?> article(s)
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">Aucun détail</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success fs-6">
                                                <?php echo number_format($vente['montant_total'], 0, ',', ' '); ?> Kwz
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($vente['mode_vente']); ?></span>
                                            <?php if (!empty($vente['reference'])): ?>
                                            <br><small class="text-muted">Ref: <?php echo htmlspecialchars($vente['reference']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $vente['statut'] == 'payé' ? 'success' : 
                                                    ($vente['statut'] == 'en attente' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo htmlspecialchars($vente['statut']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($vente['statut'] == 'payé' && $vente['caisse_id']): ?>
                                            <span class="badge bg-success" data-bs-toggle="tooltip" title="Dépôt en caisse effectué">
                                                <i class="bi bi-check-circle"></i> Validé
                                            </span>
                                            <?php elseif ($vente['statut'] == 'payé'): ?>
                                            <span class="badge bg-warning" data-bs-toggle="tooltip" title="En attente d'enregistrement en caisse">
                                                <i class="bi bi-clock"></i> En attente
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($vente['utilisateur_nom'])): ?>
                                                <small><?php echo htmlspecialchars($vente['utilisateur_nom']); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">-</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($vente['statut'] != 'payé'): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['changer_statut' => $vente['id'], 'statut' => 'payé'])); ?>" 
                                                class="btn btn-success" data-bs-toggle="tooltip" title="Marquer comme payé"
                                                onclick="return confirm('Marquer cette vente comme payée? Le stock sera déduit.')">
                                                    <i class="bi bi-check-circle"></i>
                                                </a>
                                                <?php endif; ?>
                                                <?php if ($vente['statut'] != 'annulé'): ?>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['changer_statut' => $vente['id'], 'statut' => 'annulé'])); ?>" 
                                                class="btn btn-danger" data-bs-toggle="tooltip" title="Annuler la vente"
                                                onclick="return confirm('Annuler cette vente? Le stock sera restauré.')">
                                                    <i class="bi bi-x-circle"></i>
                                                </a>
                                                <?php endif; ?>
                                                <button class="btn btn-info" data-bs-toggle="tooltip" title="Imprimer le reçu"
                                                        onclick="genererRecu(<?php echo $vente['id']; ?>)">
                                                    <i class="bi bi-receipt"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Pagination des ventes">
                            <ul class="pagination justify-content-center mt-4">
                                <!-- Premier et précédent -->
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                                        <i class="bi bi-chevron-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>

                                <!-- Pages -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <!-- Suivant et dernier -->
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                                        <i class="bi bi-chevron-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                            
                            <!-- Informations de pagination -->
                            <div class="text-center text-muted mt-2">
                                <small>
                                    Affichage de <strong><?php echo (($page - 1) * $items_par_page) + 1; ?></strong> 
                                    à <strong><?php echo min($page * $items_par_page, $total_items); ?></strong> 
                                    sur <strong><?php echo $total_items; ?></strong> ventes
                                </small>
                            </div>
                        </nav>
                        <?php endif; ?>

                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-cart display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">Aucune vente enregistrée</h4>
                            <p class="text-muted">Commencez par enregistrer la première vente.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ajouterVenteModal">
                                <i class="bi bi-plus-circle"></i> Enregistrer la première vente
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

   <!-- Modal Ajouter vente -->
<div class="modal fade" id="ajouterVenteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-cart-plus"></i> Enregistrer une vente</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="form-vente">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="niveau_id" class="form-label">Niveau</label>
                                <select class="form-control" id="niveau_id" name="niveau_id" required>
                                    <option value="">Sélectionner un niveau</option>
                                    <?php foreach ($niveaux as $niveau): ?>
                                        <option value="<?php echo $niveau; ?>">
                                            Niveau <?php echo $niveau; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="classe_id" class="form-label">Classe</label>
                                <select class="form-control" id="classe_id" name="classe_id" required disabled>
                                    <option value="">Sélectionner d'abord un niveau</option>
                                </select>
                                <div class="form-text">
                                    <span id="classe-info-text">Veuillez d'abord sélectionner un niveau</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="etudiant_id" class="form-label">Élève</label>
                                <select class="form-control" id="etudiant_id" name="etudiant_id" required disabled>
                                    <option value="">Sélectionner d'abord une classe</option>
                                </select>
                                <div class="form-text">
                                    <span id="etudiant-info-text">Veuillez d'abord sélectionner une classe</span>
                                </div>
                            </div>
                             
                            <div class="mb-3">
                                <label for="mode_vente" class="form-label">Mode de vente</label>
                                <select class="form-control" id="mode_vente" name="mode_vente" required>
                                    <option value="espèces">Espèces</option>
                                    <option value="chèque">Chèque</option>
                                    <option value="virement">Virement</option>
                                    <option value="carte">Carte bancaire</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="date_vente" class="form-label">Date de vente</label>
                                <input type="date" class="form-control" id="date_vente" 
                                       name="date_vente" value="<?php echo date('Y-m-d'); ?>" required>
                            </div> 
                            
                            <div class="mb-4">
                                <label for="reference" class="form-label">Référence</label>
                                <input type="text" class="form-control" id="reference" 
                                       name="reference" placeholder="Numéro de chèque, référence virement, etc.">
                            </div>
                            
                            <!-- Informations supplémentaires -->
                            <div class="card bg-light mt-3">
                                <div class="card-body p-3">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        <strong>Informations :</strong>
                                        <div id="infos-supplementaires">
                                            Sélectionnez un élève pour voir les détails
                                        </div>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section des articles -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6><i class="bi bi-bag-check"></i> Articles à vendre</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="ajouter-article">
                                    <i class="bi bi-plus-circle"></i> Ajouter un article
                                </button>
                            </div>
                            
                            <div id="articles-container">
                                <!-- Les lignes d'articles seront ajoutées ici dynamiquement -->
                                <div class="article-row mb-3">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-6">
                                            <label class="form-label">Article</label>
                                            <select class="form-control article-select" name="articles[]" required onchange="verifierStock(this)">
                                                <option value="">Sélectionner un article</option>
                                                <?php foreach ($article_list as $article): 
                                                    $stock_info = $article['stock_disponible'] > 0 ? 
                                                        " (Stock: {$article['stock_disponible']})" : 
                                                        " (Rupture de stock)";
                                                ?>
                                                    <option value="<?php echo $article['id']; ?>" 
                                                            data-prix="<?php echo $article['prix']; ?>"
                                                            data-stock="<?php echo $article['stock_disponible']; ?>"
                                                            data-nom="<?php echo htmlspecialchars($article['nom']); ?>"
                                                            <?php echo $article['stock_disponible'] <= 0 ? 'disabled' : ''; ?>>
                                                        <?php echo $article['nom'] . ' - ' . number_format($article['prix'], 0, ',', ' ') . ' Kwz' . $stock_info; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-danger stock-error" style="display: none;"></small>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Quantité</label>
                                            <input type="number" class="form-control quantite-input" name="quantites[]" min="1" value="1" required
                                                   oninput="verifierQuantite(this)">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Prix unitaire</label>
                                            <input type="text" class="form-control prix-unitaire" value="0" readonly>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-outline-danger btn-sm supprimer-article" style="display: none;">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Résumé du total -->
                            <div class="card mt-4">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Résumé de la vente</h6>
                                            <div id="resume-articles">
                                                <p class="text-muted mb-0">Aucun article sélectionné</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <h5 class="mb-0">Total: <span id="montant-total">0</span> Kwz</h5>
                                            <input type="hidden" id="montant-total-hidden" name="montant_total">
                                            <p class="text-muted" id="nombre-articles">0 article(s)</p>
                                            <div id="messages-stock" class="small"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="enregistrer_vente" class="btn btn-primary" id="btn-enregistrer-vente">
                        <i class="bi bi-save"></i> Enregistrer la vente
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="assets/bootstrap-5.1.3-dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const niveauSelect = document.getElementById('niveau_id');
            const classeSelect = document.getElementById('classe_id');
            const etudiantSelect = document.getElementById('etudiant_id');
            const articlesContainer = document.getElementById('articles-container');
            const ajouterArticleBtn = document.getElementById('ajouter-article');
            const montantTotalSpan = document.getElementById('montant-total');
            const montantTotalHidden = document.getElementById('montant-total-hidden');
            const nombreArticlesSpan = document.getElementById('nombre-articles');
            const resumeArticles = document.getElementById('resume-articles');
            const classeInfoText = document.getElementById('classe-info-text');
            const etudiantInfoText = document.getElementById('etudiant-info-text');
            const infosSupplementaires = document.getElementById('infos-supplementaires');
            const messagesStock = document.getElementById('messages-stock');
            const btnEnregistrerVente = document.getElementById('btn-enregistrer-vente');
            
            // Variables pour stocker les données
            let etudiantsData = {};
            let articlesData = {};
            
            // Initialiser les données des articles
            <?php foreach ($article_list as $article): ?>
            articlesData[<?php echo $article['id']; ?>] = {
                id: <?php echo $article['id']; ?>,
                nom: "<?php echo addslashes($article['nom']); ?>",
                prix: <?php echo $article['prix']; ?>,
                stock: <?php echo $article['stock_disponible']; ?>
            };
            <?php endforeach; ?>
            
            // Gestion du changement de niveau
            niveauSelect.addEventListener('change', function() {
                const niveau = this.value;
                
                if (niveau) {
                    // Activer le champ classe
                    classeSelect.disabled = false;
                    classeInfoText.textContent = 'Chargement des classes...';
                    
                    // Charger les classes de ce niveau via AJAX
                    chargerClassesParNiveau(niveau);
                } else {
                    // Désactiver et vider les champs classe et étudiant
                    classeSelect.disabled = true;
                    classeSelect.innerHTML = '<option value="">Sélectionner d\'abord un niveau</option>';
                    classeInfoText.textContent = 'Veuillez d\'abord sélectionner un niveau';
                    
                    etudiantSelect.disabled = true;
                    etudiantSelect.innerHTML = '<option value="">Sélectionner d\'abord une classe</option>';
                    etudiantInfoText.textContent = 'Veuillez d\'abord sélectionner une classe';
                    
                    // Réinitialiser les infos supplémentaires
                    infosSupplementaires.innerHTML = 'Sélectionnez un élève pour voir les détails';
                }
            });
            
            // Gestion du changement de classe
            classeSelect.addEventListener('change', function() {
                const classeId = this.value;
                
                if (classeId) {
                    // Activer le champ étudiant
                    etudiantSelect.disabled = false;
                    etudiantInfoText.textContent = 'Chargement des élèves...';
                    
                    // Charger les étudiants de cette classe via AJAX
                    chargerEtudiantsParClasse(classeId);
                    
                    // Afficher les infos de la classe sélectionnée
                    const selectedClass = classeSelect.options[classeSelect.selectedIndex];
                    classeInfoText.textContent = selectedClass.textContent;
                } else {
                    // Désactiver et vider le champ étudiant
                    etudiantSelect.disabled = true;
                    etudiantSelect.innerHTML = '<option value="">Sélectionner d\'abord une classe</option>';
                    etudiantInfoText.textContent = 'Veuillez d\'abord sélectionner une classe';
                    
                    // Réinitialiser les infos supplémentaires
                    infosSupplementaires.innerHTML = 'Sélectionnez un élève pour voir les détails';
                }
            });
            
            // Gestion du changement d'étudiant
            etudiantSelect.addEventListener('change', function() {
                const etudiantId = this.value;
                
                if (etudiantId && etudiantsData[etudiantId]) {
                    const etudiant = etudiantsData[etudiantId];
                    etudiantInfoText.textContent = `${etudiant.matricule} - ${etudiant.nom} ${etudiant.prenom}`;
                    
                    // Afficher les infos supplémentaires
                    afficherInfosEtudiant(etudiant);
                } else {
                    etudiantInfoText.textContent = 'Sélectionnez un élève';
                    infosSupplementaires.innerHTML = 'Sélectionnez un élève pour voir les détails';
                }
            });
            
            // Fonction pour vérifier le stock d'un article
            window.verifierStock = function(selectElement) {
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
                const prix = parseFloat(selectedOption.getAttribute('data-prix')) || 0;
                const nomArticle = selectedOption.getAttribute('data-nom') || '';
                
                // Mettre à jour le prix unitaire
                const prixUnitaireInput = selectElement.closest('.article-row').querySelector('.prix-unitaire');
                if (prixUnitaireInput) {
                    prixUnitaireInput.value = new Intl.NumberFormat('fr-FR').format(prix);
                }
                
                // Mettre à jour la quantité maximale
                const quantiteInput = selectElement.closest('.article-row').querySelector('.quantite-input');
                if (quantiteInput) {
                    quantiteInput.max = stock;
                    if (parseInt(quantiteInput.value) > stock) {
                        quantiteInput.value = stock;
                        afficherMessageStock(`Quantité réduite au stock disponible (${stock}) pour "${nomArticle}"`, 'warning');
                    }
                }
                
                // Afficher un message si stock faible
                const stockError = selectElement.nextElementSibling;
                if (stockError && stockError.classList.contains('stock-error')) {
                    if (stock === 0) {
                        stockError.textContent = 'Rupture de stock';
                        stockError.style.display = 'block';
                    } else if (stock < 5) {
                        stockError.textContent = `Stock faible: ${stock} unité(s) disponible(s)`;
                        stockError.style.display = 'block';
                    } else {
                        stockError.style.display = 'none';
                    }
                }
                
                calculerTotal();
                mettreAJourResume();
                verifierDisponibiliteGlobale();
            };
            
            // Fonction pour vérifier la quantité
            window.verifierQuantite = function(inputElement) {
                const quantite = parseInt(inputElement.value) || 0;
                const selectElement = inputElement.closest('.article-row').querySelector('.article-select');
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
                const nomArticle = selectedOption.getAttribute('data-nom') || '';
                
                if (quantite > stock) {
                    inputElement.value = stock;
                    afficherMessageStock(`Quantité réduite au stock disponible (${stock}) pour "${nomArticle}"`, 'warning');
                }
                
                calculerTotal();
                mettreAJourResume();
                verifierDisponibiliteGlobale();
            };
            
            // Fonction pour vérifier la disponibilité globale
            function verifierDisponibiliteGlobale() {
                let toutesDisponibles = true;
                let messages = [];
                
                const articleRows = articlesContainer.querySelectorAll('.article-row');
                articleRows.forEach(row => {
                    const selectElement = row.querySelector('.article-select');
                    const quantiteInput = row.querySelector('.quantite-input');
                    
                    if (selectElement.value && quantiteInput.value) {
                        const selectedOption = selectElement.options[selectElement.selectedIndex];
                        const stock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
                        const quantite = parseInt(quantiteInput.value) || 0;
                        const nomArticle = selectedOption.getAttribute('data-nom') || '';
                        
                        if (quantite > stock) {
                            toutesDisponibles = false;
                            messages.push(`Stock insuffisant pour "${nomArticle}"`);
                        }
                    }
                });
                
                if (messages.length > 0) {
                    afficherMessageStock(messages.join('<br>'), 'danger');
                } else {
                    messagesStock.innerHTML = '';
                }
                
                btnEnregistrerVente.disabled = !toutesDisponibles;
                return toutesDisponibles;
            }
            
            // Fonction pour afficher un message de stock
            function afficherMessageStock(message, type = 'info') {
                messagesStock.innerHTML = `<div class="alert alert-${type} alert-sm p-2 mb-0">${message}</div>`;
                setTimeout(() => {
                    if (type !== 'danger') {
                        messagesStock.innerHTML = '';
                    }
                }, 5000);
            }
            
            // Fonction pour ajouter une ligne d'article
            function ajouterLigneArticle() {
                const articleRow = document.createElement('div');
                articleRow.className = 'article-row mb-3';
                articleRow.innerHTML = `
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label">Article</label>
                            <select class="form-control article-select" name="articles[]" required onchange="verifierStock(this)">
                                <option value="">Sélectionner un article</option>
                                <?php foreach ($article_list as $article): 
                                    $stock_info = $article['stock_disponible'] > 0 ? 
                                        " (Stock: {$article['stock_disponible']})" : 
                                        " (Rupture de stock)";
                                ?>
                                    <option value="<?php echo $article['id']; ?>" 
                                            data-prix="<?php echo $article['prix']; ?>"
                                            data-stock="<?php echo $article['stock_disponible']; ?>"
                                            data-nom="<?php echo htmlspecialchars($article['nom']); ?>"
                                            <?php echo $article['stock_disponible'] <= 0 ? 'disabled' : ''; ?>>
                                        <?php echo $article['nom'] . ' - ' . number_format($article['prix'], 0, ',', ' ') . ' Kwz' . $stock_info; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-danger stock-error" style="display: none;"></small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Quantité</label>
                            <input type="number" class="form-control quantite-input" name="quantites[]" min="1" value="1" required
                                   oninput="verifierQuantite(this)">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Prix unitaire</label>
                            <input type="text" class="form-control prix-unitaire" value="0" readonly>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm supprimer-article">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                
                articlesContainer.appendChild(articleRow);
                
                // Ajouter les événements pour la nouvelle ligne
                const supprimerBtn = articleRow.querySelector('.supprimer-article');
                
                supprimerBtn.addEventListener('click', function() {
                    articleRow.remove();
                    calculerTotal();
                    mettreAJourResume();
                    verifierDisponibiliteGlobale();
                    mettreAJourBoutonsSupprimer();
                });
                
                // Mettre à jour les boutons supprimer
                mettreAJourBoutonsSupprimer();
            }
            
            // Fonction pour mettre à jour les boutons supprimer
            function mettreAJourBoutonsSupprimer() {
                const articleRows = articlesContainer.querySelectorAll('.article-row');
                articleRows.forEach((row, index) => {
                    const supprimerBtn = row.querySelector('.supprimer-article');
                    if (supprimerBtn) {
                        supprimerBtn.style.display = articleRows.length > 1 ? 'block' : 'none';
                    }
                });
            }
            
            // Fonction pour calculer le total
            function calculerTotal() {
                let total = 0;
                let nombreArticles = 0;
                
                const articleRows = articlesContainer.querySelectorAll('.article-row');
                articleRows.forEach(row => {
                    const articleSelect = row.querySelector('.article-select');
                    const quantiteInput = row.querySelector('.quantite-input');
                    
                    if (articleSelect.value && quantiteInput.value) {
                        const selectedOption = articleSelect.options[articleSelect.selectedIndex];
                        const prix = parseFloat(selectedOption.getAttribute('data-prix')) || 0;
                        const quantite = parseFloat(quantiteInput.value) || 0;
                        
                        total += prix * quantite;
                        nombreArticles += quantite;
                    }
                });
                
                montantTotalSpan.textContent = new Intl.NumberFormat('fr-FR', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(total);
                
                montantTotalHidden.value = total;
                nombreArticlesSpan.textContent = nombreArticles + ' article(s)';
            }
            
            // Fonction pour mettre à jour le résumé
            function mettreAJourResume() {
                const articleRows = articlesContainer.querySelectorAll('.article-row');
                let resumeHTML = '';
                let total = 0;
                
                if (articleRows.length === 1 && !articleRows[0].querySelector('.article-select').value) {
                    resumeHTML = '<p class="text-muted mb-0">Aucun article sélectionné</p>';
                } else {
                    articleRows.forEach(row => {
                        const articleSelect = row.querySelector('.article-select');
                        const quantiteInput = row.querySelector('.quantite-input');
                        
                        if (articleSelect.value && quantiteInput.value) {
                            const selectedOption = articleSelect.options[articleSelect.selectedIndex];
                            const prix = parseFloat(selectedOption.getAttribute('data-prix')) || 0;
                            const quantite = parseFloat(quantiteInput.value) || 0;
                            const sousTotal = prix * quantite;
                            total += sousTotal;
                            
                            resumeHTML += `
                                <div class="d-flex justify-content-between mb-1">
                                    <span>${selectedOption.textContent.split(' - ')[0]} × ${quantite}</span>
                                    <span>${new Intl.NumberFormat('fr-FR').format(sousTotal)} Kwz</span>
                                </div>
                            `;
                        }
                    });
                    
                    if (resumeHTML) {
                        resumeHTML += `
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <strong>Total</strong>
                                <strong>${new Intl.NumberFormat('fr-FR').format(total)} Kwz</strong>
                            </div>
                        `;
                    }
                }
                
                resumeArticles.innerHTML = resumeHTML || '<p class="text-muted mb-0">Aucun article sélectionné</p>';
            }
            
            // Fonction pour charger les classes par niveau avec filière
            function chargerClassesParNiveau(niveau) {
                // Afficher un indicateur de chargement
                classeSelect.innerHTML = '<option value="">Chargement...</option>';
                
                // Envoyer une requête AJAX pour récupérer les classes
                fetch(`api/classes-par-niveau.php?niveau=${niveau}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erreur réseau');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Vider le select
                        classeSelect.innerHTML = '<option value="">Sélectionner une classe</option>';
                        
                        // Ajouter les options de classes avec filière
                        if (data && data.length > 0) {
                            data.forEach(classe => {
                                const option = document.createElement('option');
                                option.value = classe.id;
                                let texte = `${classe.nom} - Niv. ${classe.niveau}`;
                                if (classe.filiere) {
                                    texte += ` (${classe.filiere})`;
                                }
                                option.textContent = texte;
                                option.setAttribute('data-filiere', classe.filiere || '');
                                classeSelect.appendChild(option);
                            });
                            
                            classeInfoText.textContent = `${data.length} classe(s) disponible(s)`;
                        } else {
                            classeInfoText.textContent = 'Aucune classe disponible pour ce niveau';
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement des classes:', error);
                        classeSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                        classeInfoText.textContent = 'Erreur lors du chargement des classes';
                    });
            }
            
            // Fonction pour charger les étudiants par classe
            function chargerEtudiantsParClasse(classeId) {
                // Afficher un indicateur de chargement
                etudiantSelect.innerHTML = '<option value="">Chargement...</option>';
                
                // Envoyer une requête AJAX pour récupérer les étudiants
                fetch(`api/etudiants-par-classe.php?classe_id=${classeId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Erreur réseau');
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Vider le select
                        etudiantSelect.innerHTML = '<option value="">Sélectionner un élève</option>';
                        
                        // Stocker les données des étudiants
                        etudiantsData = {};
                        
                        // Ajouter les options d'étudiants
                        if (data && data.length > 0) {
                            data.forEach(etudiant => {
                                const option = document.createElement('option');
                                option.value = etudiant.id;
                                option.textContent = `${etudiant.matricule} - ${etudiant.nom} ${etudiant.prenom}`;
                                etudiantSelect.appendChild(option);
                                
                                // Stocker les données complètes de l'étudiant
                                etudiantsData[etudiant.id] = etudiant;
                            });
                            
                            etudiantInfoText.textContent = `${data.length} élève(s) disponible(s)`;
                        } else {
                            etudiantInfoText.textContent = 'Aucun élève dans cette classe';
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors du chargement des étudiants:', error);
                        etudiantSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                        etudiantInfoText.textContent = 'Erreur lors du chargement des élèves';
                    });
            }
            
            // Fonction pour afficher les informations de l'étudiant
            function afficherInfosEtudiant(etudiant) {
                let html = `
                    <div class="mt-2">
                        <strong>${etudiant.nom} ${etudiant.prenom}</strong><br>
                        <small>Matricule: ${etudiant.matricule}</small><br>
                        <small>Classe: ${etudiant.classe_nom}</small><br>
                        <small>Niveau: ${etudiant.classe_niveau}</small>
                `;
                
                if (etudiant.classe_filiere) {
                    html += `<br><small>Filière: ${etudiant.classe_filiere}</small>`;
                }
                
                if (etudiant.telephone) {
                    html += `<br><small>Téléphone: ${etudiant.telephone}</small>`;
                }
                
                if (etudiant.email) {
                    html += `<br><small>Email: ${etudiant.email}</small>`;
                }
                
                html += `</div>`;
                infosSupplementaires.innerHTML = html;
            }
            
            // Ajouter un événement au bouton "Ajouter un article"
            ajouterArticleBtn.addEventListener('click', ajouterLigneArticle);
            
            // Initialiser les événements pour la première ligne d'article
            const premierArticleSelect = articlesContainer.querySelector('.article-select');
            if (premierArticleSelect) {
                // Déclencher l'événement change pour initialiser
                premierArticleSelect.dispatchEvent(new Event('change'));
            }
            
            // Mettre à jour les boutons supprimer au chargement
            mettreAJourBoutonsSupprimer();
            
            // Calculer le total initial
            calculerTotal();
            mettreAJourResume();

            // Activation des tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Fonction pour initialiser les popovers
            function initPopovers() {
                // Détruire les popovers existants
                const existingPopovers = document.querySelectorAll('[data-bs-toggle="popover"]');
                existingPopovers.forEach(el => {
                    const popover = bootstrap.Popover.getInstance(el);
                    if (popover) {
                        popover.dispose();
                    }
                });
                
                // Initialiser les nouveaux popovers
                const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
                popoverTriggerList.forEach(function (popoverTriggerEl) {
                    new bootstrap.Popover(popoverTriggerEl, {
                        container: 'body',
                        trigger: 'hover click',
                        placement: 'auto',
                        html: true,
                        sanitize: false
                    });
                });
            }
            
            // Initialiser les popovers après un court délai
            setTimeout(initPopovers, 100);
        });

        function genererRecu(venteId) {
            // Ouvrir dans une nouvelle fenêtre pour impression
            var url = 'generer_recu.php?id=' + venteId + '&type=vente&auto_print=1';
            var windowFeatures = 'width=800,height=900,scrollbars=yes,resizable=yes';
            window.open(url, '_blank', windowFeatures);
        }
        </script>
</body>
<?php include 'layout-end.php'; ?>
</html>