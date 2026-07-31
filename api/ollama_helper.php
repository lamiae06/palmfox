<?php
// ============================================================
// HELPER PARTAGÉ : communication avec Ollama (chat.php + rapport_ia.php)
// ============================================================
// Corrige le bug "Aucune réponse." : avant, une erreur Ollama (ex: modèle
// non installé -> HTTP 404) était silencieusement transformée en
// "Aucune réponse." sans aucune indication du vrai problème.
// Désormais on : 1) vérifie le code HTTP et le champ "error" d'Ollama,
// 2) si le modèle demandé n'est pas installé, on regarde les modèles
// réellement disponibles (via /api/tags) et on bascule automatiquement
// dessus si possible, 3) sinon on renvoie un message clair et actionnable
// au lieu de "Aucune réponse.".
// ============================================================

define('OLLAMA_BASE_URL', 'http://127.0.0.1:45845');

/**
 * Récupère la liste des modèles réellement installés sur le serveur Ollama.
 * Retourne un tableau de noms (ex: ["llama3.2:1b", "qwen3:4b"]) ou [] si
 * on ne peut pas contacter Ollama.
 */
function ollamaListerModeles() {
    $ch = curl_init(OLLAMA_BASE_URL . "/api/tags");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return [];
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if (!isset($data['models']) || !is_array($data['models'])) {
        return [];
    }
    return array_map(fn($m) => $m['name'] ?? '', $data['models']);
}

/**
 * Appelle /api/chat sur Ollama et renvoie soit :
 *   ['content' => "..."]   en cas de succès
 *   ['error'   => "..."]   en cas d'échec (message déjà en français, prêt à afficher)
 *
 * $modelePrefere : modèle souhaité (ex: "qwen3:4b")
 * $messages      : tableau de messages au format Ollama (role/content)
 * $options       : options Ollama optionnelles (num_predict, etc.)
 */
function ollamaChat($modelePrefere, $messages, $options = null) {
    $modeles = ollamaListerModeles();

    // Si Ollama ne répond même pas à /api/tags, inutile d'essayer /api/chat
    if (empty($modeles)) {
        return ['error' => "Impossible de joindre le serveur Ollama (" . OLLAMA_BASE_URL . "). " .
            "Vérifiez qu'il est bien démarré (commande : ollama serve)."];
    }

    $modeleUtilise = $modelePrefere;

    // Le modèle demandé n'est pas installé -> on bascule sur un modèle disponible
    if (!in_array($modelePrefere, $modeles, true)) {
        $modeleUtilise = $modeles[0];
        logDebug("Modèle '$modelePrefere' non installé, bascule automatique sur '$modeleUtilise'. " .
            "Pour utiliser le modèle prévu, lancez : ollama pull $modelePrefere", 'error');
    }

    $payloadArray = [
        "model" => $modeleUtilise,
        "messages" => $messages,
        "stream" => false
    ];
    if ($options) {
        $payloadArray['options'] = $options;
    }
    // "think" n'est pertinent que pour les modèles qwen3
    if (strpos($modeleUtilise, 'qwen3') !== false) {
        $payloadArray['think'] = false;
    }

    $ch = curl_init(OLLAMA_BASE_URL . "/api/chat");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadArray));
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $erreur = curl_error($ch);
        curl_close($ch);
        return ['error' => "Erreur de connexion à l'IA : $erreur"];
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    // Ollama renvoie un code HTTP != 200 (souvent 404) quand le modèle
    // n'existe pas, avec un champ "error" expliquant pourquoi.
    if ($httpCode !== 200) {
        $msgErreur = $result['error'] ?? "réponse HTTP $httpCode";
        return ['error' => "Erreur Ollama (modèle '$modeleUtilise') : $msgErreur. " .
            "Modèles installés : " . implode(', ', $modeles) . "."];
    }

    $rawReply = $result["message"]["content"] ?? null;
    if ($rawReply === null) {
        return ['error' => "Réponse Ollama invalide ou vide pour le modèle '$modeleUtilise'."];
    }

    // Nettoyage de la réflexion (<think>...</think>) insensible à la casse et multi-lignes
    $cleanReply = trim(preg_replace('/<think>.*?<\/think>/is', '', $rawReply));

    if ($cleanReply === '') {
        return ['error' => "Le modèle '$modeleUtilise' n'a renvoyé aucun texte exploitable."];
    }

    return ['content' => $cleanReply];
}
