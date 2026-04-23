# Firebase FCM avec Symfony Messagerie (XAMPP/WAMP)

## Objectif
Recevoir une notification push sur mobile/web quand un nouveau message est envoyé dans la messagerie.

## Ce qu'il faut comprendre
- Les messages restent dans ta base MySQL (XAMPP/WAMP).
- Firebase **ne stocke pas** ton chat principal.
- Firebase FCM sert uniquement à livrer la notification aux appareils.

---

## Prérequis
1. Projet Symfony fonctionnel.
2. Compte Google + projet Firebase.
3. HTTPS pour test mobile réel (très important).
   - `localhost` peut marcher localement sur PC.
   - Téléphone + réseau local sans HTTPS = push souvent bloqué.

---

## Étape 1 - Configurer Firebase
1. Créer un projet sur Firebase Console.
2. Activer **Cloud Messaging**.
3. Générer la clé **Web Push (VAPID)**.
4. Créer une **Service Account** (JSON) pour envoi serveur.
5. Récupérer:
   - `FIREBASE_PROJECT_ID`
   - `FIREBASE_WEB_API_KEY`
   - `FIREBASE_MESSAGING_SENDER_ID`
   - `FIREBASE_APP_ID`
   - `FIREBASE_VAPID_PUBLIC_KEY`
   - fichier JSON du service account

---

## Étape 2 - Ajouter variables d'environnement
Dans `.env.local` (pas dans `.env` versionné):

```env
FIREBASE_PROJECT_ID=""
FIREBASE_WEB_API_KEY=""
FIREBASE_MESSAGING_SENDER_ID=""
FIREBASE_APP_ID=""
FIREBASE_VAPID_PUBLIC_KEY=""
FIREBASE_SERVICE_ACCOUNT_PATH="C:/path/to/firebase-service-account.json"
```

---

## Étape 3 - Frontend: obtenir token device
1. Ajouter SDK Firebase dans la page messagerie.
2. Ajouter `public/firebase-messaging-sw.js`.
3. Demander permission notification.
4. Générer token FCM côté navigateur.
5. Envoyer token à Symfony via endpoint API.

Exemple de endpoint: `POST /messagerie/device-token`

Payload:
```json
{ "token": "...", "platform": "web" }
```

---

## Étape 4 - Backend: stocker tokens
Créer entité/table `user_device_token`:
- `id`
- `user_id` (relation UserApp)
- `token` (unique)
- `platform` (web/android/ios)
- `updated_at`

Règle:
- Un utilisateur peut avoir plusieurs tokens (mobile + laptop + etc).

---

## Étape 5 - Service d'envoi FCM côté Symfony
Créer service `FcmNotificationService`:
1. Lire `FIREBASE_SERVICE_ACCOUNT_PATH`.
2. Générer access token OAuth Google.
3. Appeler API FCM HTTP v1:
   - `POST https://fcm.googleapis.com/v1/projects/{project_id}/messages:send`

Payload exemple:
```json
{
  "message": {
    "token": "DEVICE_TOKEN",
    "notification": {
      "title": "Nouveau message",
      "body": "hiba: Salut"
    },
    "data": {
      "conversationId": "27",
      "click_action": "/messagerie/3/27"
    }
  }
}
```

---

## Étape 6 - Brancher à la messagerie
Dans `sendMessage()` (après sauvegarde DB):
1. Identifier destinataires (participants sauf expéditeur).
2. Récupérer leurs tokens device.
3. Envoyer notification FCM à chaque token.
4. Si token invalide, le supprimer de la table.

---

## Étape 7 - Service Worker (notification clic)
Dans `firebase-messaging-sw.js`:
1. Gérer notification en background.
2. Au clic, ouvrir `click_action` (conversation concernée).

---

## Étape 8 - Test complet
1. Ouvrir app sur téléphone (HTTPS).
2. Autoriser notifications.
3. Vérifier token enregistré en DB.
4. Depuis laptop, envoyer message vers compte mobile.
5. Vérifier push reçu mobile.

---

## Erreurs fréquentes
1. Pas de HTTPS sur mobile -> pas de push.
2. Clés Firebase incorrectes.
3. Service account JSON absent/chemin incorrect.
4. Token expiré/non valide non nettoyé.
5. Notification testée sans permission navigateur.

---

## Sécurité
1. Ne jamais commiter les clés réelles dans `.env`.
2. Mettre secrets dans `.env.local`.
3. Protéger endpoint de token par utilisateur authentifié.

---

## Plan d'implémentation recommandé
1. Créer entité `UserDeviceToken` + migration.
2. Créer endpoint `POST /messagerie/device-token`.
3. Ajouter Firebase JS + service worker.
4. Créer `FcmNotificationService`.
5. Brancher envoi dans `sendMessage()`.
6. Tester + cleanup tokens invalides.

