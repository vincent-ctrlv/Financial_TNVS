<?php
require_once __DIR__ . '/../src/bootstrap.php';
$loggedIn = is_logged_in();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SakaySaya - TNVS Finance Ecosystem</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Main styles -->
    <link rel="stylesheet" href="assets/css/style.css?v=1.0.5">
    
    <style>
        /* Landing Page Custom Styling - Light Mode */
        :root {
            --font-display: 'Outfit', sans-serif;
            --font-sans: 'Inter', sans-serif;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-600: #475569;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --primary-green: #28a745;
            --primary-glow: rgba(40, 167, 69, 0.15);
            --header-blur: saturate(180%) blur(20px);
        }

        body {
            font-family: var(--font-sans);
            background: var(--slate-50);
            color: var(--slate-900);
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* Typography */
        h1, h2, h3, h4 {
            font-family: var(--font-display);
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        /* Glassmorphic Navigation */
        .landing-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: var(--header-blur);
            -webkit-backdrop-filter: var(--header-blur);
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 5%;
            transition: all var(--transition-normal);
        }

        .landing-header.scrolled {
            background: rgba(255, 255, 255, 0.85);
            height: 70px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--slate-900);
        }

        .logo-text {
            font-family: var(--font-display);
            font-size: 26px;
            font-weight: 900;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1.5px;
            text-shadow: 0 0 30px rgba(40, 167, 69, 0.15);
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 32px;
            list-style: none;
        }

        .nav-link {
            color: var(--slate-700);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color var(--transition-normal);
            position: relative;
        }

        .nav-link:hover {
            color: #20c997;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 0;
            width: 0;
            height: 2px;
            background: #20c997;
            transition: width var(--transition-normal);
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .header-btn {
            background: var(--gradient-primary);
            color: #ffffff;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.15);
            transition: all var(--transition-normal);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }

        .header-btn-secondary {
            background: rgba(15, 23, 42, 0.04);
            color: var(--slate-900);
            border: 1px solid rgba(15, 23, 42, 0.08);
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: all var(--transition-normal);
        }

        .header-btn-secondary:hover {
            background: rgba(15, 23, 42, 0.08);
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 120px 5% 80px;
            background: linear-gradient(rgba(248, 250, 252, 0.85), rgba(248, 250, 252, 0.95)), 
                        url('assets/img/login_bg.png') no-repeat center center;
            background-size: cover;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -20%;
            left: -10%;
            width: 50%;
            height: 70%;
            background: radial-gradient(circle, rgba(40, 167, 69, 0.08) 0%, transparent 60%);
            pointer-events: none;
            z-index: 1;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -20%;
            right: -10%;
            width: 50%;
            height: 70%;
            background: radial-gradient(circle, rgba(32, 201, 151, 0.06) 0%, transparent 60%);
            pointer-events: none;
            z-index: 1;
        }

        .hero-container {
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 60px;
            align-items: center;
            z-index: 10;
        }

        .hero-content {
            animation: heroFadeIn 0.8s ease-out;
        }

        @keyframes heroFadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(40, 167, 69, 0.08);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: #1e7e34;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 24px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .hero-badge span {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #28a745;
            box-shadow: 0 0 10px #28a745;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.5); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(40, 167, 69, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }

        .hero-title {
            font-size: 64px;
            line-height: 1.1;
            font-weight: 900;
            color: var(--slate-900);
            margin-bottom: 24px;
            letter-spacing: -2px;
        }

        .hero-title span {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-description {
            font-size: 18px;
            line-height: 1.6;
            color: var(--slate-600);
            margin-bottom: 40px;
            max-width: 600px;
        }

        .hero-actions {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .hero-btn-primary {
            background: var(--gradient-primary);
            color: #ffffff;
            padding: 16px 36px;
            border-radius: 16px;
            font-weight: 800;
            font-size: 16px;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.2);
            transition: all var(--transition-normal);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .hero-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(40, 167, 69, 0.4);
        }

        .hero-btn-secondary {
            background: rgba(15, 23, 42, 0.04);
            color: var(--slate-900);
            border: 1px solid rgba(15, 23, 42, 0.08);
            padding: 16px 36px;
            border-radius: 16px;
            font-weight: 800;
            font-size: 16px;
            text-decoration: none;
            transition: all var(--transition-normal);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .hero-btn-secondary:hover {
            background: rgba(15, 23, 42, 0.08);
            transform: translateY(-3px);
            border-color: rgba(15, 23, 42, 0.12);
        }

        /* Hero Graphic (Glass card mockup) */
        .hero-graphic {
            position: relative;
            animation: heroFloat 6s ease-in-out infinite;
        }

        @keyframes heroFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }

        .glass-mockup {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 24px;
            padding: 32px;
            backdrop-filter: blur(15px);
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.08);
            position: relative;
            z-index: 5;
        }

        .mockup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
            padding-bottom: 16px;
        }

        .mockup-dots {
            display: flex;
            gap: 6px;
        }

        .mockup-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .mockup-dot:nth-child(1) { background: #dc3545; }
        .mockup-dot:nth-child(2) { background: #ffc107; }
        .mockup-dot:nth-child(3) { background: #28a745; }

        .mockup-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--slate-800);
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .mockup-kpi {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(15, 23, 42, 0.04);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .mockup-kpi h4 {
            font-size: 11px;
            color: var(--slate-600);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .mockup-kpi .value {
            font-size: 26px;
            font-weight: 900;
            color: #28a745;
            text-shadow: 0 0 15px rgba(40, 167, 69, 0.15);
        }

        .mockup-chart-row {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            height: 80px;
            margin-top: 15px;
        }

        .mockup-chart-bar {
            flex: 1;
            background: rgba(40, 167, 69, 0.1);
            border-top: 2px solid #28a745;
            border-radius: 4px 4px 0 0;
            transition: height 1s ease-in-out;
            animation: fillBar 1.5s ease-out forwards;
        }

        @keyframes fillBar {
            from { height: 0; }
        }

        /* Stats Section */
        .stats-section {
            padding: 80px 5%;
            background: var(--slate-100);
            position: relative;
            border-top: 1px solid rgba(15, 23, 42, 0.05);
            border-bottom: 1px solid rgba(15, 23, 42, 0.05);
        }

        .stats-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 32px;
            text-align: center;
        }

        .stat-item {
            padding: 20px;
        }

        .stat-number {
            font-size: 48px;
            font-weight: 900;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        .stat-label {
            font-size: 15px;
            color: var(--slate-700);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Features Section */
        .features {
            padding: 100px 5%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-subtitle {
            color: #28a745;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 42px;
            font-weight: 900;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }

        .section-description {
            color: var(--slate-700);
            font-size: 16px;
            max-width: 600px;
            margin: 0 auto;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }

        @media (max-width: 868px) {
            .roles-grid {
                grid-template-columns: 1fr;
            }
            .hero-container {
                grid-template-columns: 1fr;
                gap: 40px;
                text-align: center;
            }
            .hero-actions {
                justify-content: center;
            }
            .hero-title {
                font-size: 46px;
            }
        }

        .role-card {
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 24px;
            padding: 40px;
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: transparent;
            transition: background var(--transition-normal);
        }

        .role-card:hover {
            transform: translateY(-5px);
            border-color: rgba(40, 167, 69, 0.3);
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.06);
            background: rgba(255, 255, 255, 0.95);
        }

        .role-card.driver-focus:hover::before {
            background: var(--gradient-primary);
        }

        .role-card.admin-focus:hover::before {
            background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
        }

        .role-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 28px;
            font-size: 24px;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        .role-card.admin-focus .role-icon {
            background: rgba(32, 201, 151, 0.1);
            color: #20c997;
            border-color: rgba(32, 201, 151, 0.2);
        }

        .role-title {
            font-size: 24px;
            margin-bottom: 16px;
        }

        .role-desc {
            color: var(--slate-700);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .feature-list {
            list-style: none;
            display: grid;
            gap: 16px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: var(--slate-700);
            font-weight: 500;
        }

        .feature-check {
            color: #28a745;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .role-card.admin-focus .feature-check {
            color: #20c997;
        }

        /* CTA Bottom Section */
        .cta-bottom {
            padding: 100px 5%;
            text-align: center;
            background: linear-gradient(to top, var(--slate-50) 0%, var(--slate-100) 100%);
            position: relative;
            border-top: 1px solid rgba(15, 23, 42, 0.05);
        }

        .cta-box {
            max-width: 900px;
            margin: 0 auto;
            background: radial-gradient(circle at top right, rgba(40, 167, 69, 0.05), transparent), rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 32px;
            padding: 60px 40px;
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.04);
        }

        .cta-box h3 {
            font-size: 38px;
            margin-bottom: 16px;
        }

        .cta-box p {
            color: var(--slate-700);
            font-size: 16px;
            max-width: 550px;
            margin: 0 auto 36px;
            line-height: 1.6;
        }

        /* Landing Footer */
        .landing-footer {
            background: var(--slate-50);
            padding: 60px 5% 30px;
            border-top: 1px solid rgba(15, 23, 42, 0.05);
            color: var(--slate-700);
            font-size: 14px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 40px;
        }

        .footer-branding {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .footer-logo {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 900;
            color: #28a745;
            letter-spacing: -1px;
            text-decoration: none;
        }

        .footer-links {
            display: flex;
            gap: 24px;
            list-style: none;
        }

        .footer-link a {
            color: var(--slate-600);
            text-decoration: none;
            transition: color var(--transition-normal);
        }

        .footer-link a:hover {
            color: #20c997;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 0 auto;
            border-top: 1px solid rgba(15, 23, 42, 0.05);
            padding-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            color: var(--slate-600);
            font-size: 13px;
        }
    </style>
</head>
<body>

    <!-- Header / Nav -->
    <header class="landing-header" id="landingHeader">
        <a href="#" class="logo-container">
            <span class="logo-text">SAKAYSAYA</span>
        </a>

        <ul class="nav-menu">
            <li><a href="#features" class="nav-link">Features</a></li>
            <li><a href="#statistics" class="nav-link">Stats</a></li>
            <?php if ($loggedIn): ?>
                <li><a href="dashboard.php" class="header-btn">Dashboard</a></li>
                <li><a href="auth/logout.php" class="header-btn-secondary">Sign Out</a></li>
            <?php else: ?>
                <li><a href="auth/login.php" class="header-btn">Sign In</a></li>
            <?php endif; ?>
        </ul>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <div class="hero-badge">
                    <span></span> Live Financial Ecosystem
                </div>
                <h1 class="hero-title">
                    Drive Smarter. <br>Manage <span>Faster</span>.
                </h1>
                <p class="hero-description">
                    SakaySaya Finance is a premium financial management suite built for the TNVS sector. Seamlessly track fares, leases, incentives, and platform expenses in real-time.
                </p>
                <div class="hero-actions">
                    <?php if ($loggedIn): ?>
                        <a href="dashboard.php" class="hero-btn-primary">
                            Go to Dashboard
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                    <?php else: ?>
                        <a href="auth/login.php" class="hero-btn-primary">
                            Access Portal
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                        <a href="#features" class="hero-btn-secondary">Explore Features</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hero-graphic">
                <div class="glass-mockup">
                    <div class="mockup-header">
                        <div class="mockup-dots">
                            <span class="mockup-dot"></span>
                            <span class="mockup-dot"></span>
                            <span class="mockup-dot"></span>
                        </div>
                        <div class="mockup-title">Live Preview</div>
                    </div>
                    
                    <div class="mockup-kpi">
                        <h4>Driver Gross Earnings</h4>
                        <div class="value" id="grossEarningsVal">₱24,850.00</div>
                    </div>

                    <div class="mockup-kpi" style="margin-bottom: 0;">
                        <h4>Weekly Disbursements</h4>
                        <div class="value" style="color: #20c997; text-shadow: 0 0 15px rgba(32, 201, 151, 0.3);">₱15,200.00</div>
                        
                        <div class="mockup-chart-row">
                            <div class="mockup-chart-bar" style="height: 40px; animation-delay: 0.1s;"></div>
                            <div class="mockup-chart-bar" style="height: 65px; animation-delay: 0.2s;"></div>
                            <div class="mockup-chart-bar" style="height: 50px; animation-delay: 0.3s;"></div>
                            <div class="mockup-chart-bar" style="height: 75px; animation-delay: 0.4s;"></div>
                            <div class="mockup-chart-bar" style="height: 80px; animation-delay: 0.5s;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section" id="statistics">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">₱12.8M+</div>
                <div class="stat-label">Fares Tracked</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">1,200+</div>
                <div class="stat-label">Active TNVS Drivers</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">99.9%</div>
                <div class="stat-label">Payout Delivery Rate</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">&lt; 3 Min</div>
                <div class="stat-label">Cash-out Processing</div>
            </div>
        </div>
    </section>

    <!-- Features Showcase -->
    <section class="features" id="features">
        <div class="section-header">
            <div class="section-subtitle">Tailored Workspace</div>
            <h2 class="section-title">Designed for Drivers & Admins</h2>
            <p class="section-description">
                Experience dual-role integration custom-engineered to bridge the gap between drivers on the road and fleet back-office administrators.
            </p>
        </div>

        <div class="roles-grid">
            <!-- Driver Card -->
            <div class="role-card driver-focus">
                <div class="role-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                </div>
                <h3 class="role-title">Driver Portal</h3>
                <p class="role-desc">
                    Empower drivers with a secure, highly responsive mobile-friendly portal to log, track, and manage their earnings on the road.
                </p>
                <ul class="feature-list">
                    <li class="feature-item">
                        <span class="feature-check">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </span>
                        Track Gross Fares & Incentive Bonuses
                    </li>
                    <li class="feature-item">
                        <span class="feature-check">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </span>
                        Log Fuel, Lease & Platform Commission Expenses
                    </li>
                    <li class="feature-item">
                        <span class="feature-check">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </span>
                        Request & Track Payout Cashouts in Real Time
                    </li>
                    <li class="feature-item">
                        <span class="feature-check">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </span>
                        Detailed Personal Financial Statement Breakdown
                    </li>
                </ul>
            </div>

            <!-- Admin/Finance Card -->
            <div class="role-card admin-focus">
                <div class="role-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="9"></line><line x1="9" y1="13" x2="15" y2="13"></line><line x1="9" y1="17" x2="15" y2="17"></line></svg>
                </div>
                <h3 class="role-title">Admin & Finance Console</h3>
                <p class="role-desc">
                    Full-featured control panel built for managers and back-office staff to monitor collections, reconciliation, and audit logs.
                </p>
                <ul class="feature-list">
                    <li class="feature-item">
                        <span class="feature-check">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </span>
                        Manage Accounts Receivable (AR) & Payable (AP)
                    </li>
                    <li class="feature-item">
                        <span class="feature-check">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </span>
                        Automated Collection & Disbursement Ledger
                    </li>
                    <li class="feature-item">
                        <span class="feature-check">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </span>
                        Comprehensive Platform Audit Logging & Analytics
                    </li>
                    <li class="feature-item">
                        <span class="feature-check">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        </span>
                        Driver Accounts & Reference ID Management
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Bottom Call-To-Action -->
    <section class="cta-bottom">
        <div class="cta-box">
            <h3>Ready to Supercharge Your TNVS Finance?</h3>
            <p>Join hundreds of drivers and administrators who trust SakaySaya to secure, track, and speed up their daily financial logistics.</p>
            <?php if ($loggedIn): ?>
                <a href="dashboard.php" class="hero-btn-primary">Go to Dashboard</a>
            <?php else: ?>
                <a href="auth/login.php" class="hero-btn-primary">Get Started Now</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="landing-footer">
        <div class="footer-container">
            <div class="footer-branding">
                <a href="#" class="footer-logo">SAKAYSAYA</a>
                <p style="color: var(--slate-600); max-width: 300px; line-height: 1.5; margin-top: 8px;">Premium financial ledger and automated collection suite for TNVS fleets.</p>
            </div>
            
            <ul class="footer-links">
                <li class="footer-link"><a href="#features">Features</a></li>
                <li class="footer-link"><a href="#statistics">Statistics</a></li>
                <?php if ($loggedIn): ?>
                    <li class="footer-link"><a href="dashboard.php">Dashboard</a></li>
                <?php else: ?>
                    <li class="footer-link"><a href="auth/login.php">Sign In</a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="footer-bottom">
            <div>&copy; <?php echo date('Y'); ?> SakaySaya Finance System. All rights reserved.</div>
            <div style="display: flex; gap: 15px;">
                <span style="color: #28a745;">● System Operational</span>
                <span>v1.2.0</span>
            </div>
        </div>
    </footer>

    <!-- Header Scroll Script & Counter Animation -->
    <script>
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('landingHeader');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Simulated KPI incrementer just to make the preview feel alive
        (function() {
            let earningsVal = 24850.00;
            const container = document.getElementById('grossEarningsVal');
            if (container) {
                setInterval(function() {
                    const increment = (Math.random() * 80 + 20);
                    earningsVal += increment;
                    container.innerText = '₱' + earningsVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    
                    // Brief green text glow on update
                    container.style.textShadow = '0 0 25px rgba(40, 167, 69, 0.8)';
                    setTimeout(function() {
                        container.style.textShadow = '0 0 15px rgba(40, 167, 69, 0.3)';
                    }, 500);
                }, 4000);
            }
        })();
    </script>
</body>
</html>
