<header class="app-header">
    <div class="header-left" style="display:flex; align-items:center; gap:24px;">
        <button id="menuToggle" class="icon-btn" aria-label="Toggle menu"><span></span><span></span><span></span></button>
        <div class="brand" role="img" aria-label="TNVS Finance System">
            <svg viewBox="0 0 120 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="height:28px; width:auto; filter:drop-shadow(0 0 15px rgba(40,167,69,0.4));">
                <text x="0" y="16" font-size="16" font-weight="900" letter-spacing="0.5" font-family="Inter, Segoe UI, sans-serif" fill="#28a745">SAKAYSAYA</text>
            </svg>
        </div>
    </div>
    <div class="user" style="display:flex; align-items:center; gap:16px;">
        <?php 
        $u = current_user(); 
        $u_name = htmlspecialchars($u['name'] ?? 'User');
        $u_avatar = $u['avatar'] ?? '';
        $u_src = '';
        if ($u_avatar && file_exists(__DIR__ . '/' . $u_avatar)) {
            $u_src = $u_avatar;
        } else {
            $u_initial = strtoupper(substr((string)$u_name, 0, 1));
            $u_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128"><rect width="100%" height="100%" fill="#28a745" fill-opacity="0.1"/><text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Segoe UI, Arial" font-size="64" fill="#28a745" font-weight="800">'.$u_initial.'</text></svg>';
            $u_src = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($u_svg);
        }
        ?>
        <img src="<?php echo $u_src; ?>" alt="head-avatar" style="width:36px; height:36px; border-radius:50%; object-fit:cover; border:2px solid rgba(40,167,69,0.2);">
        <span id="currentUser" style="font-weight:600; color:var(--text); font-size:14px;">Welcome, <?php echo $u_name; ?></span>
        <a href="logout.php" class="text-btn" style="color:var(--muted); font-size:13px; text-decoration:none; font-weight:600; margin-left:8px;">Logout</a>
    </div>
</header>
