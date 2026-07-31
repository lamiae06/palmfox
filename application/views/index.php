<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PalmFox - Bienvenue</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f6f4f1;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .portal-container {
            text-align: center;
            max-width: 800px;
            width: 100%;
            padding: 20px;
        }
        .portal-logo {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            font-weight: 700;
            color: #1e1712;
            margin-bottom: 10px;
        }
        .portal-logo i { color: #9a6633; margin-right: 10px; }
        .portal-subtitle {
            color: #7a6d5f;
            margin-bottom: 40px;
            font-size: 16px;
        }
        .doors-grid {
            display: flex;
            gap: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .door-card {
            background: #fff;
            border: 2px solid transparent;
            border-radius: 20px;
            width: 280px;
            padding: 40px 30px;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .door-card:hover {
            transform: translateY(-10px);
            border-color: #9a6633;
            box-shadow: 0 15px 40px rgba(154, 102, 51, 0.15);
        }
        .door-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #fdfaf6;
            color: #9a6633;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .door-card:hover .door-icon {
            background: #9a6633;
            color: #fff;
        }
        .door-card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            color: #1e1712;
            margin-bottom: 12px;
        }
        .door-card p {
            font-size: 13px;
            color: #8a7e72;
            line-height: 1.5;
        }
    </style>
</head>
<body>

    <div class="portal-container">
        <div class="portal-logo">
            <i class="fa-solid fa-cubes"></i><span>PalmFox</span>
        </div>
        <p class="portal-subtitle">Veuillez choisir votre espace de connexion pour continuer.</p>

        <div class="doors-grid">
            <a href="login/login.php" class="door-card">
                <div class="door-icon">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <h3>Espace Admin</h3>
                <p>Gestion globale, configuration du système, statistiques et contrôle des accès.</p>
            </a>

            <a href="login/login.php" class="door-card">
                <div class="door-icon">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <h3>Espace Utilisateur</h3>
                <p>Passage et suivi des commandes, consultation du stock et gestion de vos clients.</p>
            </a>
        </div>
    </div>

</body>
</html>