-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 28 nov. 2025 à 18:13
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
-- Structure de la table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL COMMENT 'Identifiant unique de l''entrée',
  `timestamp` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date et heure de l''événement',
  `niveau` enum('INFO','SECURITY','WARNING','ERROR') NOT NULL DEFAULT 'INFO' COMMENT 'Niveau de sévérité du log',
  `utilisateur_id` int(11) DEFAULT NULL COMMENT 'ID de l''utilisateur concerné',
  `message` text NOT NULL COMMENT 'Description détaillée de l''événement',
  `ip_address` varchar(45) NOT NULL COMMENT 'Adresse IP de l''utilisateur (support IPv6)',
  `user_agent` text DEFAULT NULL COMMENT 'User Agent du navigateur',
  `module` varchar(50) DEFAULT NULL COMMENT 'Module concerné (ex: auth, users, payments)',
  `action` varchar(50) DEFAULT NULL COMMENT 'Action effectuée (ex: login, create, update, delete)',
  `entite_id` int(11) DEFAULT NULL COMMENT 'ID de l''entité concernée',
  `entite_type` varchar(50) DEFAULT NULL COMMENT 'Type d''entité concernée (ex: utilisateur, etudiant, paiement)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Date de création de l''enregistrement'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Journal d''audit des activités du système';

--
-- Déchargement des données de la table `audit_log`
--

INSERT INTO `audit_log` (`id`, `timestamp`, `niveau`, `utilisateur_id`, `message`, `ip_address`, `user_agent`, `module`, `action`, `entite_id`, `entite_type`, `created_at`) VALUES
(1, '2025-11-28 17:56:26', 'INFO', 1, 'Connexion réussie de l\'administrateur', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'auth', 'login', 1, 'utilisateur', '2025-11-28 16:56:26'),
(2, '2025-11-28 17:56:26', 'SECURITY', 1, 'Nouvel utilisateur créé: caissier1', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'users', 'create', 2, 'utilisateur', '2025-11-28 16:56:26'),
(3, '2025-11-28 17:56:26', 'INFO', 2, 'Connexion réussie du caissier', '192.168.1.101', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36', 'auth', 'login', 2, 'utilisateur', '2025-11-28 16:56:26'),
(4, '2025-11-28 17:56:26', 'WARNING', NULL, 'Tentative de connexion échouée pour utilisateur: unknown', '192.168.1.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'auth', 'login_failed', NULL, 'utilisateur', '2025-11-28 16:56:26'),
(5, '2025-11-28 17:56:26', 'INFO', 1, 'Déconnexion utilisateur', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'auth', 'logout', 1, 'utilisateur', '2025-11-28 16:56:26'),
(6, '2025-11-28 17:56:26', 'INFO', 1, 'Nouvel étudiant ajouté: MAT2024001', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'etudiants', 'create', 1, 'etudiant', '2025-11-28 16:56:26'),
(7, '2025-11-28 17:56:26', 'INFO', 2, 'Paiement enregistré pour l\'étudiant MAT2024001', '192.168.1.101', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36', 'paiements', 'create', 1, 'paiement', '2025-11-28 16:56:26'),
(8, '2025-11-28 17:56:26', 'ERROR', 1, 'Erreur lors de la suppression de l\'étudiant: contraintes de clé étrangère', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'etudiants', 'delete', 3, 'etudiant', '2025-11-28 16:56:26');

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
(1, 'dépôt', 500.00, '2025-11-28 00:00:00', 'espèces', 'Paiement Frais d\'inscription - EMBONGO BONKANGU Nissi (MAT2025053) - 1 ère A (Niv. Humanitaire)', 'REF0015', 2, '2025-11-28 16:10:28', '', 'validé', 1);

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
(1, '1 ère A', 'Humanitaire', 'Scientifique', 100, '2025-2026', '2025-11-28 16:08:51', '2025-11-28 16:08:51');

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
(1, 'MAT2025053', 'EMBONGO BONKANGU', 'Nissi', '+243812796152', 'nissiembongo06@gmail.com', '', '2025-11-28', 1);

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
(1, 'Frais d\'inscription', 500.00, 'Frais lié à la scolarité', '2025-2026');

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
(1, 1, 1, 500.00, '2025-11-28', 'espèces', 'REF0015', 'payé', 1);

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
(1, '2025-11-28', 0.00, 0.00, 0.00, 0.00, 0, 'ouvert', 2, NULL);

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
  `role` enum('admin','caissier') DEFAULT 'caissier',
  `statut` varchar(15) NOT NULL,
  `derniere_connexion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `username`, `password`, `nom_complet`, `role`, `statut`, `derniere_connexion`) VALUES
(1, 'admin', '$2y$10$V5t.7GnVl3XDlNPe05Z67OTL1r/Q/U7gb29bvAc77uGIvdm76ihQW', 'Administrateur Principal', 'admin', 'actif', '2025-11-28 18:12:05'),
(2, 'Nissi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nissi Embongo', 'admin', 'actif', '2025-11-28 17:23:22');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_timestamp` (`timestamp`),
  ADD KEY `idx_audit_niveau` (`niveau`),
  ADD KEY `idx_audit_utilisateur` (`utilisateur_id`),
  ADD KEY `idx_audit_ip` (`ip_address`),
  ADD KEY `idx_audit_module` (`module`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_entite` (`entite_type`,`entite_id`),
  ADD KEY `idx_audit_created` (`created_at`);

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
-- AUTO_INCREMENT pour la table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Identifiant unique de l''entrée', AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `caisse`
--
ALTER TABLE `caisse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `categories_caisse`
--
ALTER TABLE `categories_caisse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `classe`
--
ALTER TABLE `classe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `etudiants`
--
ALTER TABLE `etudiants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `frais`
--
ALTER TABLE `frais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `solde_caisse`
--
ALTER TABLE `solde_caisse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
