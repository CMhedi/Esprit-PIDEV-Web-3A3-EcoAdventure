-- EcoAdventure realistic seed data for MySQL/MariaDB.
-- Run after migrations:
--   php bin/console doctrine:migrations:migrate
-- Then import:
--   mysql -u root ecoadventure < docs/realistic_seed.sql
--
-- Demo login password for all users: password
-- Includes the legacy is_verified column added by migrations.

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM messenger_messages;
DELETE FROM event_rating;
DELETE FROM message;
DELETE FROM conversation_user;
DELETE FROM conversation;
DELETE FROM reservation_seance;
DELETE FROM reservation_evenement;
DELETE FROM reservation_activite;
DELETE FROM recommendation_log;
DELETE FROM nutrition_log;
DELETE FROM reclamation;
DELETE FROM feedback_event;
DELETE FROM inscription;
DELETE FROM seance;
DELETE FROM planning;
DELETE FROM activite;
DELETE FROM evenement;
DELETE FROM capacity_policy;
DELETE FROM pack;
DELETE FROM user_app;

ALTER TABLE user_app AUTO_INCREMENT = 1;
ALTER TABLE pack AUTO_INCREMENT = 1;
ALTER TABLE activite AUTO_INCREMENT = 1;
ALTER TABLE planning AUTO_INCREMENT = 1;
ALTER TABLE seance AUTO_INCREMENT = 1;
ALTER TABLE evenement AUTO_INCREMENT = 1;
ALTER TABLE inscription AUTO_INCREMENT = 1;
ALTER TABLE reservation_activite AUTO_INCREMENT = 1;
ALTER TABLE reservation_evenement AUTO_INCREMENT = 1;
ALTER TABLE reservation_seance AUTO_INCREMENT = 1;
ALTER TABLE reclamation AUTO_INCREMENT = 1;
ALTER TABLE nutrition_log AUTO_INCREMENT = 1;
ALTER TABLE recommendation_log AUTO_INCREMENT = 1;
ALTER TABLE feedback_event AUTO_INCREMENT = 1;
ALTER TABLE conversation AUTO_INCREMENT = 1;
ALTER TABLE message AUTO_INCREMENT = 1;
ALTER TABLE event_rating AUTO_INCREMENT = 1;
ALTER TABLE messenger_messages AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO user_app (
    id_user, nom, prenom, email, telephone, image_url, role, mot_de_passe,
    date_creation, last_seen, age, experience, specialite, bio_certifs,
    disponibilite, referral_code, loyalty_points, is_verified
) VALUES
(1, 'Admin', 'Eco', 'admin@ecoadventure.test', '+21620000001', '/images/users/admin.jpg', 'ADMIN', '$2y$10$tywwvfCaNdS3JQnfuSyFJeGyTicHy58/fG5aySlm7kU3e6Zfm8BtG', '2026-01-02 09:00:00', '2026-04-10 09:30:00', NULL, NULL, NULL, NULL, NULL, 'ADMIN1', 0, 1),
(2, 'Ben Salem', 'Youssef', 'coach.fitness@ecoadventure.test', '+21620000002', '/images/users/youssef.jpg', 'COACH', '$2y$10$tywwvfCaNdS3JQnfuSyFJeGyTicHy58/fG5aySlm7kU3e6Zfm8BtG', '2026-01-05 10:10:00', '2026-04-10 08:45:00', 34, '10', 'FITNESS', 'Coach fitness certifie, specialiste preparation physique et circuits outdoor.', 'MATIN', 'YSF202', 220, 1),
(3, 'Trabelsi', 'Meriem', 'coach.running@ecoadventure.test', '+21620000003', '/images/users/meriem.jpg', 'COACH', '$2y$10$tywwvfCaNdS3JQnfuSyFJeGyTicHy58/fG5aySlm7kU3e6Zfm8BtG', '2026-01-07 11:25:00', '2026-04-09 19:15:00', 29, '6', 'RUNNING', 'Coach running et endurance, formatrice trail debutant et intermediaire.', 'SOIR', 'MRM303', 180, 1),
(4, 'Mansouri', 'Sarra', 'sarra.client@ecoadventure.test', '+21620000004', '/images/users/sarra.jpg', 'USER_SIMPLE', '$2y$10$tywwvfCaNdS3JQnfuSyFJeGyTicHy58/fG5aySlm7kU3e6Zfm8BtG', '2026-02-01 12:00:00', '2026-04-10 07:50:00', NULL, NULL, NULL, NULL, NULL, 'SRR404', 95, 1),
(5, 'Gharbi', 'Anis', 'anis.client@ecoadventure.test', '+21620000005', '/images/users/anis.jpg', 'USER_SIMPLE', '$2y$10$tywwvfCaNdS3JQnfuSyFJeGyTicHy58/fG5aySlm7kU3e6Zfm8BtG', '2026-02-04 15:40:00', '2026-04-08 21:05:00', NULL, NULL, NULL, NULL, NULL, 'ANS505', 145, 1),
(6, 'Kacem', 'Nour', 'nour.client@ecoadventure.test', '+21620000006', '/images/users/nour.jpg', 'USER_SIMPLE', '$2y$10$tywwvfCaNdS3JQnfuSyFJeGyTicHy58/fG5aySlm7kU3e6Zfm8BtG', '2026-02-12 16:30:00', '2026-04-10 10:05:00', NULL, NULL, NULL, NULL, NULL, 'NOU606', 70, 1);

INSERT INTO pack (id_pack, nom, type_pack, prix_base, reduction, nb_activites_max, statut_pack) VALUES
(1, 'Pack Decouverte', 'INDIVIDUEL', 180.00, 20.00, 3, 'ACTIF'),
(2, 'Pack Famille Outdoor', 'GROUPE', 420.00, 60.00, 5, 'ACTIF'),
(3, 'Pack Entreprise Challenge', 'ENTREPRISE', 1200.00, 150.00, 8, 'ACTIF'),
(4, 'Pack Explorer Legacy', 'explorer', 260.00, 30.00, 4, 'ACTIF');

INSERT INTO capacity_policy (categorie_act, capacite_totale) VALUES
('FITNESS', 30),
('RUNNING', 50),
('RANDONNEE', 40),
('CYCLISME', 25),
('NATATION', 20),
('YOGA', 22),
('FOOTBALL', 18),
('BASKETBALL', 16);

INSERT INTO activite (
    id_activite, nom, type_activite, categorie_act, niveau_act, prix, statut, image_url, id_pack
) VALUES
(1, 'Trail Carthage', 'SPORT', 'RUNNING', 'INTERMEDIAIRE', 65.00, 'DISPONIBLE', '/images/activities/trail-carthage.jpg', 1),
(2, 'Yoga Plage', 'SPORT', 'YOGA', 'DEBUTANT', 45.00, 'DISPONIBLE', '/images/activities/yoga-plage.jpg', 1),
(3, 'Randonnee Ichkeul', 'CAMPING', 'RANDONNEE', 'DEBUTANT', 80.00, 'DISPONIBLE', '/images/activities/randonnee-ichkeul.jpg', 2),
(4, 'Cyclisme Zaghouan', 'SPORT', 'CYCLISME', 'AVANCE', 95.00, 'DISPONIBLE', '/images/activities/cyclisme-zaghouan.jpg', 2),
(5, 'Challenge Fitness', 'SPORT', 'FITNESS', 'INTERMEDIAIRE', 70.00, 'DISPONIBLE', '/images/activities/challenge-fitness.jpg', 3),
(6, 'Atelier Orientation', 'INTELECTUEL', 'AUTRE', 'DEBUTANT', 35.00, 'DISPONIBLE', '/images/activities/orientation.jpg', 4);

INSERT INTO planning (
    id_planning, titre, description, date_debut, date_fin, statut, created_at, updated_at
) VALUES
(1, 'Planning Printemps Outdoor', 'Seances sportives et aventures douces pour le mois d avril.', '2026-04-12', '2026-04-30', 'ACTIF', '2026-04-01 09:00:00', '2026-04-08 11:00:00'),
(2, 'Planning Entreprise Mai', 'Programme intensif pour teams building et challenges corporate.', '2026-05-04', '2026-05-28', 'ACTIF', '2026-04-05 10:00:00', '2026-04-09 14:00:00'),
(3, 'Planning Archive Hiver', 'Ancien planning conserve pour historique.', '2026-01-10', '2026-01-30', 'ARCHIVE', '2026-01-01 08:00:00', '2026-02-01 08:00:00');

INSERT INTO seance (
    id_seance, nom, date_seance, heure_debut, heure_fin, capacite, statut_seance, id_planning, id_coach
) VALUES
(1, 'Circuit Fitness Lac', '2026-04-13', '08:00:00', '09:30:00', 20, 'PLANIFIEE', 1, 2),
(2, 'Sortie Running Medina', '2026-04-15', '18:00:00', '19:30:00', 25, 'PLANIFIEE', 1, 3),
(3, 'Yoga Recuperation', '2026-04-18', '09:00:00', '10:15:00', 18, 'PLANIFIEE', 1, 2),
(4, 'Challenge Equipe', '2026-05-07', '10:00:00', '12:00:00', 40, 'PLANIFIEE', 2, 2),
(5, 'Trail Endurance', '2026-05-12', '17:30:00', '19:30:00', 30, 'PLANIFIEE', 2, 3);

INSERT INTO evenement (
    id_evenement, titre, description, categorie_evt, date_event, lieu, nb_places, prix, statut, image_url
) VALUES
(1, 'Marathon Vert de Tunis', 'Course urbaine eco-responsable avec ravitaillement local et collecte de dechets.', 'MARATHON', '2026-05-17 07:00:00', 'Lac de Tunis', 250, 35.00, 'ACTIF', '/images/events/marathon-vert.jpg'),
(2, 'Camp Nature Ichkeul', 'Weekend nature avec randonnee guidee, observation oiseaux et bivouac encadre.', 'NATURE', '2026-06-06 09:00:00', 'Parc Ichkeul', 60, 120.00, 'ACTIF', '/images/events/camp-ichkeul.jpg'),
(3, 'Tournoi Beach Challenge', 'Competitions amicales sur plage: running, fitness et ateliers team spirit.', 'TOURNOI', '2026-07-04 16:00:00', 'Gammarth', 120, 50.00, 'ACTIF', '/images/events/beach-challenge.jpg');

INSERT INTO inscription (
    id_inscription, date_inscription, statut_inscr, montant_total, nom_user, nom_pack, id_pack, id_user
) VALUES
(1, '2026-04-02 10:20:00', 'VALIDEE', 160.00, 'Sarra Mansouri', 'Pack Decouverte', 1, 4),
(2, '2026-04-03 13:15:00', 'CONFIRMEE', 360.00, 'Anis Gharbi', 'Pack Famille Outdoor', 2, 5),
(3, '2026-04-04 17:30:00', 'EN_ATTENTE', 1050.00, 'Nour Kacem', 'Pack Entreprise Challenge', 3, 6);

INSERT INTO reservation_activite (
    id_res_act, date_reservation, statut_res, nb_personnes, id_user, id_activite
) VALUES
(1, '2026-04-12 10:00:00', 'CONFIRMEE', 1, 4, 1),
(2, '2026-04-14 11:30:00', 'CONFIRMEE', 2, 5, 3),
(3, '2026-04-16 09:20:00', 'EN_ATTENTE', 3, 6, 5),
(4, '2026-04-20 15:00:00', 'ANNULEE', 1, 4, 2);

INSERT INTO reservation_evenement (
    id_res_evt, date_reservation, statut_res, nb_billets, id_user, id_evenement
) VALUES
(1, '2026-04-05 09:00:00', 'CONFIRMEE', 2, 4, 1),
(2, '2026-04-06 12:45:00', 'CONFIRMEE', 1, 5, 2),
(3, '2026-04-07 18:10:00', 'EN_ATTENTE', 4, 6, 3);

INSERT INTO reservation_seance (
    id_reservation, date_reservation, statut, id_user, id_seance, google_event_id, google_event_link, statut_presence
) VALUES
(1, '2026-04-10 08:00:00', 'CONFIRMEE', 4, 1, 'evt_fit_001', 'https://calendar.google.com/calendar/event?eid=evt_fit_001', 'NON_MARQUE'),
(2, '2026-04-10 08:20:00', 'CONFIRMEE', 5, 2, 'evt_run_002', 'https://calendar.google.com/calendar/event?eid=evt_run_002', 'NON_MARQUE'),
(3, '2026-04-10 09:10:00', 'ANNULEE', 6, 3, NULL, NULL, 'ABSENT');

INSERT INTO reclamation (
    id_reclamation, type, contenu, statut, date_creation, id_user, reponse
) VALUES
(1, 'Reservation', 'Je souhaite modifier le nombre de billets pour le Marathon Vert.', 'EN_ATTENTE', '2026-04-08 10:30:00', 4, NULL),
(2, 'Paiement', 'Le paiement du pack Famille est passe deux fois sur mon compte.', 'TRAITEE', '2026-04-07 16:45:00', 5, 'Le remboursement du second paiement a ete declenche.'),
(3, 'Planning', 'La seance running du soir ne s affiche pas dans mon espace.', 'REJETEE', '2026-04-06 19:00:00', 6, 'La seance est visible uniquement apres confirmation de reservation.');

INSERT INTO nutrition_log (
    id, user_id, food_name, calories, log_date, protein, fat, carbs
) VALUES
(1, 4, 'Salade tunisienne avec thon', 420, '2026-04-08', 28, 18, 34),
(2, 4, 'Smoothie banane dattes', 310, '2026-04-09', 9, 6, 58),
(3, 5, 'Ojja legumes et oeufs', 520, '2026-04-08', 30, 26, 42),
(4, 6, 'Couscous poisson portion sport', 690, '2026-04-09', 38, 20, 88);

INSERT INTO recommendation_log (
    rec_id, user_id, created_at, request_json, results_json
) VALUES
(1, 4, '2026-04-08 21:00:00', '{"goal":"endurance","level":"intermediaire","budget":200}', '{"packs":[1],"activities":[1,2],"reason":"bon equilibre running et recuperation"}'),
(2, 5, '2026-04-09 09:15:00', '{"goal":"famille","level":"debutant","budget":450}', '{"packs":[2],"activities":[3],"reason":"activites accessibles en groupe"}'),
(3, 6, '2026-04-09 18:40:00', '{"goal":"team_building","level":"mixte","budget":1300}', '{"packs":[3],"activities":[5,6],"reason":"challenge equipe et atelier orientation"}');

INSERT INTO feedback_event (
    id, user_id, pack_id, action, created_at, meta_json
) VALUES
(1, 4, 1, 'view', '2026-04-02 09:58:00', '{"source":"home","device":"mobile"}'),
(2, 4, 1, 'subscribe', '2026-04-02 10:20:00', '{"price":160}'),
(3, 5, 2, 'subscribe', '2026-04-03 13:15:00', '{"price":360}'),
(4, 6, 3, 'view', '2026-04-04 17:00:00', '{"source":"recommendation"}');

INSERT INTO conversation (
    id_conversation, id_createur, titre, est_groupe, date_creation
) VALUES
(1, 4, 'Sarra et support EcoAdventure', 0, '2026-04-08 10:35:00'),
(2, 2, 'Equipe coaches avril', 1, '2026-04-07 09:00:00'),
(3, 5, 'Preparation sortie Ichkeul', 1, '2026-04-06 15:20:00');

INSERT INTO conversation_user (id_conversation, id_user) VALUES
(1, 1),
(1, 4),
(2, 1),
(2, 2),
(2, 3),
(3, 3),
(3, 5),
(3, 6);

INSERT INTO message (
    id_message, type_message, contenu, statut_message, date_envoi, date_lecture,
    date_modifier, reactions, attachments, id_conversation, id_user
) VALUES
(1, 'TEXTE', 'Bonjour, je veux modifier ma reservation pour le marathon.', 'LU', '2026-04-08 10:36:00', '2026-04-08 10:40:00', NULL, '{"1":"thumbs_up"}', '[]', 1, 4),
(2, 'TEXTE', 'Bonjour Sarra, je verifie les places disponibles et je reviens vers vous.', 'LU', '2026-04-08 10:42:00', '2026-04-08 10:45:00', NULL, '[]', '[]', 1, 1),
(3, 'TEXTE', 'Merci de confirmer les capacites des seances du 13 et du 15 avril.', 'LU', '2026-04-07 09:05:00', '2026-04-07 09:10:00', NULL, '[]', '[]', 2, 1),
(4, 'TEXTE', 'Capacite circuit fitness confirmee a 20 participants.', 'ENVOYE', '2026-04-07 09:12:00', NULL, NULL, '{"3":"ok"}', '[]', 2, 2),
(5, 'TEXTE', 'Pour Ichkeul, faut-il prevoir chaussures de randonnee ou running?', 'LU', '2026-04-06 15:22:00', '2026-04-06 15:30:00', NULL, '[]', '[]', 3, 5),
(6, 'IMAGE', 'Carte du point de depart', 'ENVOYE', '2026-04-06 15:35:00', NULL, NULL, '[]', '[{"name":"point-depart.jpg","path":"/uploads/messages/point-depart.jpg","mime":"image/jpeg"}]', 3, 3);

INSERT INTO event_rating (
    id, id_user, id_evenement, note, created_at
) VALUES
(1, 4, 1, 5, '2026-04-09 12:00:00'),
(2, 5, 2, 4, '2026-04-09 12:15:00'),
(3, 6, 3, 5, '2026-04-09 12:30:00');
