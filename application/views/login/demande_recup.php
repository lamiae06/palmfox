<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mot de passe oublié - PalmFox</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #fdfbf7; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0px 4px 12px rgba(0,0,0,0.08); width: 360px; border: 1px solid #e8ded7; }
        h2 { color: #8c5a3c; margin-top: 0; margin-bottom: 10px; text-align: center; font-size: 20px; }
        p { color: #666; font-size: 13px; text-align: center; margin-bottom: 20px; }
        label { font-weight: bold; font-size: 14px; color: #5c3e2b; display: block; margin-bottom: 5px; }
        input[type="email"] { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #d4c5bc; border-radius: 4px; box-sizing: border-box; background-color: #faf8f5; }
        input[type="email"]:focus { outline: none; border-color: #8c5a3c; }
        button { width: 100%; padding: 12px; background-color: #8c5a3c; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 15px; font-weight: bold; }
        button:hover { background-color: #70462e; }
        .back-link { text-align: center; margin-top: 15px; display: block; color: #8c5a3c; text-decoration: none; font-size: 13px; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Mot de passe oublié</h2>
        <p>Entrez votre adresse email. Nous allons vous envoyer un lien sécurisé par email pour modifier votre mot de passe.</p>
        <form action="traitement_recup.php" method="POST">
            <label for="email">Votre adresse email :</label>
            <input type="email" name="email" id="email" required placeholder="exemple@palmfox.com">
            <button type="submit">Envoyer le lien de récupération</button>
        </form>
        <a href="index.php" class="back-link">Retourner à la page de connexion</a>
    </div>
</body>
</html>