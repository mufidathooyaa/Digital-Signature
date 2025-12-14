<header class="header">
    <div class="nav-container">
        <div class="logo">
            <a href="dashboard.php" style="text-decoration: none; color: inherit; display: flex; align-items: center;">
                <img src="assets/img/logo.png" alt="Logo Aplikasi" style="height: 50px; width: auto;">
                <span>DigiSign</span>
            </a>
        </div>   
        <nav>
            <ul class="nav-menu">
                <li><a href="verifikasi.php">Verifikasi</a></li>

                <?php if (isset($_SESSION['user'])): ?>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    
                    <?php if ($_SESSION['user']['role'] === 'karyawan'): ?>
                        <li><a href="pengajuan.php">Buat Pengajuan</a></li>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['user']['role'] === 'direksi'): ?>
                        <li><a href="kelola_pengajuan.php">Kelola Pengajuan</a></li>
                        <li><a href="generate_key.php">Generate Key</a></li>
                        <li><a href="tanda_tangan.php">Tanda Tangan</a></li>
                        
                        <li><a href="kelola_user.php">Kelola User</a></li> 
                    <?php endif; ?>

                    <li><a href="riwayat.php">Riwayat</a></li>
                    
                    <li class="user-info">
                        <span class="user-badge"><?php echo ucfirst($_SESSION['user']['role']); ?></span>
                        <a href="logout.php" style="color: #ef4444;">Logout</a>
                    </li>
                    
                <?php else: ?>
                    <li><a href="login.php" class="btn btn-primary btn-sm" style="color: white; padding: 8px 15px;">Login Pegawai</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>