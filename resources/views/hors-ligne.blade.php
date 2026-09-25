<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1f6f3a">
    <title>Hors connexion</title>
    {{-- Page autonome (styles intégrés) : elle doit s'afficher sans aucune autre ressource. --}}
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #f4f7f2; color: #1e293b; padding: 24px; box-sizing: border-box; }
        .carte { max-width: 420px; text-align: center; background: #fff; border-radius: 24px; padding: 32px 24px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        img { width: 88px; height: 88px; object-fit: contain; }
        h1 { font-size: 20px; margin: 16px 0 8px; color: #1f6f3a; }
        p { color: #475569; line-height: 1.5; font-size: 15px; }
        button, a { display: inline-block; margin-top: 12px; min-height: 48px; padding: 12px 20px; border-radius: 14px; border: 0; background: #1f6f3a; color: #fff; font-weight: 600; font-size: 15px; text-decoration: none; cursor: pointer; }
        a.sec { background: #fff; color: #1f6f3a; border: 1px solid #bbdfc2; }
    </style>
</head>
<body>
    <div class="carte">
        <img src="/logo.png" alt="">
        <h1>Serveur injoignable</h1>
        <p>Le serveur de la coopérative ne répond pas : pas de connexion Internet, ou application arrêtée (sur l'ordinateur, vérifiez que Laragon est démarré et utilisez l'adresse <strong>https://coomuni.sn</strong>). Les pages personnelles déjà consultées restent lisibles.</p>
        <p><strong>Par sécurité, aucun paiement ni opération de caisse ne peut être enregistré hors connexion.</strong></p>
        <button type="button" onclick="location.reload()">Réessayer</button><br>
        <a class="sec" href="/mon-historique">Mon historique (dernière version consultée)</a>
    </div>
</body>
</html>
