-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : jeu. 27 nov. 2025 à 23:33
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestion_finance_ecole`
--

-- --------------------------------------------------------

--
-- Structure de la table `caisse`
--

CREATE TABLE `caisse` (
  `id` int(11) NOT NULL,
  `type_operation` enum('dépôt','retrait') NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_operation` datetime NOT NULL,
  `mode_operation` enum('espèces','chèque','virement','carte') NOT NULL,
  `description` text NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `categorie` enum('scolarité','frais_divers','salaires','fournitures','loyer','entretien','autre') NOT NULL DEFAULT 'scolarité',
  `statut` enum('validé','en_attente','annulé') DEFAULT 'validé',
  `paiement_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `caisse`
--

INSERT INTO `caisse` (`id`, `type_operation`, `montant`, `date_operation`, `mode_operation`, `description`, `reference`, `utilisateur_id`, `date_creation`, `categorie`, `statut`, `paiement_id`) VALUES
(1, 'dépôt', 500000.00, '2024-01-15 09:00:00', 'espèces', 'Fonds de démarrage caisse', 'INIT001', 1, '2025-11-20 15:00:46', 'scolarité', 'validé', NULL),
(5, 'dépôt', 30.00, '2025-11-21 15:46:00', 'espèces', 'EUHEUFEI', 'REF002', 3, '2025-11-21 14:47:38', '', 'validé', NULL),
(6, 'dépôt', 5000.00, '2025-11-24 00:00:00', 'espèces', 'Paiement Frais d\'inscription - EMBONGO Nissi (MAT-001)', 'REF0013', 1, '2025-11-24 15:44:00', '', 'validé', 2),
(7, 'dépôt', 500.00, '2025-11-24 00:00:00', 'espèces', 'Paiement Frais scolaire - EMBONGO Nissi (MAT-001)', 'REF0053', 1, '2025-11-24 15:56:55', 'scolarité', 'validé', 3),
(8, 'dépôt', 5000.00, '2025-11-24 00:00:00', 'espèces', 'Paiement Frais d\'inscription - KANANGILA  JEPTHE (MAT-002)', 'REF0012', 1, '2025-11-24 16:14:50', '', 'validé', 4),
(9, 'dépôt', 100.00, '2025-11-24 00:00:00', 'espèces', 'Paiement Frais scolaire - KANANGILA  JEPTHE (MAT-002)', 'REF0014', 1, '2025-11-24 16:40:55', 'scolarité', 'validé', 5),
(10, 'dépôt', 59.00, '2025-11-24 00:00:00', 'espèces', 'Paiement Frais scolaire - KANANGILA  JEPTHE (MAT-002)', 'REF0015', 1, '2025-11-24 17:04:26', 'scolarité', 'validé', 6),
(11, 'dépôt', 1000.00, '2025-11-24 00:00:00', 'espèces', 'Paiement Frais scolaire - EMBONGO Nissi (MAT-001) - 7 ère ', 'REF0011', 1, '2025-11-24 21:02:35', 'scolarité', 'validé', 10),
(12, 'dépôt', 155.00, '2025-11-24 00:00:00', 'espèces', 'Paiement Frais scolaire - KANANGILA  JEPTHE (MAT-002) - 1 ère ', 'REF0017', 1, '2025-11-24 21:05:19', 'scolarité', 'validé', 7),
(14, 'dépôt', 5000.00, '2025-11-26 00:00:00', 'espèces', 'Paiement Frais d\'inscription - TANGANGA MVUMBI Emmanuel (MAT2025064) - 7 ère ', 'REF8877', 3, '2025-11-26 21:17:24', '', 'validé', 12),
(15, 'retrait', -4.00, '2025-11-27 15:36:00', 'espèces', 'Repas', 'REF0015Rep', 1, '2025-11-27 14:38:57', '', 'validé', NULL),
(16, 'retrait', -4.00, '2025-11-27 15:36:00', 'espèces', 'Repas', 'REF0015Rep', 1, '2025-11-27 14:53:14', '', 'validé', NULL),
(17, 'retrait', -6.00, '2025-11-27 15:53:00', 'espèces', 'Entretient ', 'REF0014Entretient', 1, '2025-11-27 14:54:53', 'entretien', 'validé', NULL),
(18, 'dépôt', 70.00, '2025-11-24 00:00:00', 'espèces', 'Paiement Frais scolaire - KANANGILA  JEPTHE (MAT-002) - 1 ère ', 'REF0050', 1, '2025-11-27 15:06:29', 'scolarité', 'validé', 9),
(19, 'dépôt', 70.00, '2025-11-24 00:00:00', 'espèces', 'Paiement Frais scolaire - KANANGILA  JEPTHE (MAT-002) - 1 ère  (Niv. Primaire)', 'REF0050', 1, '2025-11-27 22:26:36', 'scolarité', 'validé', 8);

-- --------------------------------------------------------

--
-- Structure de la table `categories_caisse`
--

CREATE TABLE `categories_caisse` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `type` enum('recette','depense') NOT NULL,
  `description` text DEFAULT NULL,
  `couleur` varchar(7) DEFAULT '#007bff',
  `statut` enum('actif','inactif') DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categories_caisse`
--

INSERT INTO `categories_caisse` (`id`, `nom`, `type`, `description`, `couleur`, `statut`) VALUES
(1, 'Scolarité', 'recette', 'Paiements des frais de scolarité', '#28a745', 'actif'),
(2, 'Frais d\'inscription', 'recette', 'Frais d\'inscription des étudiants', '#20c997', 'actif'),
(3, 'Frais divers', 'recette', 'Autres recettes diverses', '#17a2b8', 'actif'),
(4, 'Salaires', 'depense', 'Paiement des salaires du personnel', '#dc3545', 'actif'),
(5, 'Fournitures', 'depense', 'Achat de fournitures scolaires', '#fd7e14', 'actif'),
(6, 'Loyer', 'depense', 'Paiement du loyer', '#6f42c1', 'actif'),
(7, 'Entretien', 'depense', 'Frais d\'entretien des locaux', '#e83e8c', 'actif'),
(8, 'Services', 'depense', 'Paiement de services divers', '#6c757d', 'actif'),
(9, 'Autres dépenses', 'depense', 'Autres dépenses non catégorisées', '#343a40', 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `classe`
--

CREATE TABLE `classe` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `niveau` varchar(50) NOT NULL,
  `filiere` varchar(100) DEFAULT NULL,
  `capacite_max` int(11) DEFAULT 30,
  `annee_scolaire` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `classe`
--

INSERT INTO `classe` (`id`, `nom`, `niveau`, `filiere`, `capacite_max`, `annee_scolaire`, `created_at`, `updated_at`) VALUES
(1, '1 ère ', 'Primaire', 'Primaire', 30, '2025-2026', '2025-11-24 20:51:19', '2025-11-24 20:51:19'),
(2, '7 ère ', 'Primaire', 'Primaire', 30, '2025-2026', '2025-11-24 20:53:57', '2025-11-24 20:53:57'),
(3, '1 ère A', 'Secondaire', NULL, 100, '2025-2026', '2025-11-27 18:50:13', '2025-11-27 19:07:25');

-- --------------------------------------------------------

--
-- Structure de la table `etudiants`
--

CREATE TABLE `etudiants` (
  `id` int(11) NOT NULL,
  `matricule` varchar(20) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `classe` varchar(20) NOT NULL,
  `date_inscription` date NOT NULL,
  `classe_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `etudiants`
--

INSERT INTO `etudiants` (`id`, `matricule`, `nom`, `prenom`, `telephone`, `email`, `classe`, `date_inscription`, `classe_id`) VALUES
(1, 'MAT-001', 'EMBONGO', 'Nissi', '0812796152', 'nissiembongo@gmail.com', '7 ieme', '2025-11-20', 2),
(2, 'MAT-002', 'KANANGILA ', 'JEPTHE', '0812796152', 'jephthekanangila@gmail.com', '4 ieme Sc B', '2025-11-24', 1),
(3, 'MAT2025064', 'TANGANGA MVUMBI', 'Emmanuel', '+244 898458875', 'emmanuel@gmail.com', '', '2025-11-26', 2),
(4, 'MAT2025485', 'KANAGILA INONGE', ' JEREDE', '', '', '', '2025-11-27', 3);

-- --------------------------------------------------------

--
-- Structure de la table `frais`
--

CREATE TABLE `frais` (
  `id` int(11) NOT NULL,
  `type_frais` varchar(100) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `annee_scolaire` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `frais`
--

INSERT INTO `frais` (`id`, `type_frais`, `montant`, `description`, `annee_scolaire`) VALUES
(1, 'Frais scolaire', 1000.00, '', '2025-2026'),
(2, 'Frais d\'inscription', 5000.00, '', '2025-2026');

-- --------------------------------------------------------

--
-- Structure de la table `paiements`
--

CREATE TABLE `paiements` (
  `id` int(11) NOT NULL,
  `etudiant_id` int(11) NOT NULL,
  `frais_id` int(11) NOT NULL,
  `montant_paye` decimal(10,2) NOT NULL,
  `date_paiement` date NOT NULL,
  `mode_paiement` enum('espèces','chèque','virement','carte') NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `statut` enum('payé','en attente','annulé') DEFAULT 'payé',
  `operation_caisse_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `paiements`
--

INSERT INTO `paiements` (`id`, `etudiant_id`, `frais_id`, `montant_paye`, `date_paiement`, `mode_paiement`, `reference`, `statut`, `operation_caisse_id`) VALUES
(1, 1, 1, 100.00, '2025-11-20', 'espèces', 'REF0012', 'payé', NULL),
(2, 1, 2, 5000.00, '2025-11-24', 'espèces', 'REF0013', 'payé', 6),
(3, 1, 1, 500.00, '2025-11-24', 'espèces', 'REF0053', 'payé', 7),
(4, 2, 2, 5000.00, '2025-11-24', 'espèces', 'REF0012', 'payé', 8),
(5, 2, 1, 100.00, '2025-11-24', 'espèces', 'REF0014', 'payé', 9),
(6, 2, 1, 59.00, '2025-11-24', 'espèces', 'REF0015', 'payé', 10),
(7, 2, 1, 155.00, '2025-11-24', 'espèces', 'REF0017', 'payé', 12),
(8, 2, 1, 70.00, '2025-11-24', 'espèces', 'REF0050', 'payé', 19),
(9, 2, 1, 70.00, '2025-11-24', 'espèces', 'REF0050', 'payé', 18),
(10, 1, 1, 1000.00, '2025-11-24', 'espèces', 'REF0011', 'payé', 11),
(12, 3, 2, 5000.00, '2025-11-26', 'espèces', 'REF8877', 'payé', 14);

-- --------------------------------------------------------

--
-- Structure de la table `solde_caisse`
--

CREATE TABLE `solde_caisse` (
  `id` int(11) NOT NULL,
  `date_solde` date NOT NULL,
  `solde_ouverture` decimal(12,2) NOT NULL DEFAULT 0.00,
  `solde_fermeture` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_depots` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_retraits` decimal(12,2) NOT NULL DEFAULT 0.00,
  `nombre_operations` int(11) NOT NULL DEFAULT 0,
  `statut` enum('ouvert','fermé') DEFAULT 'ouvert',
  `utilisateur_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `solde_caisse`
--

INSERT INTO `solde_caisse` (`id`, `date_solde`, `solde_ouverture`, `solde_fermeture`, `total_depots`, `total_retraits`, `nombre_operations`, `statut`, `utilisateur_id`, `notes`) VALUES
(1, '2025-11-21', 0.00, 0.00, 30.00, 0.00, 1, 'fermé', 3, ''),
(2, '2025-11-24', 0.00, 0.00, 11954.00, 0.00, 9, 'ouvert', 1, NULL),
(3, '2025-11-26', 0.00, 0.00, 5000.00, 0.00, 1, 'ouvert', 3, NULL),
(4, '2025-11-27', 0.00, 0.00, 0.00, 14.00, 3, 'ouvert', 3, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `sorties`
--

CREATE TABLE `sorties` (
  `id` int(11) NOT NULL,
  `date_sortie` date NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `utilisateur_id` int(11) NOT NULL,
  `motif` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `type_frais`
--

CREATE TABLE `type_frais` (
  `id` int(11) NOT NULL,
  `libele` varchar(50) NOT NULL,
  `montant` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

CREATE TABLE `utilisateurs` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nom_complet` varchar(100) NOT NULL,
  `role` enum('admin','caissier') DEFAULT 'caissier'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `username`, `password`, `nom_complet`, `role`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'Administrateur Principal', 'admin'),
(3, 'Nissi', 'a721028360a3559fd038def5dbb195f4', 'Nissi Embongo', 'caissier');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `caisse`
--
ALTER TABLE `caisse`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`),
  ADD KEY `paiement_id` (`paiement_id`);

--
-- Index pour la table `categories_caisse`
--
ALTER TABLE `categories_caisse`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `classe`
--
ALTER TABLE `classe`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nom` (`nom`);

--
-- Index pour la table `etudiants`
--
ALTER TABLE `etudiants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricule` (`matricule`),
  ADD KEY `fk_etudiant_classe` (`classe_id`);

--
-- Index pour la table `frais`
--
ALTER TABLE `frais`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `etudiant_id` (`etudiant_id`),
  ADD KEY `frais_id` (`frais_id`),
  ADD KEY `operation_caisse_id` (`operation_caisse_id`);

--
-- Index pour la table `solde_caisse`
--
ALTER TABLE `solde_caisse`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`date_solde`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `sorties`
--
ALTER TABLE `sorties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `utilisateur_id` (`utilisateur_id`);

--
-- Index pour la table `type_frais`
--
ALTER TABLE `type_frais`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `caisse`
--
ALTER TABLE `caisse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT pour la table `categories_caisse`
--
ALTER TABLE `categories_caisse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `classe`
--
ALTER TABLE `classe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `etudiants`
--
ALTER TABLE `etudiants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `frais`
--
ALTER TABLE `frais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `solde_caisse`
--
ALTER TABLE `solde_caisse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `sorties`
--
ALTER TABLE `sorties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `type_frais`
--
ALTER TABLE `type_frais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `caisse`
--
ALTER TABLE `caisse`
  ADD CONSTRAINT `caisse_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `caisse_ibfk_2` FOREIGN KEY (`paiement_id`) REFERENCES `paiements` (`id`);

--
-- Contraintes pour la table `etudiants`
--
ALTER TABLE `etudiants`
  ADD CONSTRAINT `fk_etudiant_classe` FOREIGN KEY (`classe_id`) REFERENCES `classe` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `paiements`
--
ALTER TABLE `paiements`
  ADD CONSTRAINT `paiements_ibfk_1` FOREIGN KEY (`etudiant_id`) REFERENCES `etudiants` (`id`),
  ADD CONSTRAINT `paiements_ibfk_2` FOREIGN KEY (`frais_id`) REFERENCES `frais` (`id`),
  ADD CONSTRAINT `paiements_ibfk_3` FOREIGN KEY (`operation_caisse_id`) REFERENCES `caisse` (`id`);

--
-- Contraintes pour la table `solde_caisse`
--
ALTER TABLE `solde_caisse`
  ADD CONSTRAINT `solde_caisse_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `sorties`
--
ALTER TABLE `sorties`
  ADD CONSTRAINT `sorties_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
