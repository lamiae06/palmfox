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
- **Réinitialisation de mot de passe** — renouvellement du mot de passe par vérification email (SMTP)
## Stack technique

- **Back-end** : PHP, MySQLi
- **Front-end** : HTML, CSS, JavaScript
- **Base de données** : MySQL
- **IA** : Ollama (Qwen3)
- **Versioning** : Git, travail en équipe sur branches dédiées

## Aperçu

<img width="1138" height="514" alt="image" src="https://github.com/user-attachments/assets/36eac067-895d-4dd8-affc-4af488fad76b" />
<img width="1179" height="543" alt="image" src="https://github.com/user-attachments/assets/b2262543-8ebe-45d3-a710-0ccdc96b4a1e" />
<img width="279" height="574" alt="image" src="https://github.com/user-attachments/assets/fe53180c-d6b9-4a07-82e7-c205525ca3f2" />

