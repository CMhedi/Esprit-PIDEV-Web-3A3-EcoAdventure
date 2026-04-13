<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\UserApp;
use App\Enum\RoleUser;
use App\Enum\StatutMessage;
use App\Enum\TypeMessage;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserAppRepository;
use App\Service\ContentModerationService;
use App\Service\GeminiGifChatService;
use App\Service\TextCorrectionService;
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
    #[Route('/messagerie/open', name: 'app_messagerie_open')]
    public function openCurrentSessionMessenger(
        Request $request
    ): Response {
        $sessionUser = $this->getUser();
        if ($sessionUser instanceof UserApp) {
            // Always open the user-style messenger from the navbar icon,
            // even for admins (admin keeps back-office messenger via admin routes/menu).
            return $this->redirectToRoute('app_messagerie', ['id_user' => $sessionUser->getId_user()]);
        }

        $this->addFlash('error', 'Connectez-vous d\'abord pour ouvrir la messagerie.');
        return $this->redirectToRoute('app_login');
    }

    /**
     * Vérifier le rôle de l'utilisateur et rediriger automatiquement
     */
    #[Route('/messagerie/auto/{id_user}', name: 'app_messagerie_auto', requirements: ['id_user' => '\d+'])]
    public function autoRedirectByRole(
        int $id_user,
        UserAppRepository $userAppRepo
    ): Response {
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof UserApp) {
            $id_user = $authenticatedUser->getId_user();
        }

        $user = $userAppRepo->find($id_user);
        
        if (!$user) {
            // Si l'utilisateur n'existe pas, rediriger vers l'accueil
            return $this->redirectToRoute('app_home');
        }

        
        // Vérifier le rôle de l'utilisateur
        $role = $user->getRole();
        
        if ($role === RoleUser::ADMIN) {
            return $this->redirectToRoute('admin_messagerie_index');
        }

        return $this->redirectToRoute('app_messagerie', ['id_user' => $id_user]);
    }

    /**
     * Page principale de la messagerie
     */
    #[Route('/messagerie', name: 'app_messagerie_root', defaults: ['id_user' => null, 'id_conversation' => null])]
    #[Route('/messagerie/{id_user}', name: 'app_messagerie', requirements: ['id_user' => '\d+'], defaults: ['id_user' => null])]
    #[Route('/messagerie/{id_user}/{id_conversation}', name: 'app_messagerie_selected', requirements: ['id_user' => '\d+', 'id_conversation' => '\d+'])]
    public function index(
        ?int $id_user,
        ?int $id_conversation,
        Request $request,
        UserAppRepository $userAppRepo,
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo,
        EntityManagerInterface $em
    ): Response {
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof UserApp) {
            $id_user = $authenticatedUser->getId_user();
        }

        if (!$id_user) {
            $this->addFlash('error', 'Connectez-vous pour accéder à votre messagerie.');
            return $this->redirectToRoute('app_login');
        }

        $user = $userAppRepo->find($id_user);
        if (!$user) {
            return $this->redirectToRoute('app_home');
        }

        if ($user->getRole() === RoleUser::ADMIN) {
            return $this->redirectToRoute('admin_messagerie_index');
        }

        $user->setLast_seen(new \DateTime());
        $em->persist($user);

        $conversations = $conversationRepo->findConversationsByUser($user);
        $tousLesUsers = $userAppRepo->findAll();
        $presenceByUserId = [];
        $presenceThreshold = new \DateTime('-5 minutes');
        foreach ($tousLesUsers as $candidateUser) {
            $lastSeen = $candidateUser->getLast_seen();
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

        $messages = [];
        $currentConvLastContact = null;
        if ($currentConv) {
            $messages = $messageRepo->findMessagesForConversation($currentConv);
            if (!empty($messages)) {
                $lastMessage = end($messages);
                $currentConvLastContact = $lastMessage?->getDate_envoi();
                reset($messages);
            }
        }

        $unreadByConversationId = $conversationRepo->getUnreadCountsForUser($user);
        $totalUnreadCount = array_sum($unreadByConversationId);

        $em->flush();

        return $this->render('front/index.html.twig', [
            'conversations' => $conversations,
            'current_conv' => $currentConv,
            'messages' => $messages,
            'current_conv_last_contact' => $currentConvLastContact,
            'tous_les_users' => $tousLesUsers,
            'presence_by_user_id' => $presenceByUserId,
            'unread_by_conversation_id' => $unreadByConversationId,
            'total_unread_count' => $totalUnreadCount,
            'mon_id' => $user->getId_user(),
            'nom_utilisateur' => $user->getNom(),
            'prenom_utilisateur' => $user->getPrenom(),
            'gemini_assistant_email' => (string) ($_ENV['GEMINI_ASSISTANT_EMAIL'] ?? 'gemini.bot@ecoadventure.local'),
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
                if ($existingConv->isPrivateBlocked()) {
                    $this->addFlash('error', 'Cette conversation privée a été bloquée par l\'administration.');
                    return $this->redirectToRoute('app_messagerie', ['id_user' => $id_createur]);
                }

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
        ValidatorInterface $validator,
        ConversationRepository $conversationRepo,
        UserAppRepository $userRepo,
        MessageRepository $messageRepo,
        ContentModerationService $moderationService,
        GeminiGifChatService $geminiGifChatService
    ): Response {
        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);

        if (!$user) {
            throw $this->createNotFoundException();
        }

        if (!$conversation || !$conversation->getParticipants()->contains($user)) {
            $idDestinataire = (int) $request->request->get('id_destinataire', 0);
            if ($idDestinataire > 0 && $idDestinataire !== $id_user) {
                $destinataire = $userRepo->find($idDestinataire);
                if ($destinataire) {
                    $conversation = $conversationRepo->findOneByParticipants($user, $destinataire);
                    if ($conversation && $conversation->isPrivateBlocked()) {
                        $this->addFlash('error', 'Cette conversation privée a été bloquée par l\'administration.');
                        return $this->redirectToRoute('app_messagerie', ['id_user' => $id_user]);
                    }

                    if (!$conversation) {
                        $conversation = new Conversation();
                        $conversation->setCreateur($user);
                        $conversation->addParticipant($user);
                        $conversation->addParticipant($destinataire);
                        $conversation->setTitre($destinataire->getNom() . ' ' . $destinataire->getPrenom());
                        $conversation->setEst_groupe(false);
                        $conversation->setDate_creation(new \DateTime());
                        $em->persist($conversation);
                        $em->flush();
                    }
                }
            }
        }

        if (!$conversation || !$conversation->getParticipants()->contains($user)) {
            throw $this->createNotFoundException();
        }

        if (!$conversation->isEst_groupe() && $conversation->isPrivateBlocked()) {
            $this->addFlash('error', 'Cette conversation privée est bloquée. Vous pouvez continuer à discuter dans les groupes.');
            return $this->redirectToRoute('app_messagerie_selected', [
                'id_user' => $id_user,
                'id_conversation' => $conversation->getId_conversation()
            ]);
        }

        $message = new Message();
        $message->setUserApp($user);
        $message->setConversation($conversation);
        $message->setDate_envoi(new \DateTime());
        $message->setStatut_message(StatutMessage::ENVOYE);
        $message->setType_message(TypeMessage::TEXTE);

        $uploadedFiles = $request->files->all('mediaFiles');
        $textContent = trim($request->request->get('message', ''));
        $gifUrl = trim((string) $request->request->get('gif_url', ''));
        $vocalBlobBase64 = trim((string) $request->request->get('vocal_blob_base64', ''));
        $vocalBlobMime = trim((string) $request->request->get('vocal_blob_mime', 'audio/webm'));
        $vocalBlobName = trim((string) $request->request->get('vocal_blob_name', 'vocale-' . time() . '.webm'));
        $isRecordedVocale = $request->request->getBoolean('is_recorded_vocale', false);
        $uploadedFiles = array_values(array_filter(is_array($uploadedFiles) ? $uploadedFiles : ($uploadedFiles ? [$uploadedFiles] : [])));

        if ($textContent !== '' && $moderationService->containsProhibitedContent($textContent)) {
            $this->addFlash('error', 'Message bloque: contenu inapproprie detecte.');
            return $this->redirectToRoute('app_messagerie_selected', [
                'id_user' => $id_user,
                'id_conversation' => $id_conversation
            ]);
        }

        if (count($uploadedFiles) > 3) {
            $this->addFlash('error', 'Vous pouvez envoyer au maximum 3 fichiers par message.');
            return $this->redirectToRoute('app_messagerie_selected', [
                'id_user' => $id_user,
                'id_conversation' => $id_conversation
            ]);
        }

        // Gestion du GIF choisi depuis le picker (telechargement local vers /uploads/Gifs)
        if ($gifUrl !== '' && filter_var($gifUrl, FILTER_VALIDATE_URL)) {
            $storedGif = $geminiGifChatService->downloadGifToLocal(
                $gifUrl,
                (string) $this->getParameter('messages_upload_directory')
            );
            if ($storedGif) {
                $message->setType_message(TypeMessage::GIF);
                $message->setAttachments([[
                    'path' => $storedGif['path'],
                    'name' => $storedGif['name'],
                    'mime' => $storedGif['mime'],
                    'type' => TypeMessage::GIF->value,
                ]]);
            } else {
                $this->addFlash('error', 'Impossible de telecharger ce GIF pour le moment.');
                return $this->redirectToRoute('app_messagerie_selected', [
                    'id_user' => $id_user,
                    'id_conversation' => $id_conversation
                ]);
            }
        }

        // Gestion du fichier
        if (!empty($uploadedFiles)) {
            $attachments = [];

            try {
                foreach ($uploadedFiles as $index => $uploadedFile) {
                    if (!$uploadedFile->isValid() || !$uploadedFile->isReadable()) {
                        throw new FileException('Invalid uploaded file.');
                    }

                    $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->getClientOriginalExtension();
                    $mime = $uploadedFile->getMimeType() ?? '';

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

                    $attachmentType = $isRecordedVocale && $index === 0
                        ? TypeMessage::VOCALE
                        : match (true) {
                            $mime === 'image/gif' => TypeMessage::GIF,
                            str_starts_with($mime, 'image/') => TypeMessage::IMAGE,
                            str_starts_with($mime, 'video/') => TypeMessage::VIDEO,
                            str_starts_with($mime, 'audio/') => TypeMessage::AUDIO,
                            $mime === 'application/pdf' => TypeMessage::PDF,
                            default => TypeMessage::TEXTE,
                        };

                    if ($index === 0) {
                        $message->setType_message($attachmentType);
                    }

                    $targetFolder = match ($attachmentType) {
                        TypeMessage::IMAGE => 'images',
                        TypeMessage::GIF => 'Gifs',
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
                    $attachments[] = [
                        'path' => '/uploads/' . $targetFolder . '/' . $newFilename,
                        'name' => $uploadedFile->getClientOriginalName(),
                        'mime' => $mime,
                        'type' => $attachmentType->value,
                    ];
                }

                $message->setAttachments($attachments);
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de l\'upload du fichier.');
                return $this->redirectToRoute('app_messagerie_selected', [
                    'id_user' => $id_user,
                    'id_conversation' => $id_conversation
                ]);
            }
        }

        // Fallback mobile: vocal en base64 quand l'input file n'est pas supporte
        if ($vocalBlobBase64 !== '' && empty($uploadedFiles)) {
            $baseUploadDir = rtrim((string) $this->getParameter('messages_upload_directory'), '/\\');
            $targetFolder = 'Vocale';
            $targetUploadDir = $baseUploadDir . DIRECTORY_SEPARATOR . $targetFolder;

            if (!is_dir($targetUploadDir)) {
                mkdir($targetUploadDir, 0775, true);
            }

            $decoded = base64_decode($vocalBlobBase64, true);
            if ($decoded === false || $decoded === '') {
                $this->addFlash('error', 'Enregistrement vocal invalide.');
                return $this->redirectToRoute('app_messagerie_selected', [
                    'id_user' => $id_user,
                    'id_conversation' => $id_conversation
                ]);
            }

            $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', pathinfo($vocalBlobName, PATHINFO_FILENAME) ?: 'vocale');
            $extension = str_contains(strtolower($vocalBlobMime), 'ogg') ? 'ogg' : 'webm';
            $newFilename = $safeBase . '-' . uniqid() . '.' . $extension;
            $targetPath = $targetUploadDir . DIRECTORY_SEPARATOR . $newFilename;

            if (@file_put_contents($targetPath, $decoded) === false) {
                $this->addFlash('error', 'Impossible d\'enregistrer le message vocal.');
                return $this->redirectToRoute('app_messagerie_selected', [
                    'id_user' => $id_user,
                    'id_conversation' => $id_conversation
                ]);
            }

            $message->setType_message(TypeMessage::VOCALE);
            $message->setAttachments([[
                'path' => '/uploads/' . $targetFolder . '/' . $newFilename,
                'name' => $vocalBlobName,
                'mime' => $vocalBlobMime,
                'type' => TypeMessage::VOCALE->value,
            ]]);
        }

        // Gestion du texte (commentaire éventuel)
        if (!empty($textContent)) {
            if (!empty($uploadedFiles) || $gifUrl !== '') {
                $message->setContenu($textContent);
            } else {
                $message->setContenu($textContent);
                if ($this->isEmojiOnlyMessage($textContent)) {
                    $message->setType_message(TypeMessage::EMOJI);
                }
            }
        } elseif (empty($uploadedFiles) && $gifUrl === '' && $vocalBlobBase64 === '') {
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

        $this->sendAutomatedGeminiReplyIfNeeded(
            $textContent,
            $user,
            $conversation,
            $em,
            $userRepo,
            $messageRepo,
            $geminiGifChatService
        );

        return $this->redirectToRoute('app_messagerie_selected', [
            'id_user' => $id_user,
            'id_conversation' => $conversation->getId_conversation()
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
        ContentModerationService $moderationService,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);
        $message = $messageRepo->find($id_message);
        $edited = trim($request->request->get('edited_message', ''));

        if ($edited !== '' && $moderationService->containsProhibitedContent($edited)) {
            $this->addFlash('error', 'Modification bloquee: contenu inapproprie detecte.');
            return $this->redirectToRoute('app_messagerie_selected', [
                'id_user' => $id_user,
                'id_conversation' => $id_conversation
            ]);
        }

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
        $response = $this->buildMediaResponse($file, $request);
        $disposition = $request->query->getBoolean('download', false)
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;
        $response->setContentDisposition($disposition, basename($response->getFile()->getPathname()));
        return $response;
    }

    #[Route('/media', name: 'app_media_by_path', methods: ['GET'])]
    public function mediaByPath(Request $request): Response
    {
        $path = (string) $request->query->get('path', '');
        if ($path === '') {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $relative = str_starts_with($path, '/uploads/') ? substr($path, 9) : ltrim($path, '/');
        $response = $this->buildMediaResponse($relative, $request);
        $disposition = $request->query->getBoolean('download', false)
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;
        $response->setContentDisposition($disposition, basename($response->getFile()->getPathname()));

        return $response;
    }

    private function buildMediaResponse(string $file, Request $request): BinaryFileResponse
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

        return new BinaryFileResponse($resolvedPath);
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

    #[Route('/messagerie/poll/{id_user}/{id_conversation}', name: 'app_messagerie_poll', methods: ['GET'])]
    public function pollConversation(
        int $id_user,
        int $id_conversation,
        UserAppRepository $userRepo,
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo,
        Request $request
    ): Response {
        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);
        if (!$user || !$conversation || !$conversation->getParticipants()->contains($user)) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $lastSeenId = (int) $request->query->get('last_seen_id', 0);
        $stats = $messageRepo->getLatestIdAndIncomingCount($conversation, $user, $lastSeenId);

        return $this->json([
            'latest_id' => $stats['latest_id'],
            'incoming_count' => $stats['incoming_count'],
        ]);
    }

    #[Route('/messagerie/read/{id_user}/{id_conversation}', name: 'app_messagerie_read', methods: ['POST'])]
    public function markConversationRead(
        int $id_user,
        int $id_conversation,
        UserAppRepository $userRepo,
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo
    ): Response {
        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);

        if (!$user || !$conversation || !$conversation->getParticipants()->contains($user)) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $messageRepo->markConversationAsRead($conversation, $user);
        $unreadByConversationId = $conversationRepo->getUnreadCountsForUser($user);

        return $this->json([
            'success' => true,
            'conversation_unread' => (int) ($unreadByConversationId[$id_conversation] ?? 0),
            'total_unread' => array_sum($unreadByConversationId),
        ]);
    }

    #[Route('/messagerie/ai/{id_user}/{id_conversation}', name: 'app_messagerie_ai_reply', methods: ['POST'])]
    public function askGemini(
        int $id_user,
        int $id_conversation,
        Request $request,
        UserAppRepository $userRepo,
        ConversationRepository $conversationRepo,
        EntityManagerInterface $em,
        GeminiGifChatService $geminiGifChatService
    ): Response {
        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);
        if (!$user || !$conversation || !$conversation->getParticipants()->contains($user)) {
            return $this->json(['error' => 'Conversation introuvable'], 404);
        }

        $prompt = trim((string) $request->request->get('prompt', ''));
        if ($prompt === '') {
            return $this->json(['error' => 'Prompt vide'], 422);
        }

        $assistant = $this->getOrCreateGeminiAssistant($userRepo, $em);
        if (!$conversation->getParticipants()->contains($assistant)) {
            $conversation->addParticipant($assistant);
            $em->persist($conversation);
        }

        $replyText = $geminiGifChatService->generateReply($prompt);
        $aiMessage = new Message();
        $aiMessage->setUserApp($assistant);
        $aiMessage->setConversation($conversation);
        $aiMessage->setDate_envoi(new \DateTime());
        $aiMessage->setStatut_message(StatutMessage::ENVOYE);
        $aiMessage->setType_message(TypeMessage::TEXTE);
        $aiMessage->setContenu($replyText);
        $em->persist($aiMessage);

        $gifUrl = $geminiGifChatService->searchGifUrl($prompt);
        if ($gifUrl) {
            $gifMessage = new Message();
            $gifMessage->setUserApp($assistant);
            $gifMessage->setConversation($conversation);
            $gifMessage->setDate_envoi(new \DateTime());
            $gifMessage->setStatut_message(StatutMessage::ENVOYE);
            $gifMessage->setType_message(TypeMessage::GIF);
            $gifMessage->setAttachments([[
                'path' => $gifUrl,
                'name' => 'giphy.gif',
                'mime' => 'image/gif',
                'type' => TypeMessage::GIF->value,
            ]]);
            $em->persist($gifMessage);
        }

        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/messagerie/correct/{id_user}/{id_conversation}', name: 'app_messagerie_correct_text', methods: ['POST'])]
    public function correctMessageText(
        int $id_user,
        int $id_conversation,
        Request $request,
        UserAppRepository $userRepo,
        ConversationRepository $conversationRepo,
        TextCorrectionService $textCorrectionService
    ): Response {
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof UserApp) {
            $id_user = $authenticatedUser->getId_user();
        }

        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);
        if (!$user || !$conversation || !$conversation->getParticipants()->contains($user)) {
            return $this->json(['success' => false, 'message' => 'Conversation introuvable.'], 404);
        }

        $text = trim((string) $request->request->get('text', ''));
        if ($text === '') {
            return $this->json(['success' => false, 'message' => 'Veuillez saisir un texte a corriger.'], 422);
        }

        $result = $textCorrectionService->correctFrenchText($text);
        $status = $result['success'] ? 200 : 503;

        return $this->json($result, $status);
    }

    #[Route('/messagerie/ai/open/{id_user}', name: 'app_messagerie_ai_open', requirements: ['id_user' => '\d+'])]
    public function openGeminiConversation(
        int $id_user,
        UserAppRepository $userRepo,
        ConversationRepository $conversationRepo,
        EntityManagerInterface $em
    ): Response {
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof UserApp) {
            $id_user = $authenticatedUser->getId_user();
        }

        $user = $userRepo->find($id_user);
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $assistant = $this->getOrCreateGeminiAssistant($userRepo, $em);
        $conversation = $conversationRepo->findOneByParticipants($user, $assistant);

        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setCreateur($user);
            $conversation->addParticipant($user);
            $conversation->addParticipant($assistant);
            $conversation->setTitre('Assistant IA Groq');
            $conversation->setEst_groupe(false);
            $conversation->setDate_creation(new \DateTime());
            $em->persist($conversation);
            $em->flush();
        }

        return $this->redirectToRoute('app_messagerie_selected', [
            'id_user' => $id_user,
            'id_conversation' => $conversation->getId_conversation(),
        ]);
    }

    #[Route('/messagerie/gif/search', name: 'app_messagerie_gif_search', methods: ['GET'])]
    public function searchGif(
        Request $request,
        GeminiGifChatService $geminiGifChatService
    ): Response {
        $query = trim((string) $request->query->get('q', ''));
        $gifs = $geminiGifChatService->searchGifUrls($query, 12);

        return $this->json([
            'success' => true,
            'items' => $gifs,
        ]);
    }

    private function sendAutomatedGeminiReplyIfNeeded(
        string $textContent,
        UserApp $sender,
        Conversation $conversation,
        EntityManagerInterface $em,
        UserAppRepository $userRepo,
        MessageRepository $messageRepo,
        GeminiGifChatService $geminiGifChatService
    ): void {
        if ($textContent === '') {
            return;
        }

        $trimmed = trim($textContent);
        $assistantEmail = (string) ($_ENV['GEMINI_ASSISTANT_EMAIL'] ?? 'gemini.bot@ecoadventure.local');
        $isAssistantConversation = false;
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getEmail() === $assistantEmail) {
                $isAssistantConversation = true;
                break;
            }
        }

        $needsSummary = str_starts_with($trimmed, '/resume') || str_starts_with($trimmed, '/summary');
        $needsLongMessage = str_starts_with($trimmed, '/long');
        $needsReply = $isAssistantConversation || str_starts_with($trimmed, '/ai ') || str_starts_with($trimmed, '@gemini ');
        $needsGif = str_starts_with($trimmed, '/gif ');
        if (!$needsReply && !$needsGif && !$needsSummary && !$needsLongMessage) {
            return;
        }

        $assistant = $this->getOrCreateGeminiAssistant($userRepo, $em);
        if ($assistant->getId_user() === $sender->getId_user()) {
            return;
        }
        if (!$conversation->getParticipants()->contains($assistant)) {
            $conversation->addParticipant($assistant);
            $em->persist($conversation);
        }

        if ($needsSummary) {
            $conversationMessages = $messageRepo->findMessagesForConversation($conversation);
            $lines = [];
            foreach ($conversationMessages as $msg) {
                $author = $msg->getUserApp()?->getPrenom() ?: 'User';
                $content = trim((string) ($msg->getContenu() ?? ''));
                if ($content !== '') {
                    $lines[] = $author . ': ' . $content;
                }
            }
            $summaryText = $geminiGifChatService->generateConversationSummary(implode("\n", $lines));

            $summaryMessage = new Message();
            $summaryMessage->setUserApp($assistant);
            $summaryMessage->setConversation($conversation);
            $summaryMessage->setDate_envoi(new \DateTime());
            $summaryMessage->setStatut_message(StatutMessage::ENVOYE);
            $summaryMessage->setType_message(TypeMessage::TEXTE);
            $summaryMessage->setContenu($summaryText);
            $em->persist($summaryMessage);
        }

        if ($needsLongMessage) {
            $subject = trim(preg_replace('/^\/long\\s*/i', '', $trimmed) ?? '');
            $longText = $geminiGifChatService->generateLongMessage($subject);

            $longMessage = new Message();
            $longMessage->setUserApp($assistant);
            $longMessage->setConversation($conversation);
            $longMessage->setDate_envoi(new \DateTime());
            $longMessage->setStatut_message(StatutMessage::ENVOYE);
            $longMessage->setType_message(TypeMessage::TEXTE);
            $longMessage->setContenu($longText);
            $em->persist($longMessage);
        }

        if ($needsReply && !$needsGif && !$needsSummary && !$needsLongMessage) {
            $prompt = $trimmed;
            if (preg_match('/^(\/ai|@gemini)\s+/i', $trimmed)) {
                $prompt = trim(preg_replace('/^(\/ai|@gemini)\s+/i', '', $trimmed) ?? '');
            }
            $replyText = $prompt !== ''
                ? $geminiGifChatService->generateReply($prompt)
                : 'Ecris votre question apres /ai ou @gemini.';

            $aiMessage = new Message();
            $aiMessage->setUserApp($assistant);
            $aiMessage->setConversation($conversation);
            $aiMessage->setDate_envoi(new \DateTime());
            $aiMessage->setStatut_message(StatutMessage::ENVOYE);
            $aiMessage->setType_message(TypeMessage::TEXTE);
            $aiMessage->setContenu($replyText);
            $em->persist($aiMessage);
        }

        if ($needsGif) {
            $query = trim(preg_replace('/^\/gif\s+/i', '', $trimmed) ?? '');
            $gifUrl = $geminiGifChatService->searchGifUrl($query);
            if ($gifUrl) {
                $gifMessage = new Message();
                $gifMessage->setUserApp($assistant);
                $gifMessage->setConversation($conversation);
                $gifMessage->setDate_envoi(new \DateTime());
                $gifMessage->setStatut_message(StatutMessage::ENVOYE);
                $gifMessage->setType_message(TypeMessage::GIF);
                $gifMessage->setAttachments([[
                    'path' => $gifUrl,
                    'name' => 'gif-' . uniqid() . '.gif',
                    'mime' => 'image/gif',
                    'type' => TypeMessage::GIF->value,
                ]]);
                $em->persist($gifMessage);
            } else {
                $fallback = new Message();
                $fallback->setUserApp($assistant);
                $fallback->setConversation($conversation);
                $fallback->setDate_envoi(new \DateTime());
                $fallback->setStatut_message(StatutMessage::ENVOYE);
                $fallback->setType_message(TypeMessage::TEXTE);
                $fallback->setContenu('Aucun GIF trouve. Verifiez GIPHY_API_KEY ou essayez un autre mot-cle.');
                $em->persist($fallback);
            }
        }

        $em->flush();
    }

    private function getOrCreateGeminiAssistant(UserAppRepository $userRepo, EntityManagerInterface $em): UserApp
    {
        $assistantEmail = (string) ($_ENV['GEMINI_ASSISTANT_EMAIL'] ?? 'gemini.bot@ecoadventure.local');
        $assistant = $userRepo->findOneBy(['email' => $assistantEmail]);
        if ($assistant instanceof UserApp) {
            return $assistant;
        }

        $assistant = new UserApp();
        $assistant->setNom('Assistant');
        $assistant->setPrenom('Groq');
        $assistant->setEmail($assistantEmail);
        $assistant->setRole(RoleUser::USER_SIMPLE);
        $assistant->setMot_de_passe(password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT));
        $assistant->setDate_creation(new \DateTime());
        $assistant->setLast_seen(new \DateTime());

        $em->persist($assistant);
        $em->flush();

        return $assistant;
    }
}
