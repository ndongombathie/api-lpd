<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Identifiants de connexion</title>
    <style>
        body { font-family: Arial, sans-serif; color: #222; }
        .container { max-width: 640px; margin: 0 auto; padding: 16px; }
        .box { background: #f7f7f7; padding: 12px; border-radius: 8px; }
        .muted { color: #666; font-size: 12px; }
    </style>
    </head>
<body>
<div class="container">
    <h2>Bienvenue, {{ $user->prenom }} {{ $user->nom }}</h2>
    <p>Votre compte a été créé avec succès. Voici vos identifiants de connexion :</p>
    <div class="box">
        <p><strong>Email :</strong> {{ $user->email }}</p>
        <p><strong>Mot de passe :</strong> {{ $plainPassword }}</p>
    </div>

    <p class="muted">Par mesure de sécurité, nous vous recommandons de changer ce mot de passe dès votre première connexion.</p>

    <p>Merci et bonne journée,</p>
    <p>L’équipe Support</p>
</div>
</body>
</html>