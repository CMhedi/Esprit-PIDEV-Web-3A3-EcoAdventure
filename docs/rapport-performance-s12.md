# Rapport de Performance & Optimisation

## Nom de groupe

A completer avec le nom officiel du groupe.

## Contexte

Dans le cadre de l'atelier S12, nous avons travaille sur trois gestions principales de notre application Symfony:

- gestion de messagerie
- gestion de reservation d'activite
- gestion d'inscription pack

L'objectif etait d'ameliorer la qualite du code, la fiabilite des traitements et l'observabilite des performances en utilisant:

- PHPStan pour l'analyse statique
- PHPUnit pour les tests unitaires
- Doctrine Doctor pour l'analyse des performances Doctrine

## 1. PHPStan

### 1.1 Avant optimisation

Avant l'optimisation, l'analyse statique remontait plusieurs anomalies dans le projet:

- conditions toujours vraies ou toujours fausses
- branches mortes
- verifications redondantes sur des tableaux ou des types deja connus
- incoherences de typage dans certains services et controleurs
- faux positifs sur certaines proprietes Doctrine d'entites

Commande utilisee:

```powershell
vendor/bin/phpstan analyse src --level=4
```

Preuves a inserer:

- capture du terminal montrant les erreurs avant optimisation
- capture d'une ou deux erreurs representatives

### 1.2 Apres optimisation

Apres correction, le projet passe correctement l'analyse PHPStan au niveau 4.

Commande utilisee:

```powershell
vendor/bin/phpstan analyse src --level=4 --no-progress
```

Resultat obtenu:

```text
[OK] No errors
```

Travail realise:

- simplification de plusieurs conditions inutiles
- suppression de branches mortes
- correction de certains traitements dans les controleurs et services
- nettoyage de services utilitaires
- ajustement de la configuration PHPStan pour ignorer les faux positifs lies a certaines proprietes Doctrine generees par l'ORM

Preuves a inserer:

- capture du terminal montrant `[OK] No errors`

## 2. Tests unitaires

Les tests unitaires ont ete organises autour des trois gestions ciblees.

### 2.1 Gestion Messagerie

Objectif:

- verifier les regles d'acces a la messagerie
- verifier les contraintes metier des conversations
- verifier les regles de validation des messages

Fichiers testes:

- `tests/Service/MessagingAccessManagerTest.php`
- `tests/Entity/ConversationTest.php`
- `tests/Entity/MessageValidationTest.php`

Commande:

```powershell
php vendor/bin/phpunit tests/Service/MessagingAccessManagerTest.php tests/Entity/ConversationTest.php tests/Entity/MessageValidationTest.php --testdox
```

Resultats observes:

- les utilisateurs bloques sont correctement detectes
- les conversations privees bloquees respectent la logique metier
- les messages vides ou invalides sont rejetes
- les valeurs par defaut d'un nouveau message sont conformes

### 2.2 Gestion Reservation d'activite

Objectif:

- verifier la validation des reservations
- empecher les dates passees et les nombres de participants invalides

Fichier teste:

- `tests/Entity/ReservationActiviteValidationTest.php`

Commande:

```powershell
php vendor/bin/phpunit tests/Entity/ReservationActiviteValidationTest.php --testdox
```

Resultats observes:

- les reservations avec date passee sont rejetees
- les reservations avec nombre de personnes invalide sont rejetees
- une reservation future correctement renseignee est acceptee

### 2.3 Gestion Inscription Pack

Objectif:

- verifier la logique d'inscription et de paiement
- verifier l'analyse et la priorisation des packs
- verifier le moteur de risque

Fichiers testes:

- `tests/Controller/PackInscriptionControllerPrivateLogicTest.php`
- `tests/InscriptionAnalyticsBuilderTest.php`
- `tests/InscriptionRiskEngineTest.php`
- `tests/PackInsightAssemblerTest.php`
- `tests/PackRiskEngineTest.php`

Commande:

```powershell
php vendor/bin/phpunit tests/Controller/PackInscriptionControllerPrivateLogicTest.php tests/InscriptionAnalyticsBuilderTest.php tests/InscriptionRiskEngineTest.php tests/PackInsightAssemblerTest.php tests/PackRiskEngineTest.php --testdox
```

Resultats observes:

- les identifiants de commande de paiement sont generes correctement
- la validation des cartes de demonstration fonctionne
- les packs sont correctement analyses et classes
- les inscriptions a risque sont detectees par le moteur metier

### 2.4 Resultat global

Commande globale:

```powershell
php vendor/bin/phpunit --testdox
```

Resultat obtenu:

```text
OK (23 tests, 67 assertions)
```

Interpretation:

- l'ensemble des tests automatiques executes sur le projet passe avec succes
- aucune regression n'a ete detectee sur les trois gestions ciblees

Preuves a inserer:

- capture du terminal pour les tests par gestion
- capture du terminal pour le resultat global

## 3. Problemes detectes avec Doctrine Doctor

### 3.1 Methode de verification

Doctrine Doctor a ete utilise en environnement `dev` via le profiler Symfony.

Procedure:

1. lancer l'application en mode developpement
2. ouvrir les pages des trois gestions
3. declencher des parcours reels
4. consulter le profiler Symfony
5. ouvrir l'onglet `Doctrine Doctor`

Parcours testes:

- messagerie: ouverture de la liste des conversations puis consultation d'une conversation
- reservation activite: ouverture d'une activite puis test de reservation
- inscription pack: ouverture d'un pack puis lancement du parcours d'inscription

### 3.2 Points observes

Les indicateurs principaux suivis etaient:

- nombre de problemes N+1 detectes
- requetes lentes
- chargements inutiles eager/lazy
- problemes d'hydratation

### 3.3 Resultat

Formulation conseillee si aucun blocage critique n'est remonte:

```text
L'analyse Doctrine Doctor sur les parcours testes n'a pas remonte de probleme critique bloquant sur les modules messagerie, reservation d'activite et inscription pack. Les parcours principaux restent fonctionnels et exploitables.
```

Si vous avez observe une alerte, remplacez ce paragraphe par:

- nom de la page
- type de probleme detecte
- correction appliquee
- resultat apres correction

Preuves a inserer:

- capture du profiler Symfony
- capture de l'onglet `Doctrine Doctor`

## 4. Tableau de performance avant / apres optimisation

Le modele Word demande un tableau comparatif. Tu peux le remplir avec le contenu suivant.

### 4.1 Nombre de problemes N+1 detectes

- Avant optimisation: a completer selon la capture Doctrine Doctor initiale
- Apres optimisation: a completer selon la capture finale
- Preuves: capture Doctrine Doctor avant/apres

### 4.2 Les problemes

- Avant optimisation:
  - erreurs PHPStan
  - conditions redondantes
  - branches mortes
  - faux positifs non traites
- Apres optimisation:
  - PHPStan niveau 4 valide
  - simplification des traitements
  - code plus lisible et plus fiable
- Preuves: captures du terminal PHPStan

### 4.3 Temps moyen de reponse de la page d'accueil

- Avant optimisation: a relever dans le navigateur ou le profiler
- Apres optimisation: a relever apres les corrections
- Preuves: capture profiler ou reseau navigateur

### 4.4 Temps d'execution d'une fonctionnalite principale

Fonctionnalite conseillee:

- chargement de la page admin messagerie
- chargement d'une conversation
- chargement d'une page d'inscription pack

Remplissage:

- Avant optimisation: valeur relevee avant correction
- Apres optimisation: valeur relevee apres correction
- Preuves: capture du profiler

### 4.5 Utilisation memoire

- Avant optimisation: valeur relevee dans le profiler Symfony
- Apres optimisation: valeur relevee apres correction
- Preuves: capture du profiler

## 5. Conclusion

Ce travail nous a permis de renforcer la qualite logicielle et la maintenabilite de l'application sur trois modules importants: la messagerie, la reservation d'activite et l'inscription pack. L'utilisation combinee de PHPStan, PHPUnit et Doctrine Doctor nous a permis d'identifier des anomalies de structure, de fiabiliser le comportement metier et de verifier la stabilite des parcours principaux.

Apres optimisation:

- l'analyse statique PHPStan au niveau 4 ne remonte plus d'erreurs
- les tests unitaires du projet passent avec succes
- les parcours verifies sur les trois gestions restent fonctionnels

Le travail realise a donc permis d'ameliorer la robustesse du code sans remettre en cause la logique metier principale de l'application.

## 6. Liste rapide des captures a inserer

- PHPStan avant optimisation
- PHPStan apres optimisation avec `[OK] No errors`
- tests unitaires messagerie
- tests unitaires reservation activite
- tests unitaires inscription pack
- resultat global PHPUnit
- profiler Symfony sur une page messagerie
- onglet Doctrine Doctor
- eventuellement une capture d'une page admin ou front representative
