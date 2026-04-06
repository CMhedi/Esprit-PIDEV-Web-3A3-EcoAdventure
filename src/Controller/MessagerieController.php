<?php

namespace App\Controller;

use App\Entity\Conversation;
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
    public function index(
        int $id_user, 
        UserAppRepository $userAppRepo,
        ConversationRepository $conversationRepo, 
        MessageRepository $messageRepo
    ): Response {

        $user = $userAppRepo->find($id_user);

        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        $conversations = $conversationRepo->findByParticipant($user);
        $tousLesUsers = $userAppRepo->findAll();
        
        $messages = [];
        if (count($conversations) > 0) {
            // Utilisation du nom de propriété 'conversation' (relation Doctrine)
            $messages = $messageRepo->findBy(
                ['conversation' => $conversations[0]], 
                ['date_envoi' => 'ASC']
            );
        }

        return $this->render('front/index.html.twig', [
            'conversations' => $conversations,
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