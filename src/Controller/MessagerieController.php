<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Enum\StatutMessage;
use App\Enum\TypeMessage;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserAppRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MessagerieController extends AbstractController
{
    /**
     * Page principale de la messagerie
     */
    #[Route('/messagerie/{id_user}', name: 'app_messagerie', requirements: ['id_user' => '\d+'], defaults: ['id_user' => 1])]
    #[Route('/messagerie/{id_user}/{id_conversation}', name: 'app_messagerie_selected', requirements: ['id_user' => '\d+', 'id_conversation' => '\d+'])]
    public function index(
        int $id_user,
        ?int $id_conversation,
        UserAppRepository $userAppRepo,
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo,
        EntityManagerInterface $em
    ): Response {

        $user = $userAppRepo->find($id_user);

        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        $conversations = $conversationRepo->findByParticipant($user);
        $tousLesUsers = $userAppRepo->findAll();

        $currentConv = null;
        if ($id_conversation) {
            $currentConv = $conversationRepo->find($id_conversation);
            if ($currentConv && !$currentConv->getParticipants()->contains($user)) {
                $currentConv = null;
            }
        }

        if (!$currentConv && count($conversations) > 0) {
            $currentConv = $conversations[0];
        }

        $messages = [];
        if ($currentConv) {
            $messages = $messageRepo->findBy(
                ['conversation' => $currentConv],
                ['date_envoi' => 'ASC']
            );
            
            // Mark messages from other users as read when viewing the conversation
            foreach ($messages as $message) {
                // If message is from someone else and hasn't been read yet, mark it as read
                if ($message->getUserApp()?->getId_user() !== $user->getId_user() && $message->getDate_lecture() === null) {
                    $message->setDate_lecture(new \DateTime());
                    $em->persist($message);
                }
            }
            $em->flush();
        }

        return $this->render('front/index.html.twig', [
            'conversations' => $conversations,
            'current_conv' => $currentConv,
            'messages' => $messages,
            'tous_les_users' => $tousLesUsers,
            'mon_id' => $user->getId_user(),
            'nom_utilisateur' => $user->getNom(),
            'prenom_utilisateur' => $user->getPrenom()
        ]);
    }

    /**
     * Créer une nouvelle conversation (Full Name automatique)
     */
    #[Route('/messagerie/nouvelle-conversation/{id_createur}/{id_destinataire}/{type}', name: 'app_new_conversation')]
    public function newConversation(
        int $id_createur, 
        int $id_destinataire, 
        string $type,
        UserAppRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        
        $createur = $userRepo->find($id_createur);
        $destinataire = $userRepo->find($id_destinataire);
        
        if (!$destinataire || !$createur) {
            return $this->redirectToRoute('app_messagerie', ['id_user' => $id_createur]);
        }

        $conversation = new Conversation();
        $conversation->setCreateur($createur);
        $conversation->addParticipant($createur);
        $conversation->addParticipant($destinataire);

        if ($type === 'groupe') {
            // Titre automatique : Groupe Créateur & Destinataire
            $nomGroupe = "Groupe " . $createur->getNom() . " " . $createur->getPrenom() . " & " . $destinataire->getNom() . " " . $destinataire->getPrenom();
            $conversation->setTitre($nomGroupe);
            $conversation->setEst_groupe(true);
        } else {
            // Titre automatique : Nom Complet du destinataire
            $conversation->setTitre($destinataire->getNom() . " " . $destinataire->getPrenom());
            $conversation->setEst_groupe(false);
        }

        $conversation->setDate_creation(new \DateTime());

        $em->persist($conversation);
        $em->flush();

        return $this->redirectToRoute('app_messagerie', ['id_user' => $id_createur]);
        
    }

    /**
     * Créer un nouveau groupe avec sélection multiple de membres
     */
    #[Route('/messagerie/nouveau-groupe/{id_createur}', name: 'app_create_group', methods: ['POST'])]
    public function createGroup(
        int $id_createur,
        Request $request,
        UserAppRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $createur = $userRepo->find($id_createur);
        $selectedUsers = $request->request->all()['participants'] ?? [];
        $groupName = $request->request->get('group_name', '');

        if (!$createur || empty($selectedUsers)) {
            return $this->redirectToRoute('app_messagerie', ['id_user' => $id_createur]);
        }

        // Convertir les IDs en entités UserApp
        $participants = [];
        foreach ($selectedUsers as $userId) {
            $user = $userRepo->find($userId);
            if ($user) {
                $participants[] = $user;
            }
        }

        // Ajouter le créateur s'il n'est pas déjà sélectionné
        if (!in_array($createur->getId_user(), $selectedUsers)) {
            $participants[] = $createur;
        }

        $conversation = new Conversation();
        $conversation->setCreateur($createur);

        // Si seulement 2 participants (créateur + 1 autre), créer comme conversation privée
        if (count($participants) === 2) {
            $otherUser = $participants[0] === $createur ? $participants[1] : $participants[0];
            $conversation->setTitre($otherUser->getNom() . " " . $otherUser->getPrenom());
            $conversation->setEst_groupe(false);
        } else {
            // Groupe avec plusieurs participants
            if (empty($groupName)) {
                // Nom automatique basé sur les participants
                $participantNames = array_map(function($user) {
                    return $user->getNom() . " " . $user->getPrenom();
                }, array_slice($participants, 0, 3)); // Max 3 noms dans le titre
                $finalGroupName = "Groupe " . implode(" & ", $participantNames);
                if (count($participants) > 3) {
                    $finalGroupName .= " +" . (count($participants) - 3);
                }
            } else {
                $finalGroupName = $groupName;
            }
            $conversation->setTitre($finalGroupName);
            $conversation->setEst_groupe(true);
        }

        // Ajouter tous les participants
        foreach ($participants as $participant) {
            $conversation->addParticipant($participant);
        }

        $conversation->setDate_creation(new \DateTime());

        $em->persist($conversation);
        $em->flush();

        return $this->redirectToRoute('app_messagerie', ['id_user' => $id_createur]);
    }

    #[Route('/messagerie/{id_user}/{id_conversation}/message/envoyer', name: 'app_send_message', methods: ['POST'])]
    public function sendMessage(
        int $id_user,
        int $id_conversation,
        Request $request,
        UserAppRepository $userRepo,
        ConversationRepository $conversationRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);
        $contenu = trim((string) $request->request->get('message', ''));

        if ($user && $conversation && $contenu !== '' && $conversation->getParticipants()->contains($user)) {
            $message = new Message();
            $message->setConversation($conversation);
            $message->setUserApp($user);
            $message->setContenu($contenu);
            $message->setType_message(TypeMessage::TEXTE);
            $message->setStatut_message(StatutMessage::ENVOYE);
            $message->setDate_envoi(new \DateTime());

            $em->persist($message);
            $em->flush();
        }

        return $this->redirectToRoute('app_messagerie_selected', ['id_user' => $id_user, 'id_conversation' => $id_conversation]);
    }

    #[Route('/messagerie/{id_user}/{id_conversation}/message/supprimer/{id_message}', name: 'app_delete_message')]
    public function deleteMessage(
        int $id_user,
        int $id_conversation,
        int $id_message,
        UserAppRepository $userRepo,
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);
        $message = $messageRepo->find($id_message);

        if ($user && $conversation && $message && $message->getConversation()?->getId_conversation() === $conversation->getId_conversation()) {
            // Allow deletion if user is the message author or if user is the conversation admin
            $isOwner = $message->getUserApp()?->getId_user() === $user->getId_user();
            $isAdmin = $conversation->getCreateur()?->getId_user() === $user->getId_user() && $conversation->isEst_groupe();

            if ($isOwner || $isAdmin) {
                $em->remove($message);
                $em->flush();
            }
        }

        return $this->redirectToRoute('app_messagerie_selected', ['id_user' => $id_user, 'id_conversation' => $id_conversation]);
    }

    #[Route('/messagerie/{id_user}/{id_conversation}/message/modifier/{id_message}', name: 'app_edit_message', methods: ['POST'])]
    public function editMessage(
        int $id_user,
        int $id_conversation,
        int $id_message,
        Request $request,
        UserAppRepository $userRepo,
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);
        $message = $messageRepo->find($id_message);
        $edited = trim((string) $request->request->get('edited_message', ''));

        if ($user && $conversation && $message && $edited !== '' && $message->getConversation()?->getId_conversation() === $conversation->getId_conversation() && $message->getUserApp()?->getId_user() === $user->getId_user()) {
            $message->setContenu($edited);
            $message->setDate_modifier(new \DateTime());
            $em->flush();
        }

        return $this->redirectToRoute('app_messagerie_selected', ['id_user' => $id_user, 'id_conversation' => $id_conversation]);
    }

    // Ajouter un membre à un groupe existant
    #[Route('/messagerie/groupe/{id_conversation}/ajouter-membre/{id_user_to_add}/{id_user}', name: 'app_add_group_member')]
    public function addGroupMember(
        int $id_conversation,
        int $id_user_to_add,
        int $id_user,
        ConversationRepository $conversationRepo,
        UserAppRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $conv = $conversationRepo->find($id_conversation);
        $userToAdd = $userRepo->find($id_user_to_add);
        $currentUser = $userRepo->find($id_user);

        if ($conv && $userToAdd && $currentUser && $conv->isEst_groupe() && $conv->getCreateur()?->getId_user() === $currentUser->getId_user()) {
            if (!$conv->getParticipants()->contains($userToAdd)) {
                $conv->addParticipant($userToAdd);
                $em->flush();
                $this->addFlash('success', 'Membre ajouté au groupe.');
            }
        }

        return $this->redirectToRoute('app_messagerie', ['id_user' => $id_user]);
    }

    // Modifier le titre du groupe
    #[Route('/messagerie/groupe/modifier/{id_user}/{id_conversation}', name: 'app_edit_group')]
    public function editGroup(int $id_user, int $id_conversation, Request $request, ConversationRepository $repo, EntityManagerInterface $em): Response {
        $conv = $repo->find($id_conversation);
        $nouveauTitre = $request->request->get('titre');
        if ($conv && $nouveauTitre && $conv->getCreateur()?->getId_user() === $id_user) {
            $conv->setTitre($nouveauTitre);
            $em->flush();
        }
        return $this->redirectToRoute('app_messagerie', ['id_user' => $id_user]);
    }

    // Supprimer une conversation (Privée ou Groupe)
#[Route('/messagerie/conversation/supprimer/{id_conversation}/{id_user}', name: 'app_delete_conversation')]
public function deleteConversation(int $id_conversation, int $id_user, ConversationRepository $repo, EntityManagerInterface $em): Response {
    $conv = $repo->find($id_conversation);
    if ($conv) {
        $em->remove($conv);
        $em->flush();
        $this->addFlash('success', 'Conversation supprimée.');
    }
    return $this->redirectToRoute('app_messagerie', ['id_user' => $id_user]);
}
}