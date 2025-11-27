<div class="col-md-3 col-lg-2 sidebar d-md-block">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Tableau de Bord
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'etudiants.php' ? 'active' : ''; ?>" href="etudiants.php">
                    <i class="bi bi-people"></i> Élèves
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'frais.php' ? 'active' : ''; ?>" href="frais.php">
                    <i class="bi bi-cash-coin"></i> Types de Frais
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'paiements.php' ? 'active' : ''; ?>" href="paiements.php">
                    <i class="bi bi-credit-card"></i> Paiements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'caisse.php' ? 'active' : ''; ?>" href="caisse.php">
                    <i class="bi bi-cash-stack"></i> Caisse
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'rapports.php' ? 'active' : ''; ?>" href="rapports.php">
                    <i class="bi bi-graph-up"></i> Rapports
                </a>
            </li>
            <?php if (($_SESSION['role'] ?? '') == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'utilisateurs.php' ? 'active' : ''; ?>" href="utilisateurs.php">
                    <i class="bi bi-person-gear"></i> Utilisateurs
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </div>
</div>