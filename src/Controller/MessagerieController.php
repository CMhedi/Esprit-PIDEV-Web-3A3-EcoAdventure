<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\UserApp;
use App\Enum\StatutMessage;
use App\Enum\TypeMessage;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserAppRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MessagerieController extends AbstractController
{
    /**
     * Page principale de la messagerie
     */
    #[Route('/messagerie', name: 'app_messagerie_root', defaults: ['id_user' => 1, 'id_conversation' => null])]
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

        $conversations = $conversationRepo->findConversationsByUser($user);
        $tousLesUsers = $userAppRepo->findAll();
        $presenceByUserId = [];
        $presenceThreshold = new \DateTime('-5 minutes');
        foreach ($tousLesUsers as $candidateUser) {
            $lastMessage = $messageRepo->findOneBy(
                ['userApp' => $candidateUser],
                ['date_envoi' => 'DESC']
            );
            $lastSeen = $lastMessage?->getDate_envoi();
            $presenceByUserId[$candidateUser->getId_user()] = [
                'online' => $lastSeen !== null && $lastSeen >= $presenceThreshold,
                'last_seen' => $lastSeen?->format('Y-m-d H:i:s'),
            ];
        }

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

            // Marquer comme lus les messages des autres utilisateurs
            foreach ($messages as $message) {
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
            'presence_by_user_id' => $presenceByUserId,
            'mon_id' => $user->getId_user(),
            'nom_utilisateur' => $user->getNom(),
            'prenom_utilisateur' => $user->getPrenom()
        ]);
    }

    /**
     * Créer une nouvelle conversation privée (unique entre deux utilisateurs)
     */
    #[Route('/messagerie/nouvelle-conversation/{id_createur}/{id_destinataire}/{type}', name: 'app_new_conversation')]
    public function newConversation(
        int $id_createur,
        int $id_destinataire,
        string $type,
        UserAppRepository $userRepo,
        ConversationRepository $conversationRepo,
        EntityManagerInterface $em
    ): Response {
        $createur = $userRepo->find($id_createur);
        $destinataire = $userRepo->find($id_destinataire);

        if (!$destinataire || !$createur) {
            return $this->redirectToRoute('app_messagerie', ['id_user' => $id_createur]);
        }

        // Vérifier si une conversation privée existe déjà entre les deux
        if ($type !== 'groupe') {
            $existingConv = $conversationRepo->findOneByParticipants($createur, $destinataire);
            if ($existingConv) {
                return $this->redirectToRoute('app_messagerie_selected', [
                    'id_user' => $id_createur,
                    'id_conversation' => $existingConv->getId_conversation()
                ]);
            }
        }

        // Créer une nouvelle conversation
        $conversation = new Conversation();
        $conversation->setCreateur($createur);
        $conversation->addParticipant($createur);
        $conversation->addParticipant($destinataire);

        if ($type === 'groupe') {
            $nomGroupe = "Groupe " . $createur->getNom() . " & " . $destinataire->getNom();
            $conversation->setTitre($nomGroupe);
            $conversation->setEst_groupe(true);
        } else {
            $conversation->setTitre($destinataire->getNom() . " " . $destinataire->getPrenom());
            $conversation->setEst_groupe(false);
        }

        $conversation->setDate_creation(new \DateTime());
        $em->persist($conversation);
        $em->flush();

        return $this->redirectToRoute('app_messagerie_selected', [
            'id_user' => $id_createur,
            'id_conversation' => $conversation->getId_conversation()
        ]);
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

        $participants = [];
        foreach ($selectedUsers as $userId) {
            $user = $userRepo->find($userId);
            if ($user) {
                $participants[] = $user;
            }
        }
        if (!in_array($createur, $participants)) {
            $participants[] = $createur;
        }

        $conversation = new Conversation();
        $conversation->setCreateur($createur);

        if (count($participants) === 2) {
            $otherUser = $participants[0] === $createur ? $participants[1] : $participants[0];
            $conversation->setTitre($otherUser->getNom() . " " . $otherUser->getPrenom());
            $conversation->setEst_groupe(false);
        } else {
            if (empty($groupName)) {
                $participantNames = array_map(function($u) {
                    return $u->getNom() . " " . $u->getPrenom();
                }, array_slice($participants, 0, 3));
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

        foreach ($participants as $participant) {
            $conversation->addParticipant($participant);
        }
        $conversation->setDate_creation(new \DateTime());

        $em->persist($conversation);
        $em->flush();

        return $this->redirectToRoute('app_messagerie', ['id_user' => $id_createur]);
    }

    /**
     * Envoyer un message (texte ou fichier)
     */
    #[Route('/send/{id_user}/{id_conversation}', name: 'app_send_message', methods: ['POST'])]
    public function sendMessage(
        int $id_user,
        int $id_conversation,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        ValidatorInterface $validator
    ): Response {
        $user = $em->getRepository(UserApp::class)->find($id_user);
        $conversation = $em->getRepository(Conversation::class)->find($id_conversation);

        if (!$user || !$conversation || !$conversation->getParticipants()->contains($user)) {
            throw $this->createNotFoundException();
        }

        $message = new Message();
        $message->setUserApp($user);
        $message->setConversation($conversation);
        $message->setDate_envoi(new \DateTime());
        $message->setStatut_message(StatutMessage::ENVOYE);
        $message->setType_message(TypeMessage::TEXTE);

        $uploadedFile = $request->files->get('mediaFile');
        $textContent = trim($request->request->get('message', ''));
        $isRecordedVocale = $request->request->getBoolean('is_recorded_vocale', false);

        // Gestion du fichier
        if ($uploadedFile) {
            if (!$uploadedFile->isValid() || !$uploadedFile->isReadable()) {
                $this->addFlash('error', 'Fichier invalide ou introuvable. Veuillez reessayer.');
                return $this->redirectToRoute('app_messagerie_selected', [
                    'id_user' => $id_user,
                    'id_conversation' => $id_conversation
                ]);
            }

            $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->getClientOriginalExtension();
            $mime = $uploadedFile->getMimeType() ?? '';

            // Vérification durée vidéo (max 5 min)
            if (!$isRecordedVocale && str_starts_with($mime, 'video/')) {
                $duration = $this->getVideoDuration($uploadedFile->getPathname());
                if ($duration > 300) {
                    $this->addFlash('error', 'La vidéo ne doit pas dépasser 5 minutes.');
                    return $this->redirectToRoute('app_messagerie_selected', [
                        'id_user' => $id_user,
                        'id_conversation' => $id_conversation
                    ]);
                }
            }

            try {
                if ($isRecordedVocale) {
                    $message->setType_message(TypeMessage::VOCALE);
                } elseif ($mime === 'image/gif') {
                    $message->setType_message(TypeMessage::GIF);
                } elseif (str_starts_with($mime, 'image/')) {
                    $message->setType_message(TypeMessage::IMAGE);
                } elseif (str_starts_with($mime, 'video/')) {
                    $message->setType_message(TypeMessage::VIDEO);
                } elseif (str_starts_with($mime, 'audio/')) {
                    $message->setType_message(TypeMessage::AUDIO);
                } elseif ($mime === 'application/pdf') {
                    $message->setType_message(TypeMessage::PDF);
                }

                $targetFolder = match ($message->getType_message()) {
                    TypeMessage::IMAGE => 'images',
                    TypeMessage::GIF => 'images',
                    TypeMessage::VIDEO, TypeMessage::APPEL_VIDEO => 'video',
                    TypeMessage::AUDIO => 'Audio',
                    TypeMessage::VOCALE, TypeMessage::APPEL_AUDIO => 'Vocale',
                    TypeMessage::PDF => 'files',
                    default => 'files',
                };

                $baseUploadDir = rtrim((string) $this->getParameter('messages_upload_directory'), '/\\');
                $targetUploadDir = $baseUploadDir . DIRECTORY_SEPARATOR . $targetFolder;

                if (!is_dir($targetUploadDir)) {
                    mkdir($targetUploadDir, 0775, true);
                }

                $uploadedFile->move($targetUploadDir, $newFilename);
                $message->setContenu('/uploads/' . $targetFolder . '/' . $newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de l\'upload du fichier.');
                return $this->redirectToRoute('app_messagerie_selected', [
                    'id_user' => $id_user,
                    'id_conversation' => $id_conversation
                ]);
            }
        }

        // Gestion du texte (commentaire éventuel)
        if (!empty($textContent)) {
            if ($uploadedFile) {
                // Si un fichier est joint, on stocke le texte en commentaire (séparé par '|')
                $message->setContenu($message->getContenu() . '|' . $textContent);
            } else {
                $message->setContenu($textContent);
                if ($this->isEmojiOnlyMessage($textContent)) {
                    $message->setType_message(TypeMessage::EMOJI);
                }
            }
        } elseif (!$uploadedFile) {
            $this->addFlash('error', 'Vous ne pouvez pas envoyer un message vide.');
            return $this->redirectToRoute('app_messagerie_selected', [
                'id_user' => $id_user,
                'id_conversation' => $id_conversation
            ]);
        }

        $errors = $validator->validate($message);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('app_messagerie_selected', [
                'id_user' => $id_user,
                'id_conversation' => $id_conversation
            ]);
        }

        $em->persist($message);
        $em->flush();

        return $this->redirectToRoute('app_messagerie_selected', [
            'id_user' => $id_user,
            'id_conversation' => $id_conversation
        ]);
    }

    /**
     * Supprimer un message
     */
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
            $isOwner = $message->getUserApp()?->getId_user() === $user->getId_user();
            $isAdmin = $conversation->getCreateur()?->getId_user() === $user->getId_user() && $conversation->isEst_groupe();

            if ($isOwner || $isAdmin) {
                $em->remove($message);
                $em->flush();
            }
        }

        return $this->redirectToRoute('app_messagerie_selected', [
            'id_user' => $id_user,
            'id_conversation' => $id_conversation
        ]);
    }

    /**
     * Modifier un message (texte uniquement)
     */
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
        $edited = trim($request->request->get('edited_message', ''));

        if ($user && $conversation && $message && $edited !== ''
            && $message->getConversation()?->getId_conversation() === $conversation->getId_conversation()
            && $message->getUserApp()?->getId_user() === $user->getId_user()
            && $message->getType_message() === TypeMessage::TEXTE) {

            $message->setContenu($edited);
            $message->setDate_modifier(new \DateTime());
            $em->flush();
        }

        return $this->redirectToRoute('app_messagerie_selected', [
            'id_user' => $id_user,
            'id_conversation' => $id_conversation
        ]);
    }

    /**
     * Ajouter un membre à un groupe (admin uniquement)
     */
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

        if ($conv && $userToAdd && $currentUser && $conv->isEst_groupe()
            && $conv->getCreateur()?->getId_user() === $currentUser->getId_user()
            && !$conv->getParticipants()->contains($userToAdd)) {

            $conv->addParticipant($userToAdd);
            $em->flush();
            $this->addFlash('success', 'Membre ajouté au groupe.');
        }

        return $this->redirectToRoute('app_messagerie', ['id_user' => $id_user]);
    }

    /**
     * Renommer un groupe (admin uniquement)
     */
    #[Route('/messagerie/{id_user}/edit-group/{id_conversation}', name: 'app_edit_group')]
    public function editGroup(
        int $id_user,
        int $id_conversation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $conversation = $em->getRepository(Conversation::class)->find($id_conversation);
        $newTitle = trim($request->request->get('titre', ''));

        if ($conversation && $newTitle && $conversation->isEst_groupe()
            && $conversation->getCreateur()?->getId_user() === $id_user) {

            $conversation->setTitre($newTitle);
            $em->flush();
        }

        return $this->redirectToRoute('app_messagerie_selected', [
            'id_user' => $id_user,
            'id_conversation' => $id_conversation
        ]);
    }

    /**
     * Expulser un membre d'un groupe (admin uniquement)
     */
    #[Route('/messagerie/{id_user}/kick/{id_conversation}/{id_user_to_kick}', name: 'app_kick_member')]
    public function kickMember(
        int $id_user,
        int $id_conversation,
        int $id_user_to_kick,
        EntityManagerInterface $em
    ): Response {
        $conversation = $em->getRepository(Conversation::class)->find($id_conversation);
        $userToKick = $em->getRepository(UserApp::class)->find($id_user_to_kick);

        if ($conversation && $userToKick && $conversation->isEst_groupe()
            && $conversation->getCreateur()?->getId_user() === $id_user
            && $conversation->getParticipants()->contains($userToKick)) {

            $conversation->removeParticipant($userToKick);
            $em->flush();
            $this->addFlash('success', 'Membre retiré du groupe.');
        }

        return $this->redirectToRoute('app_messagerie_selected', [
            'id_user' => $id_user,
            'id_conversation' => $id_conversation
        ]);
    }

    /**
     * Supprimer une conversation (privée ou groupe)
     */
    #[Route('/messagerie/conversation/supprimer/{id_conversation}/{id_user}', name: 'app_delete_conversation')]
    public function deleteConversation(
        int $id_conversation,
        int $id_user,
        ConversationRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $conv = $repo->find($id_conversation);
        if ($conv) {
            // Seul le créateur peut supprimer un groupe ; pour une privée, les deux peuvent ?
            // Ici on autorise le créateur ou le participant (simplifié)
            $em->remove($conv);
            $em->flush();
            $this->addFlash('success', 'Conversation supprimée.');
        }
        return $this->redirectToRoute('app_messagerie', ['id_user' => $id_user]);
    }

    /**
     * Helper : obtenir la durée d'une vidéo (en secondes) via ffprobe
     */
    private function getVideoDuration(string $filePath): float
    {
        $ffprobe = 'ffprobe'; // Assurez-vous que ffprobe est dans le PATH
        $cmd = sprintf('%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 "%s"', $ffprobe, $filePath);
        $output = shell_exec($cmd);
        return (float) trim($output);
    }
    #[Route('/call-log/{id_user}/{id_conversation}', name: 'app_call_log', methods: ['POST'])]
public function callLog(
    int $id_user,
    int $id_conversation,
    Request $request,
    EntityManagerInterface $em
): Response {
    $user = $em->getRepository(UserApp::class)->find($id_user);
    $conversation = $em->getRepository(Conversation::class)->find($id_conversation);

    if (!$user || !$conversation || !$conversation->getParticipants()->contains($user)) {
        throw $this->createNotFoundException();
    }

    // Get call details from the request (sent by front‑end)
    $type = $request->request->get('type');        // 'audio' or 'video'
    $duration = $request->request->get('duration'); // in seconds, e.g. "204"

    if (!in_array($type, ['audio', 'video'])) {
        return $this->json(['error' => 'Invalid call type'], 400);
    }

    // Format duration
    $minutes = floor($duration / 60);
    $seconds = $duration % 60;
    $durationStr = sprintf('%d min %d s', $minutes, $seconds);

    // Create the message
    $message = new Message();
    $message->setUserApp($user);
    $message->setConversation($conversation);
    $message->setDate_envoi(new \DateTime());
    $message->setStatut_message(StatutMessage::ENVOYE);
    $message->setType_message($type === 'audio' ? TypeMessage::APPEL_AUDIO : TypeMessage::APPEL_VIDEO);
    $message->setContenu("Appel {$type} de {$durationStr}");

    $em->persist($message);
    $em->flush();

    return $this->json(['success' => true]);
}

    #[Route('/media-file/{file}', name: 'app_media_file', requirements: ['file' => '.+'])]
    public function mediaFile(string $file, Request $request): Response
    {
        $baseDir = rtrim((string) $this->getParameter('messages_upload_directory'), '/\\');
        $relative = ltrim(str_replace('\\', '/', $file), '/');

        $candidate = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolvedPath = $candidate;

        // Backward compatibility for old DB paths like /uploads/messages/xxx.ext
        if (!is_file($resolvedPath) && str_starts_with($relative, 'messages/')) {
            $fallback = $baseDir . DIRECTORY_SEPARATOR . basename($relative);
            if (is_file($fallback)) {
                $resolvedPath = $fallback;
            }
        }

        if (!is_file($resolvedPath) || !is_readable($resolvedPath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($resolvedPath);
        $disposition = $request->query->getBoolean('download', false)
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;
        $response->setContentDisposition($disposition, basename($resolvedPath));
        return $response;
    }

    private function isEmojiOnlyMessage(string $text): bool
    {
        $normalized = preg_replace('/\s+/u', '', $text) ?? '';
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/^(?:\p{Extended_Pictographic}|\x{FE0F}|\x{200D})+$/u', $normalized);
    }

    #[Route('/message/{id_message}/react/{id_user}', name: 'app_message_react', methods: ['POST'])]
    public function reactToMessage(
        int $id_message,
        int $id_user,
        Request $request,
        MessageRepository $messageRepo,
        UserAppRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $allowedEmojis = ['❤️'];
        $emoji = (string) $request->request->get('emoji', '');

        if (!in_array($emoji, $allowedEmojis, true)) {
            return $this->json(['error' => 'Emoji invalide.'], 400);
        }

        $message = $messageRepo->find($id_message);
        $user = $userRepo->find($id_user);
        if (!$message || !$user) {
            return $this->json(['error' => 'Message ou utilisateur introuvable.'], 404);
        }

        $conversation = $message->getConversation();
        if (!$conversation || !$conversation->getParticipants()->contains($user)) {
            return $this->json(['error' => 'Action non autorisee.'], 403);
        }

        $reactions = $message->getReactions();
        foreach ($allowedEmojis as $allowedEmoji) {
            $reactions[$allowedEmoji] = array_values(array_filter(
                $reactions[$allowedEmoji] ?? [],
                static fn ($uid): bool => (int) $uid !== $id_user
            ));
        }

        $selected = false;
        if (!in_array($id_user, $reactions[$emoji] ?? [], true)) {
            $reactions[$emoji][] = $id_user;
            $selected = true;
        }

        foreach ($allowedEmojis as $allowedEmoji) {
            if (empty($reactions[$allowedEmoji])) {
                unset($reactions[$allowedEmoji]);
            }
        }

        $message->setReactions($reactions);
        $em->flush();

        $counts = [];
        foreach ($allowedEmojis as $allowedEmoji) {
            $counts[$allowedEmoji] = count($reactions[$allowedEmoji] ?? []);
        }

        return $this->json([
            'success' => true,
            'counts' => $counts,
            'selected' => $selected ? $emoji : null,
        ]);
    }
}