<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . "/../config/configuration.php";
require_once __DIR__ . "/ollama_helper.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "Non connecté"]);
    exit;
}

$userId = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);
$message = trim($data['message'] ?? '');
$conversationId = intval($data['conversation_id'] ?? 0);

// ============================================================
// ACTION SPÉCIALE : rapport d'analyse des conversations.
// Ajout pur, ne touche à aucune ligne existante. Déclenché en
// envoyant { "action": "rapport" } (au lieu de "message") dans
// le corps de la requête POST vers ce même chat.php.
// Ex: { "action": "rapport", "debut": "2026-07-01", "fin": "2026-07-31" }
// ============================================================
if (($data['action'] ?? '') === 'rapport') {
    genererRapport($conn, $userRole, $data['debut'] ?? null, $data['fin'] ?? null);
    exit;
}

if (empty($message)) {
    echo json_encode(["reply" => "Message vide."]);
    exit;
}

// ============================================================
// UTILITAIRE : normaliser le texte (minuscule + sans accents)
// ============================================================
function normaliser($texte) {
    $texte = mb_strtolower($texte, 'UTF-8');
    $remplacements = ['à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
                      'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c'];
    return strtr($texte, $remplacements);
}

$msgNorm = normaliser($message);

// ============================================================
// 1. STATISTIQUES GLOBALES (requêtes SQL directes, réponse exacte)
// ============================================================
function reponseStatistiques($msgNorm, $conn) {

    // --- Nombre de clients ---
    if (preg_match('/(combien|nombre).*(client)/u', $msgNorm)) {
        $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM client");
        $row = mysqli_fetch_assoc($res);
        return "Vous avez actuellement {$row['total']} client(s) enregistré(s).";
    }

    // --- Nombre de commandes (total ou par statut) ---
    if (preg_match('/(combien|nombre).*(commande)/u', $msgNorm)) {
        if (strpos($msgNorm, 'attente') !== false) {
            $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM commande WHERE statut = 'En attente'");
            $row = mysqli_fetch_assoc($res);
            return "Il y a {$row['total']} commande(s) en attente.";
        }
        if (strpos($msgNorm, 'livree') !== false) {
            $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM commande WHERE statut = 'Livrée'");
            $row = mysqli_fetch_assoc($res);
            return "Il y a {$row['total']} commande(s) livrée(s).";
        }
        if (strpos($msgNorm, 'confirmee') !== false) {
            $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM commande WHERE statut = 'Confirmée'");
            $row = mysqli_fetch_assoc($res);
            return "Il y a {$row['total']} commande(s) confirmée(s).";
        }
        if (strpos($msgNorm, 'annulee') !== false) {
            $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM commande WHERE statut = 'Annulée'");
            $row = mysqli_fetch_assoc($res);
            return "Il y a {$row['total']} commande(s) annulée(s).";
        }
        $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM commande");
        $row = mysqli_fetch_assoc($res);
        return "Vous avez actuellement {$row['total']} commande(s) au total.";
    }

    // --- Stock / produits ---
    if (preg_match('/(combien|nombre).*(produit)/u', $msgNorm) || strpos($msgNorm, 'stock') !== false) {
        if (strpos($msgNorm, 'rupture') !== false) {
            $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM produit WHERE quantite_stock = 0");
            $row = mysqli_fetch_assoc($res);
            return "Il y a {$row['total']} produit(s) en rupture de stock.";
        }
        if (strpos($msgNorm, 'faible') !== false) {
            $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM produit WHERE quantite_stock > 0 AND quantite_stock <= 5");
            $row = mysqli_fetch_assoc($res);
            return "Il y a {$row['total']} produit(s) en stock faible (5 unités ou moins).";
        }
        $res = mysqli_query($conn, "SELECT COUNT(*) as nb_produits, SUM(quantite_stock) as total_stock FROM produit");
        $row = mysqli_fetch_assoc($res);
        return "Vous avez {$row['nb_produits']} produit(s) référencé(s), pour un stock total de " . ($row['total_stock'] ?? 0) . " unité(s).";
    }

    // --- Livraisons ---
    if (preg_match('/(combien|nombre).*(livraison)/u', $msgNorm)) {
        $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM livraison");
        $row = mysqli_fetch_assoc($res);
        return "Il y a {$row['total']} livraison(s) enregistrée(s).";
    }

    // --- Utilisateurs / employés ---
    if (preg_match('/(combien|nombre).*(employe|utilisateur)/u', $msgNorm)) {
        $res = mysqli_query($conn, "SELECT COUNT(*) as total FROM utilisateurs");
        $row = mysqli_fetch_assoc($res);
        return "Il y a {$row['total']} utilisateur(s) enregistré(s) dans le système.";
    }

    return null; // aucune correspondance
}

// ============================================================
// 2. RECHERCHE PRÉCISE (client par nom, commande par numéro, produit par référence)
// ============================================================
function reponseRecherche($message, $msgNorm, $conn) {

    // --- Infos d'un client par nom ---
    if (preg_match('/client\s+([a-zA-Zà-ÿ\-]+)/ui', $message, $m)) {
        $nomRecherche = $m[1];
        $stmt = mysqli_prepare($conn, "SELECT nom, email, telephone, mode_paiement, condition_paiement FROM client WHERE nom LIKE ?");
        $like = "%$nomRecherche%";
        mysqli_stmt_bind_param($stmt, "s", $like);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($res)) {
            return "Client {$row['nom']} : email {$row['email']}, téléphone {$row['telephone']}, paiement {$row['mode_paiement']}" .
                   ($row['condition_paiement'] ? " ({$row['condition_paiement']})" : "") . ".";
        }
        return "Aucun client trouvé avec le nom \"$nomRecherche\".";
    }

    // --- Statut d'une commande par numéro (avec détail des produits) ---
    if (preg_match('/commande\s*(n[°o]?|numero|#)?\s*(\d+)/ui', $msgNorm, $m)) {
        $idCommande = intval($m[2]);
        $stmt = mysqli_prepare($conn, "
            SELECT c.id_commande, c.statut, c.date_livraison, c.delai, cl.nom 
            FROM commande c 
            JOIN client cl ON c.id_client = cl.id_client 
            WHERE c.id_commande = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $idCommande);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($res)) {
            $dateLivraison = $row['date_livraison'] ? " prévue le {$row['date_livraison']}" : "";

            // Récupérer les produits associés
            $stmtP = mysqli_prepare($conn, "
                SELECT p.reference, co.Quantite_produit 
                FROM concerner co 
                JOIN produit p ON co.id_produit = p.id_produit 
                WHERE co.id_commande = ?
            ");
            mysqli_stmt_bind_param($stmtP, "i", $idCommande);
            mysqli_stmt_execute($stmtP);
            $resP = mysqli_stmt_get_result($stmtP);
            $lignesProduits = [];
            while ($p = mysqli_fetch_assoc($resP)) {
                $lignesProduits[] = "{$p['Quantite_produit']}x {$p['reference']}";
            }
            $detailProduits = count($lignesProduits) > 0 ? " Produits : " . implode(', ', $lignesProduits) . "." : "";

            return "Commande n°{$row['id_commande']} (client {$row['nom']}) : statut \"{$row['statut']}\"{$dateLivraison}, délai {$row['delai']}.{$detailProduits}";
        }
        return "Aucune commande trouvée avec le numéro $idCommande.";
    }

    // --- Infos d'un produit par référence ---
    if (preg_match('/produit\s+([a-z0-9\-]+)/ui', $msgNorm, $m)) {
        $ref = $m[1];
        $stmt = mysqli_prepare($conn, "SELECT reference, code, description, quantite_stock FROM produit WHERE reference LIKE ?");
        $like = "%$ref%";
        mysqli_stmt_bind_param($stmt, "s", $like);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            return "Produit {$row['reference']} (code {$row['code']}) : {$row['quantite_stock']} unité(s) en stock. Description : {$row['description']}.";
        }
        return "Aucun produit trouvé avec la référence \"$ref\".";
    }

    return null;
}

// ============================================================
// 3. FAQ "COMMENT FAIRE" (réponses statiques écrites par toi)
// ============================================================
function reponseFAQ($msgNorm) {
    $faq = [
        [
            'mots' => ['ajouter client', 'creer client', 'nouveau client'],
            'reponse' => "Pour ajouter un client : allez dans le menu \"Clients\", cliquez sur \"Ajouter un client\", remplissez le nom, l'email, le téléphone et le mode de paiement, puis cliquez sur \"Enregistrer\"."
        ],
        [
            'mots' => ['creer commande', 'ajouter commande', 'nouvelle commande', 'passer commande'],
            'reponse' => "Pour créer une commande : allez dans \"Commandes\", cliquez sur \"Nouvelle commande\", sélectionnez le client, ajoutez les produits avec leurs quantités, puis validez."
        ],
        [
            'mots' => ['ajouter produit', 'creer produit', 'nouveau produit'],
            'reponse' => "Pour ajouter un produit : allez dans \"Produits\", cliquez sur \"Ajouter un produit\", renseignez la référence, le code, la description et le stock, puis enregistrez."
        ],
        [
            'mots' => ['creer livraison', 'ajouter livraison', 'nouvelle livraison'],
            'reponse' => "Pour créer une livraison : allez dans \"Livraisons\", sélectionnez la commande concernée, puis mettez à jour son statut de livraison."
        ],
        [
            'mots' => ['ajouter employe', 'ajouter utilisateur', 'creer compte', 'nouvel employe'],
            'reponse' => "Pour ajouter un employé : allez dans \"Gestion des accès\", cliquez sur \"Ajouter un compte\", renseignez nom d'utilisateur, email, mot de passe et rôle, puis créez le compte."
        ],
        [
            'mots' => ['modifier permission', 'gerer acces', 'changer role'],
            'reponse' => "Pour modifier les permissions d'un utilisateur : allez dans \"Gestion des accès\", trouvez l'utilisateur dans la liste, puis ajustez ses accès par page."
        ],
    ];

    foreach ($faq as $entree) {
        foreach ($entree['mots'] as $motCle) {
            if (strpos($msgNorm, normaliser($motCle)) !== false) {
                return $entree['reponse'];
            }
        }
    }

    return null;
}

// ============================================================
// UTILITAIRE : extraction flexible nom / téléphone / email
// depuis un message en langage naturel (pas besoin du format
// strict "nom: X, tel: Y").
// ============================================================
function extraireInfosPersonne($message) {
    $infos = ['nom' => null, 'telephone' => null, 'email' => null];
    $reste = $message;

    // Email
    if (preg_match('/[a-zA-Z0-9_.+\-]+@[a-zA-Z0-9\-]+\.[a-zA-Z0-9\-.]+/', $message, $m)) {
        $infos['email'] = $m[0];
        $reste = str_replace($m[0], ' ', $reste);
    }

    // Téléphone (accepte espaces, tirets, +, au moins 8 chiffres)
    if (preg_match('/(\+?\d[\d\s\-]{7,14}\d)/', $reste, $m)) {
        $infos['telephone'] = trim($m[0]);
        $reste = str_replace($m[0], ' ', $reste);
    }

    // Mode de paiement (mot-clé explicite)
    $modePaiement = null;
    if (preg_match('/paiement\s*:?\s*([a-zà-ÿ]+)/ui', $reste, $m)) {
        $modePaiement = ucfirst(trim($m[1]));
        $reste = str_replace($m[0], ' ', $reste);
    }
    $infos['mode_paiement'] = $modePaiement ?: 'Cash';

    // Ce qui reste = probablement le nom : on nettoie les mots-outils
    $motsAIgnorer = ['ajoute', 'ajouter', 'creer', 'crée', 'client', 'nouveau', 'nouvelle',
                     'le', 'la', 'un', 'une', 'nom', 'tel', 'telephone', 'téléphone', ':', ','];
    $mots = preg_split('/\s+/', trim($reste));
    $motsGardes = [];
    foreach ($mots as $mot) {
        $motPropre = trim($mot, " \t\n\r\0\x0B,:.");
        if ($motPropre === '') continue;
        if (in_array(normaliser($motPropre), $motsAIgnorer)) continue;
        $motsGardes[] = $motPropre;
    }
    $nom = trim(implode(' ', $motsGardes));
    $infos['nom'] = $nom !== '' ? $nom : null;

    return $infos;
}

// Détecte si le dernier message du bot demandait le format
// d'ajout de client, pour interpréter la réponse suivante
// comme une continuation (mémoire conversationnelle).
function attendAjoutClient($historiqueAvant) {
    if (empty($historiqueAvant)) return false;
    $dernier = end($historiqueAvant);
    if ($dernier['role'] !== 'assistant') return false;
    return (strpos($dernier['content'], "ajouter un client") !== false
         && strpos($dernier['content'], "numéro de téléphone") !== false);
}

// ============================================================
// 3.5 ACTIONS D'ÉCRITURE (Ajout, Modification, Suppression)
// ============================================================
function executerActionEcriture($message, $msgNorm, $conn, $historiqueAvant = []) {
    try {
        // A. MODIFICATIONS (UPDATE)

        // 1. Modifier le statut d'une commande
        if (preg_match('/(?:change|modifie|mettre a jour)/ui', $msgNorm)
            && strpos($msgNorm, 'statut') !== false
            && preg_match('/commande\s*(?:n[°o]?|cmd|numero)?\s*(\d+)/ui', $msgNorm, $mNum)
            && preg_match('/\ben\s+([a-zà-ÿ]+(?:\s+[a-zà-ÿ]+)*)\s*[\.\!\?]*\s*$/ui', trim($message), $mStatut)
        ) {
            $idCommande = intval($mNum[1]);
            $nouveauStatut = trim($mStatut[1]);

            $stmt = mysqli_prepare($conn, "UPDATE commande SET statut = ? WHERE id_commande = ?");
            mysqli_stmt_bind_param($stmt, "si", $nouveauStatut, $idCommande);
            if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
                return "La commande n°$idCommande a bien été mise à jour avec le statut \"$nouveauStatut\".";
            }
            return "Impossible de modifier la commande n°$idCommande.";
        }

        // 2. Modifier le téléphone d'un client
        if (preg_match('/(?:change|modifie|mettre a jour|mettre à jour).*t[ée]l[ée]phone.*client\s+([a-zA-Zà-ÿ\-\s]+?)\s*,?\s*tel:\s*([0-9\s\-\+]+)/ui', $message, $m)) {
            $nomClient = trim($m[1]);
            $nouveauTel = trim($m[2]);

            $stmt = mysqli_prepare($conn, "UPDATE client SET telephone = ? WHERE nom = ?");
            mysqli_stmt_bind_param($stmt, "ss", $nouveauTel, $nomClient);
            if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
                return "Le téléphone du client \"$nomClient\" a été mis à jour : $nouveauTel.";
            }
            return "Modification impossible.";
        }

        // 3. Modifier le stock d'un produit
        if (preg_match('/(?:change|modifie|mettre a jour).*stock.*produit\s+([a-z0-9\-]+).*?(\d+)/ui', $msgNorm, $m)) {
            $refProduit = trim($m[1]);
            $nouveauStock = intval($m[2]);

            $stmt = mysqli_prepare($conn, "UPDATE produit SET quantite_stock = ? WHERE reference = ?");
            mysqli_stmt_bind_param($stmt, "is", $nouveauStock, $refProduit);
            if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
                return "Le stock du produit \"$refProduit\" a été mis à jour à $nouveauStock unité(s).";
            }
            return "Impossible de modifier le stock du produit \"$refProduit\" (référence introuvable ?).";
        }

        // 4. Modifier le statut d'une livraison
        if (preg_match('/(?:change|modifie|mettre a jour)/ui', $msgNorm)
            && strpos($msgNorm, 'statut') !== false
            && preg_match('/livraison\s*(?:n[°o]?|numero)?\s*(\d+)/ui', $msgNorm, $mNum)
            && preg_match('/\ben\s+([a-zà-ÿ]+(?:\s+[a-zà-ÿ]+)*)\s*[\.\!\?]*\s*$/ui', trim($message), $mStatut)
        ) {
            $idLivraison = intval($mNum[1]);
            $nouveauStatut = trim($mStatut[1]);

            $stmt = mysqli_prepare($conn, "UPDATE livraison SET statut = ? WHERE id_livraison = ?");
            mysqli_stmt_bind_param($stmt, "si", $nouveauStatut, $idLivraison);
            if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
                return "La livraison n°$idLivraison a été mise à jour avec le statut \"$nouveauStatut\".";
            }
            return "Impossible de modifier la livraison n°$idLivraison.";
        }

        // B. AJOUTS (INSERT)

        // 1. Ajouter une commande
        if (preg_match('/(?:ajoute|ajouter|creer)(?!.*livraison).*commande/ui', $msgNorm)) {
            if (preg_match('/client:\s*([a-zA-Zà-ÿ\-\s]+)/i', $message, $clientM)) {
                $nomClient = trim($clientM[1]);

                $check = mysqli_prepare($conn, "SELECT id_client FROM client WHERE nom = ?");
                mysqli_stmt_bind_param($check, "s", $nomClient);
                mysqli_stmt_execute($check);
                $res = mysqli_stmt_get_result($check);

                if ($row = mysqli_fetch_assoc($res)) {
                    $idClient = $row['id_client'];
                    $statut = "En attente";

                    $stmt = mysqli_prepare($conn, "INSERT INTO commande (id_client, statut) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt, "is", $idClient, $statut);
                    if (mysqli_stmt_execute($stmt)) {
                        $idCommande = mysqli_insert_id($conn);
                        return "Commande n°$idCommande créée avec succès pour le client \"$nomClient\".";
                    }
                    return "Erreur lors de la création de la commande.";
                }
                return "Aucun client trouvé au nom de \"$nomClient\".";
            }
            return "Pour ajouter une commande, utilisez le format : 'Ajoute une commande pour client: X'.";
        }

        // 2. Ajouter une livraison
        if (preg_match('/(?:ajoute|ajouter|creer).*livraison/ui', $msgNorm)) {
            if (preg_match('/commande:?\s*(\d+)/ui', $msgNorm, $cmdM)) {
                $idCommande = intval($cmdM[1]);
                $statut = "En préparation";

                $stmt = mysqli_prepare($conn, "INSERT INTO livraison (id_commande, statut) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, "is", $idCommande, $statut);
                if (mysqli_stmt_execute($stmt)) {
                    $idLivraison = mysqli_insert_id($conn);
                    return "Livraison n°$idLivraison créée avec succès pour la commande n°$idCommande.";
                }
                return "Erreur lors de la création de la livraison.";
            }
            return "Pour ajouter une livraison, utilisez le format : 'Ajoute une livraison pour commande: X'.";
        }

        // 3. Ajouter un client — mention explicite OU continuation d'une
        // demande précédente du bot (mémoire de conversation)
        $demandeExpliciteClient = preg_match('/(?:ajoute|ajouter|creer).*client/ui', $msgNorm);
        $continuationClient = attendAjoutClient($historiqueAvant);

        if ($demandeExpliciteClient || $continuationClient) {
            $infos = extraireInfosPersonne($message);

            if ($infos['nom'] && $infos['telephone']) {
                $nom = $infos['nom'];
                $telephone = $infos['telephone'];
                $email = $infos['email'] ?: (strtolower(str_replace(' ', '', $nom)) . "@example.com");
                $mode = $infos['mode_paiement'];

                $stmt = mysqli_prepare($conn, "INSERT INTO client (nom, email, telephone, mode_paiement) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "ssss", $nom, $email, $telephone, $mode);
                if (mysqli_stmt_execute($stmt)) {
                    return "Client \"$nom\" ajouté avec succès (téléphone : $telephone" .
                           ($infos['email'] ? ", email : {$infos['email']}" : "") . ").";
                }
                return "Erreur lors de l'ajout du client. Vérifiez que \"$mode\" est une valeur de paiement valide.";
            }

            // Infos incomplètes : on précise ce qui manque plutôt que de tout rejeter
            if ($demandeExpliciteClient && !$continuationClient) {
                return "Pour ajouter un client, donnez-moi au minimum un nom et un numéro de téléphone " .
                       "(ex: 'Ajoute le client Ahmed, tel 0678453423', email et mode de paiement optionnels).";
            }
            if ($continuationClient) {
                $manque = [];
                if (!$infos['nom']) $manque[] = "le nom";
                if (!$infos['telephone']) $manque[] = "le téléphone";
                return "Il me manque encore " . implode(' et ', $manque) . " pour créer ce client.";
            }
        }

        // 4. Ajouter un produit
        if (preg_match('/(?:ajoute|ajouter|creer).*produit/ui', $msgNorm)) {
            if (preg_match('/ref:\s*([a-z0-9\-]+)/i', $message, $refM) && preg_match('/desc:\s*([a-z0-9\-\s]+)/i', $message, $descM)) {
                $reference = trim($refM[1]);
                $description = trim($descM[1]);
                $code = "AUTO-" . rand(100, 999);
                $stock = 0;
                $imagePdf = "";

                $stmt = mysqli_prepare($conn, "INSERT INTO produit (reference, code, description, quantite_stock, Image_pdf) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sssis", $reference, $code, $description, $stock, $imagePdf);
                if (mysqli_stmt_execute($stmt)) {
                    return "Produit ajouté avec succès ! Référence : $reference.";
                }
                return "Erreur lors de la création du produit.";
            }
            return "Pour ajouter un produit, utilisez le format : 'Ajoute le produit ref: X, desc: Y'.";
        }

        // C. SUPPRESSIONS (DELETE)

        // 1. Supprimer un client
        if (preg_match('/(?:supprime|supprimer).*client\s+([a-zA-Zà-ÿ\-\s]+?)\s*[\.\!\?]*\s*$/ui', $message, $m)) {
            $nomClient = trim($m[1]);
            $check = mysqli_prepare($conn, "SELECT id_client FROM client WHERE nom = ?");
            mysqli_stmt_bind_param($check, "s", $nomClient);
            mysqli_stmt_execute($check);
            $res = mysqli_stmt_get_result($check);
            if ($row = mysqli_fetch_assoc($res)) {
                $idClient = $row['id_client'];
                $del = mysqli_prepare($conn, "DELETE FROM client WHERE id_client = ?");
                mysqli_stmt_bind_param($del, "i", $idClient);
                if (mysqli_stmt_execute($del)) {
                    return "Le client \"$nomClient\" a été supprimé avec succès.";
                }
                return "Impossible de supprimer le client \"$nomClient\".";
            }
            return "Aucun client trouvé au nom de \"$nomClient\".";
        }

        // 2. Supprimer un produit
        if (preg_match('/(?:supprime|supprimer).*produit\s+([a-z0-9\-]+)/ui', $message, $m)) {
            $refProduit = trim($m[1]);
            $del = mysqli_prepare($conn, "DELETE FROM produit WHERE reference = ?");
            mysqli_stmt_bind_param($del, "s", $refProduit);
            mysqli_stmt_execute($del);
            if (mysqli_affected_rows($conn) > 0) {
                return "Le produit avec la référence \"$refProduit\" a été supprimé avec succès.";
            }
            return "Aucun produit trouvé avec la référence \"$refProduit\".";
        }

        // 3. Supprimer une commande
        if (preg_match('/(?:supprime|supprimer).*commande\s*(?:cmd)?\s*(\d+)/ui', $msgNorm, $m)) {
            $idCommande = intval($m[1]);
            $del = mysqli_prepare($conn, "DELETE FROM commande WHERE id_commande = ?");
            mysqli_stmt_bind_param($del, "i", $idCommande);
            mysqli_stmt_execute($del);
            if (mysqli_affected_rows($conn) > 0) {
                return "La commande n°$idCommande a été supprimée avec succès.";
            }
            return "Aucune commande trouvée avec le numéro $idCommande.";
        }

        // 4. Supprimer une livraison
        if (preg_match('/(?:supprime|supprimer).*livraison\s*(\d+)/ui', $msgNorm, $m)) {
            $idLivraison = intval($m[1]);
            $del = mysqli_prepare($conn, "DELETE FROM livraison WHERE id_livraison = ?");
            mysqli_stmt_bind_param($del, "i", $idLivraison);
            mysqli_stmt_execute($del);
            if (mysqli_affected_rows($conn) > 0) {
                return "La livraison n°$idLivraison a été supprimée avec succès.";
            }
            return "Aucune livraison trouvée avec le numéro $idLivraison.";
        }

    } catch (Exception $e) {
        return "Erreur SQL rencontrée : " . $e->getMessage();
    }

    return null;
}

// ============================================================
// 4. MÉMOIRE DE CONVERSATION
// Récupère les N derniers échanges de CETTE conversation pour
// donner du contexte à Ollama et lui permettre de "se souvenir".
// ============================================================
function getHistoriqueConversation($conn, $conversationId, $limite = 12) {
    $historique = [];

    if ($conversationId <= 0) {
        return $historique;
    }

    // On récupère les derniers messages dans l'ordre chronologique inverse,
    // puis on les remet dans l'ordre normal.
    $stmt = mysqli_prepare($conn, "
        SELECT role, contenu 
        FROM message 
        WHERE id_conversation = ? 
        ORDER BY id_message DESC 
        LIMIT ?
    ");
    mysqli_stmt_bind_param($stmt, "ii", $conversationId, $limite);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lignes = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lignes[] = $row;
    }
    $lignes = array_reverse($lignes); // remettre dans l'ordre chronologique

    foreach ($lignes as $ligne) {
        // Ollama attend "user" ou "assistant"
        $role = ($ligne['role'] === 'assistant') ? 'assistant' : 'user';
        $historique[] = [
            "role" => $role,
            "content" => $ligne['contenu']
        ];
    }

    return $historique;
}

// ============================================================
// 5. CONTEXTE "DONNÉES RÉELLES" pour aider l'IA à répondre
// intelligemment sur des questions complexes, sans halluciner
// de chiffres. On lui donne un résumé factuel à jour.
// ============================================================
function getContexteDonnees($conn) {
    $ctx = [];

    $r = mysqli_query($conn, "SELECT COUNT(*) as t FROM client");
    $ctx['clients'] = mysqli_fetch_assoc($r)['t'];

    $r = mysqli_query($conn, "SELECT COUNT(*) as t FROM commande");
    $ctx['commandes'] = mysqli_fetch_assoc($r)['t'];

    $r = mysqli_query($conn, "SELECT statut, COUNT(*) as t FROM commande GROUP BY statut");
    $parStatut = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $parStatut[] = "{$row['statut']}: {$row['t']}";
    }
    $ctx['commandes_par_statut'] = implode(', ', $parStatut);

    $r = mysqli_query($conn, "SELECT COUNT(*) as t, SUM(quantite_stock) as s FROM produit");
    $row = mysqli_fetch_assoc($r);
    $ctx['produits'] = $row['t'];
    $ctx['stock_total'] = $row['s'] ?? 0;

    $r = mysqli_query($conn, "SELECT COUNT(*) as t FROM produit WHERE quantite_stock = 0");
    $ctx['ruptures'] = mysqli_fetch_assoc($r)['t'];

    $r = mysqli_query($conn, "SELECT COUNT(*) as t FROM livraison");
    $ctx['livraisons'] = mysqli_fetch_assoc($r)['t'] ?? 0;

    return "Résumé des données actuelles de PalmFox : {$ctx['clients']} clients, {$ctx['commandes']} commandes " .
           "(détail par statut : {$ctx['commandes_par_statut']}), {$ctx['produits']} produits référencés " .
           "pour un stock total de {$ctx['stock_total']} unités dont {$ctx['ruptures']} en rupture, " .
           "{$ctx['livraisons']} livraisons enregistrées.";
}

// ============================================================
// 6. RAPPORT D'ANALYSE DES CONVERSATIONS (ajout)
// Ne modifie rien à l'existant : lit simplement les messages déjà
// stockés et les classe à la volée (type de demande + module
// concerné), pour donner une vue d'ensemble de l'usage du chatbot.
// ============================================================

function normaliserPourGroupement($texte) {
    $t = normaliser($texte);
    $t = preg_replace('/\d+/', '#', $t);           // les nombres deviennent # (commande 5 / commande 12 -> même groupe)
    $t = preg_replace('/[^\p{L}\s#]/u', '', $t);    // retire ponctuation
    $t = preg_replace('/\s+/', ' ', trim($t));
    return $t;
}

// Détecte le type de demande et le module concerné par un message,
// à des fins purement statistiques (n'influence pas les réponses).
function classifierMessage($msgNorm) {
    $motsClesPalmfox = ['client', 'produit', 'commande', 'livraison', 'stock',
                         'employe', 'utilisateur', 'compte', 'permission', 'acces', 'palmfox'];
    $contientMotCle = false;
    foreach ($motsClesPalmfox as $mot) {
        if (strpos($msgNorm, normaliser($mot)) !== false) {
            $contientMotCle = true;
            break;
        }
    }

    if (!$contientMotCle) {
        return ['type' => 'hors_sujet', 'module' => 'general'];
    }

    $module = 'general';
    if (preg_match('/\bclient/u', $msgNorm)) $module = 'client';
    elseif (preg_match('/\bcommande/u', $msgNorm)) $module = 'commande';
    elseif (preg_match('/\bproduit|stock/u', $msgNorm)) $module = 'produit';
    elseif (preg_match('/\blivraison/u', $msgNorm)) $module = 'livraison';
    elseif (preg_match('/employe|utilisateur|compte|permission|acces/u', $msgNorm)) $module = 'employe';

    // Ordre important : suppression/modification/ajout avant consultation,
    // pour éviter qu'une phrase d'action ne matche par erreur une consultation.
    $type = 'autre';
    if (preg_match('/(?:supprime|supprimer)/u', $msgNorm)) {
        $type = 'suppression';
    } elseif (preg_match('/(?:change|modifie|mettre a jour|autorise|active|retire|enleve|desactive)/u', $msgNorm)) {
        $type = 'modification';
    } elseif (preg_match('/(?:ajoute|ajouter|creer|nouveau|nouvelle)/u', $msgNorm)) {
        $type = 'ajout';
    } elseif (preg_match('/(?:combien|nombre|liste|montre|affiche|quel|quelle|quels|quelles|statut|comment|ou est|qui est)/u', $msgNorm)) {
        $type = 'consultation';
    }

    return ['type' => $type, 'module' => $module];
}

function genererRapport($conn, $userRole, $dateDebut = null, $dateFin = null) {
    if (!in_array($userRole, ['super_admin', 'gestionnaire'])) {
        http_response_code(403);
        echo json_encode(["error" => "Accès réservé aux administrateurs."]);
        return;
    }

    $whereUser = "WHERE role = 'user'";
    $whereAssistant = "WHERE role = 'assistant'";
    $params = [];
    $types = "";
    if ($dateDebut) {
        $whereUser .= " AND date_envoi >= ?";
        $whereAssistant .= " AND date_envoi >= ?";
        $params[] = $dateDebut . " 00:00:00";
        $types .= "s";
    }
    if ($dateFin) {
        $whereUser .= " AND date_envoi <= ?";
        $whereAssistant .= " AND date_envoi <= ?";
        $params[] = $dateFin . " 23:59:59";
        $types .= "s";
    }

    $sql = "SELECT m.contenu, m.date_envoi, m.id_conversation, c.id_user
            FROM message m
            JOIN conversation c ON m.id_conversation = c.id_conversation
            $whereUser";
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== "") {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $totalMessagesUser = 0;
    $compteurType = [];
    $compteurModule = [];
    $compteurTypeModule = [];
    $groupesQuestions = [];
    $compteurParJour = [];
    $conversationsVues = [];
    $utilisateursVus = [];

    while ($row = mysqli_fetch_assoc($res)) {
        $totalMessagesUser++;

        $msgNorm = normaliser($row['contenu']);
        $classification = classifierMessage($msgNorm);
        $type = $classification['type'];
        $module = $classification['module'];

        $compteurType[$type] = ($compteurType[$type] ?? 0) + 1;
        $compteurModule[$module] = ($compteurModule[$module] ?? 0) + 1;
        $cleTypeModule = "$type|$module";
        $compteurTypeModule[$cleTypeModule] = ($compteurTypeModule[$cleTypeModule] ?? 0) + 1;

        $cleGroupe = normaliserPourGroupement($row['contenu']);
        if ($cleGroupe !== '') {
            if (!isset($groupesQuestions[$cleGroupe])) {
                $groupesQuestions[$cleGroupe] = ["exemple" => $row['contenu'], "total" => 0];
            }
            $groupesQuestions[$cleGroupe]['total']++;
        }

        $jour = substr($row['date_envoi'], 0, 10);
        $compteurParJour[$jour] = ($compteurParJour[$jour] ?? 0) + 1;

        $conversationsVues[$row['id_conversation']] = true;
        $utilisateursVus[$row['id_user']] = true;
    }

    $stmt2 = mysqli_prepare($conn, "SELECT COUNT(*) as t FROM message $whereAssistant");
    if ($types !== "") {
        mysqli_stmt_bind_param($stmt2, $types, ...$params);
    }
    mysqli_stmt_execute($stmt2);
    $totalMessagesAssistant = (int) mysqli_stmt_get_result($stmt2)->fetch_assoc()['t'];

    $totalConversations = count($conversationsVues);
    $utilisateursActifs = count($utilisateursVus);
    $moyenneMessagesParConversation = $totalConversations > 0
        ? round($totalMessagesUser / $totalConversations, 1)
        : 0;

    $vueEnsemble = [
        "total_conversations" => $totalConversations,
        "total_messages_utilisateur" => $totalMessagesUser,
        "total_messages_assistant" => $totalMessagesAssistant,
        "utilisateurs_actifs" => $utilisateursActifs,
        "moyenne_messages_par_conversation" => $moyenneMessagesParConversation,
    ];

    arsort($compteurType);
    $parType = [];
    foreach ($compteurType as $type => $total) {
        $parType[] = [
            "type" => $type,
            "total" => $total,
            "pourcentage" => $totalMessagesUser > 0 ? round($total / $totalMessagesUser * 100, 1) : 0
        ];
    }

    arsort($compteurModule);
    $parModule = [];
    foreach ($compteurModule as $module => $total) {
        $parModule[] = [
            "module" => $module,
            "total" => $total,
            "pourcentage" => $totalMessagesUser > 0 ? round($total / $totalMessagesUser * 100, 1) : 0
        ];
    }

    arsort($compteurTypeModule);
    $parTypeEtModule = [];
    foreach ($compteurTypeModule as $cle => $total) {
        [$type, $module] = explode('|', $cle);
        $parTypeEtModule[] = [
            "type" => $type,
            "module" => $module,
            "total" => $total,
            "pourcentage" => $totalMessagesUser > 0 ? round($total / $totalMessagesUser * 100, 1) : 0
        ];
    }

    uasort($groupesQuestions, fn($a, $b) => $b['total'] <=> $a['total']);
    $questionsFrequentes = [];
    $i = 0;
    foreach ($groupesQuestions as $g) {
        if ($i >= 15) break;
        if ($g['total'] < 2) continue;
        $questionsFrequentes[] = [
            "question" => $g['exemple'],
            "occurrences" => $g['total'],
            "pourcentage" => $totalMessagesUser > 0 ? round($g['total'] / $totalMessagesUser * 100, 1) : 0
        ];
        $i++;
    }

    ksort($compteurParJour);
    $tendance = [];
    foreach ($compteurParJour as $jour => $total) {
        $tendance[] = ["jour" => $jour, "total" => $total];
    }

    echo json_encode([
        "periode" => ["debut" => $dateDebut, "fin" => $dateFin],
        "vue_ensemble" => $vueEnsemble,
        "repartition_par_type" => $parType,
        "repartition_par_module" => $parModule,
        "repartition_type_module" => $parTypeEtModule,
        "questions_frequentes" => $questionsFrequentes,
        "tendance_par_jour" => $tendance,
    ], JSON_UNESCAPED_UNICODE);
}

// ============================================================
// 7. RAPPORT VIA LE CHAT (ajout)
// Permet de déclencher le même rapport simplement en tapant une
// phrase dans le chat (ex: "donne-moi le rapport des conversations"),
// sans passer par le paramètre technique "action". Retourne un
// texte lisible (et non le JSON brut) pour s'afficher normalement
// dans la bulle de réponse du chat.
// ============================================================
function reponseRapport($msgNorm, $conn, $userId) {
    $demandeRapport = preg_match('/\brapport\b/u', $msgNorm)
        && preg_match('/conversation|discussion|echange|utilisation|statistique|analyse|chatbot/u', $msgNorm);

    if (!$demandeRapport) {
        return null;
    }

    try {
        $sql = "SELECT m.contenu, m.date_envoi, m.id_conversation, c.id_user
                FROM message m
                JOIN conversation c ON m.id_conversation = c.id_conversation
                WHERE m.role = 'user' AND c.id_user = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return "Erreur lors de la préparation du rapport : " . mysqli_error($conn);
        }
        mysqli_stmt_bind_param($stmt, "i", $userId);
        if (!mysqli_stmt_execute($stmt)) {
            return "Erreur lors de l'exécution du rapport : " . mysqli_stmt_error($stmt);
        }
        $res = mysqli_stmt_get_result($stmt);
        if ($res === false) {
            return "Erreur lors de la récupération des résultats du rapport : " . mysqli_stmt_error($stmt);
        }

        $totalMessagesUser = 0;
        $compteurType = [];
        $compteurModule = [];
        $groupesQuestions = [];
        $conversationsVues = [];

        while ($row = mysqli_fetch_assoc($res)) {
            $totalMessagesUser++;

            $classification = classifierMessage(normaliser($row['contenu']));
            $compteurType[$classification['type']] = ($compteurType[$classification['type']] ?? 0) + 1;
            $compteurModule[$classification['module']] = ($compteurModule[$classification['module']] ?? 0) + 1;

            $cleGroupe = normaliserPourGroupement($row['contenu']);
            if ($cleGroupe !== '') {
                if (!isset($groupesQuestions[$cleGroupe])) {
                    $groupesQuestions[$cleGroupe] = ["exemple" => $row['contenu'], "total" => 0];
                }
                $groupesQuestions[$cleGroupe]['total']++;
            }

            $conversationsVues[$row['id_conversation']] = true;
        }

        if ($totalMessagesUser === 0) {
            return "Aucune conversation enregistrée pour le moment.";
        }

        arsort($compteurType);
        uasort($groupesQuestions, fn($a, $b) => $b['total'] <=> $a['total']);

        $texte = "RAPPORT DE VOS CONVERSATIONS\n";
        $texte .= count($conversationsVues) . " conversation(s), " . $totalMessagesUser . " message(s) envoyé(s)\n\n";

        $texte .= "TYPES DE DEMANDE\n";
        foreach ($compteurType as $type => $total) {
            $pct = round($total / $totalMessagesUser * 100);
            $texte .= "• $type : $pct%\n";
        }
        $texte .= "\n";

        arsort($compteurModule);
        $texte .= "MODULES CONCERNÉS\n";
        foreach ($compteurModule as $module => $total) {
            $pct = round($total / $totalMessagesUser * 100);
            $texte .= "• $module : $pct%\n";
        }
        $texte .= "\n";

        $texte .= "QUESTIONS LES PLUS FRÉQUENTES\n";
        $i = 0;
        foreach ($groupesQuestions as $g) {
            if ($i >= 5) break;
            if ($g['total'] < 2) continue;
            $pct = round($g['total'] / $totalMessagesUser * 100);
            $texte .= ($i + 1) . ". \"{$g['exemple']}\" ({$g['total']} fois, $pct%)\n";
            $i++;
        }

        return trim($texte);

    } catch (Throwable $e) {
        return "Erreur lors de la génération du rapport : " . $e->getMessage();
    }
}

// ============================================================
// TRAITEMENT PRINCIPAL
// ============================================================

// Créer une nouvelle conversation si aucune fournie
if ($conversationId <= 0) {
    $titre = mb_substr($message, 0, 50);
    $stmt = mysqli_prepare($conn, "INSERT INTO conversation (id_user, titre) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "is", $userId, $titre);
    mysqli_stmt_execute($stmt);
    $conversationId = mysqli_insert_id($conn);
} else {
    $check = mysqli_prepare($conn, "SELECT id_conversation FROM conversation WHERE id_conversation = ? AND id_user = ?");
    mysqli_stmt_bind_param($check, "ii", $conversationId, $userId);
    mysqli_stmt_execute($check);
    if (!mysqli_stmt_get_result($check)->fetch_assoc()) {
        http_response_code(403);
        echo json_encode(["error" => "Accès refusé"]);
        exit;
    }
}

// IMPORTANT : on récupère l'historique AVANT d'insérer le message actuel,
// pour ne pas le dupliquer dans le contexte envoyé à Ollama.
$historiqueAvant = getHistoriqueConversation($conn, $conversationId, 12);

// Sauvegarder le message utilisateur
$stmt = mysqli_prepare($conn, "INSERT INTO message (id_conversation, role, contenu) VALUES (?, 'user', ?)");
mysqli_stmt_bind_param($stmt, "is", $conversationId, $message);
mysqli_stmt_execute($stmt);
$userMessageId = mysqli_insert_id($conn);

// --- Étape 1 : actions d'écriture (ajout / modification / suppression) ---
$reply = executerActionEcriture($message, $msgNorm, $conn, $historiqueAvant);

// --- Étape 2 : statistiques ---
if ($reply === null) {
    $reply = reponseStatistiques($msgNorm, $conn);
}

// --- Étape 3 : recherche précise ---
if ($reply === null) {
    $reply = reponseRecherche($message, $msgNorm, $conn);
}

// --- Étape 4 : FAQ "comment faire" ---
if ($reply === null) {
    $reply = reponseFAQ($msgNorm);
}

// --- Étape 4.5 : rapport personnel de conversations (ajout) ---
if ($reply === null) {
    $reply = reponseRapport($msgNorm, $conn, $userId);
}

// --- Étape 5 : fallback Ollama (avec mémoire + contexte de données) ---
if ($reply === null) {
    $mots_cles_palmfox = ['client', 'clients', 'produit', 'produits', 'commande', 'commandes',
                          'livraison', 'livraisons', 'stock', 'acces', 'accès', 'palmfox',
                          'employe', 'employé', 'utilisateur', 'compte', 'admin', 'gestionnaire',
                          'commercial', 'bonjour', 'salut', 'merci', 'aide', 'aider'];

    $contient_mot_cle = false;
    foreach ($mots_cles_palmfox as $mot) {
        if (strpos($msgNorm, normaliser($mot)) !== false) {
            $contient_mot_cle = true;
            break;
        }
    }

    if (!$contient_mot_cle) {
        $reply = "Je suis là pour vous aider uniquement avec l'application PalmFox (clients, produits, commandes, livraisons).";
    } else {
        $contexteDonnees = getContexteDonnees($conn);

        $systemPrompt = "Tu es l'assistant IA de l'application PalmFox, un logiciel de gestion commerciale " .
            "(clients, produits, commandes, livraisons). Réponds UNIQUEMENT en français, de façon claire, " .
            "utile et concise. Utilise le contexte suivant sur les données réelles actuelles pour donner des " .
            "réponses pertinentes, mais n'invente JAMAIS de chiffre précis qui ne figure pas dans ce contexte : " .
            "$contexteDonnees. Si l'utilisateur pose une question qui nécessite une donnée exacte que tu n'as pas " .
            "dans ce contexte, dis-lui de reformuler plus précisément (ex: 'combien de clients avons-nous', " .
            "'statut de la commande 5'). Tu peux t'appuyer sur l'historique de la conversation pour comprendre " .
            "le contexte des questions précédentes et donner des réponses cohérentes dans la durée.";

        // Construction des messages : system + historique + message actuel
        $messagesOllama = [
            ["role" => "system", "content" => $systemPrompt]
        ];
        foreach ($historiqueAvant as $m) {
            $messagesOllama[] = $m;
        }
        $messagesOllama[] = ["role" => "user", "content" => $message];

        $reponse = ollamaChat("qwen3:4b", $messagesOllama);

        if (isset($reponse['error'])) {
            $reply = $reponse['error'];
        } else {
            $reply = $reponse['content'];
        }
    }
}

// Sauvegarder la réponse
$stmt = mysqli_prepare($conn, "INSERT INTO message (id_conversation, role, contenu) VALUES (?, 'assistant', ?)");
mysqli_stmt_bind_param($stmt, "is", $conversationId, $reply);
mysqli_stmt_execute($stmt);

echo json_encode([
    "reply" => $reply,
    "conversation_id" => $conversationId,
    "user_message_id" => $userMessageId
]);
?>