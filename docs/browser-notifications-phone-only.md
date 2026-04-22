# Notifications Messagerie (Browser Téléphone, Sans Firebase)

## Scope exact
- Fonctionne quand l'utilisateur a la page web ouverte dans le navigateur du téléphone.
- Pas de push background quand onglet fermé.
- Pas de Firebase.

---

## Objectif
Quand un nouveau message arrive:
1. jouer un son léger
2. afficher une notification navigateur
3. mettre à jour les badges non-lus

---

## Prérequis
1. La messagerie doit déjà avoir un endpoint de polling (c'est le cas ici).
2. La page doit être ouverte et active dans le navigateur du téléphone.
3. L'utilisateur doit autoriser les notifications navigateur.

---

## Étape 1 - Permission notification
Dans `templates/front/index.html.twig` (JS):
1. vérifier `Notification` dans `window`
2. si `Notification.permission === "default"` alors `Notification.requestPermission()`
3. si refus (`denied`), garder fallback badge + son seulement

Exemple logique:
- granted => popup + son + badge
- denied => son + badge
- default => demander permission puis fallback

---

## Étape 2 - Détection nouveaux messages (polling)
Le polling existe déjà (`/messagerie/poll/{id_user}/{id_conversation}`).

Règle:
1. lire `last_seen_id` depuis le DOM
2. appeler endpoint toutes les 3-5 secondes
3. si `incoming_count > 0` et `latest_id > last_seen_id`:
   - mettre à jour `last_seen_id`
   - déclencher notification
   - mettre à jour badge

---

## Étape 3 - Notification navigateur
Créer notification seulement si permission = granted.

Contenu recommandé:
1. title: `Nouveau message - {titreConversation}`
2. body: `Vous avez X nouveau(x) message(s).`
3. icon: petite icône de l'app (optionnel)

---

## Étape 4 - Son notification
Jouer un son court uniquement quand nouveau message détecté.

Bonnes pratiques:
1. pas de son en boucle
2. pas de son si aucun nouveau message
3. une seule exécution par cycle de polling

---

## Étape 5 - Badge non lu
Mettre à jour:
1. badge conversation (liste gauche)
2. total non-lu dans `document.title`

Exemple:
- `(3) Messagerie - EcoAdventure`

---

## Étape 6 - Marquer lu
Quand utilisateur lit la conversation (scroll en bas):
1. appeler endpoint `/messagerie/read/...`
2. réduire badge de la conversation
3. réduire total non-lu

---

## Étape 7 - Tests (important)
Cas de test minimal:
1. Ouvrir compte A (laptop) + compte B (téléphone navigateur)
2. Sur téléphone, laisser messagerie ouverte
3. Envoyer message depuis laptop vers téléphone
4. Vérifier:
   - popup navigateur
   - son
   - badge non-lu
5. Scroller conversation en bas:
   - badge revient à 0

---

## Limitations connues
1. Onglet fermé/background profond: notification non garantie.
2. iOS Safari peut restreindre certains comportements selon version.
3. Pour un vrai mode background: il faut Web Push/FCM.

---

## Texte prêt pour rapport universitaire
"Nous avons implémenté une notification temps réel web basée sur polling côté client et Notification API du navigateur.
Cette approche répond au besoin du projet pour un usage mobile via navigateur lorsque la page messagerie est ouverte.
La solution met à jour les badges non-lus, déclenche un son d'alerte et affiche des notifications système, sans dépendance à Firebase."

