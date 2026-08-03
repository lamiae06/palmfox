# PalmFox ERP

Système de gestion clients/commandes développé en équipe. Interface avec espace Admin
et espace Utilisateur, design system dark navy unifié.

## Fonctionnalités

- **Authentification** — connexion par session, séparation Espace Admin / Espace Utilisateur
- **Dashboard** — vue d'ensemble : clients actifs, commandes du mois, livraisons en cours, valeur du stock, commandes récentes avec statuts (en attente / confirmée)
- **Clients** — gestion complète des clients
- **Produits** — gestion du catalogue produits et du stock
- **Commandes** — commandes multi-produits via une table de jonction (`concerner`), validation de stock et rollback via transactions MySQL, triggers MySQL pour la cohérence des données
- **Livraisons** — suivi des livraisons liées aux commandes
- **Rapport Chatbot** — page d'analyse IA du dashboard (clients, commandes, livraisons, stock), basée sur Ollama avec le modèle Qwen3
- **Gestion Accès** — administration des droits utilisateurs

## Stack technique

- **Back-end** : PHP, MySQLi
- **Front-end** : HTML, CSS, JavaScript
- **Base de données** : MySQL
- **IA** : Ollama (Qwen3)
- **Versioning** : Git, travail en équipe sur branches dédiées

## Aperçu

*(ajoute ici les captures d'écran : écran de choix d'espace, dashboard, sidebar)*
