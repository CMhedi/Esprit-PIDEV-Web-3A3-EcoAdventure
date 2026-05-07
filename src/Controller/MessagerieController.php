<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\UserApp;
use App\Enum\PrioriteMessage;
use App\Enum\RoleUser;
use App\Enum\StatutMessage;
use App\Enum\TypeMessage;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use App\Repository\UserAppRepository;
use App\Service\ContentModerationService;
use App\Service\GeminiGifChatService;
use App\Service\MessagingAccessManager;
use App\Service\TextCorrectionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MessagerieController extends AbstractController
{
    public function __construct(
        private readonly MessagingAccessManager $messagingAccessManager
    ) {
    }

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

        $allUsers = $userAppRepo->findAll();
        $conversations = $conversationRepo->findConversationsByUser($user);
        $tousLesUsers = array_values(array_filter(
            $allUsers,
            fn (UserApp $candidateUser): bool => !$this->isGeminiAssistantUser($candidateUser)
        ));
        $presenceByUserId = [];
        $presenceThreshold = new \DateTime('-5 minutes');
        foreach ($allUsers as $candidateUser) {
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
        $currentConvCallTargetIds = [];
        $currentConvCallTargetNames = [];
        $currentConvCallTargetLabel = $currentConv?->getTitre() ?? '';
        if ($currentConv) {
            $messages = $messageRepo->findMessagesForConversation($currentConv);
            $messages = $this->sanitizeConversationMessagesForDisplay($messages);
            $messageRepo->markConversationAsRead($currentConv, $user);
            [$currentConvCallTargetIds, $currentConvCallTargetNames] = $this->resolveCallableParticipants($currentConv, $user);
            if ($currentConv->isEst_groupe()) {
                $currentConvCallTargetLabel = trim((string) $currentConv->getTitre()) ?: 'Groupe';
            } elseif ($currentConvCallTargetNames !== []) {
                $currentConvCallTargetLabel = $currentConvCallTargetNames[0];
            }
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
            'current_conv_call_blocked' => $this->messagingAccessManager->isConversationCallBlocked($currentConv),
            'current_conv_call_target_ids' => $currentConvCallTargetIds,
            'current_conv_call_target_names' => $currentConvCallTargetNames,
            'current_conv_call_target_label' => $currentConvCallTargetLabel,
            'tous_les_users' => $tousLesUsers,
            'presence_by_user_id' => $presenceByUserId,
            'unread_by_conversation_id' => $unreadByConversationId,
            'total_unread_count' => $totalUnreadCount,
            'mon_id' => $user->getId_user(),
            'nom_utilisateur' => $user->getNom(),
            'prenom_utilisateur' => $user->getPrenom(),
            'user_email' => $user->getEmail(),
            'gemini_assistant_email' => (string) ($_ENV['GEMINI_ASSISTANT_EMAIL'] ?? 'gemini.bot@ecoadventure.local'),
            'jitsi_domain' => (string) ($_ENV['JITSI_DOMAIN'] ?? 'meet.jit.si'),
            'jitsi_room_prefix' => (string) ($_ENV['JITSI_ROOM_PREFIX'] ?? 'ecoadventure'),
            'jitsi_popup_mode' => (string) ($_ENV['JITSI_POPUP_MODE'] ?? 'auto'),
            'call_provider' => (string) ($_ENV['CALL_PROVIDER'] ?? 'webrtc'),
            'webrtc_ice_servers' => $this->buildWebRtcIceServers(),
        ]);
    }

    #[Route('/messagerie/{id_user}/{id_conversation}/call-window', name: 'app_messagerie_call_window', requirements: ['id_user' => '\d+', 'id_conversation' => '\d+'])]
    public function callWindow(
        int $id_user,
        int $id_conversation,
        UserAppRepository $userAppRepo,
        ConversationRepository $conversationRepo
    ): Response {
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof UserApp) {
            $id_user = $authenticatedUser->getId_user();
        }

        $user = $userAppRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);

        if (!$user || !$conversation || !$this->canAccessConversation($user, $conversation)) {
            return $this->redirectToRoute('app_messagerie', ['id_user' => $id_user]);
        }

        return $this->render('front/call_window.html.twig', [
            'mon_id' => $user->getId_user(),
            'conversation' => $conversation,
            'call_blocked' => $this->messagingAccessManager->isConversationCallBlocked($conversation),
            'current_user_display_name' => $this->buildUserDisplayName($user),
            'call_target_display_name' => $this->resolveConversationDisplayName($conversation, $user),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildWebRtcIceServers(): array
    {
        $servers = [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302'],
        ];

        $turnUrl = trim((string) ($_ENV['WEBRTC_TURN_URL'] ?? ''));
        if ($turnUrl !== '') {
            $turnServer = ['urls' => $turnUrl];

            $turnUsername = trim((string) ($_ENV['WEBRTC_TURN_USERNAME'] ?? ''));
            if ($turnUsername !== '') {
                $turnServer['username'] = $turnUsername;
            }

            $turnPassword = trim((string) ($_ENV['WEBRTC_TURN_PASSWORD'] ?? ''));
            if ($turnPassword !== '') {
                $turnServer['credential'] = $turnPassword;
            }

            $servers[] = $turnServer;
        }

        return $servers;
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

        if ($type === 'groupe' && $this->isGeminiAssistantUser($destinataire)) {
            $this->addFlash('error', 'Le chatbot IA ne peut pas etre ajoute comme membre normal d un groupe.');
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
            if ($user && !$this->isGeminiAssistantUser($user)) {
                $participants[] = $user;
            }
        }
        if ($participants === []) {
            $this->addFlash('error', 'Le chatbot IA ne peut pas etre ajoute comme membre normal d un groupe.');
            return $this->redirectToRoute('app_messagerie', ['id_user' => $id_createur]);
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
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof UserApp) {
            $id_user = $authenticatedUser->getId_user();
        }

        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);
        $isAdminSender = $user instanceof UserApp && $user->getRole() === RoleUser::ADMIN;

        if (!$user) {
            throw $this->createNotFoundException();
        }

        if (!$conversation || !$this->canAccessConversation($user, $conversation)) {
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

        if (!$conversation || !$this->canAccessConversation($user, $conversation)) {
            throw $this->createNotFoundException();
        }

        if ($isAdminSender && !$conversation->getParticipants()->contains($user)) {
            $conversation->addParticipant($user);
            $em->persist($conversation);
        }

        if (!$conversation->isEst_groupe() && $conversation->isPrivateBlocked()) {
            $this->addFlash('error', 'Cette conversation privée est bloquée. Vous pouvez continuer à discuter dans les groupes.');
            return $isAdminSender
                ? $this->redirectToRoute('admin_messagerie_view', ['id' => $conversation->getId_conversation()])
                : $this->redirectToRoute('app_messagerie_selected', [
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

        $priorityInput = strtolower(trim((string) $request->request->get('message_priority', 'normal')));
        $selectedPriority = match ($priorityInput) {
            'urgent' => PrioriteMessage::URGENT,
            'faible', 'low' => PrioriteMessage::FAIBLE,
            default => PrioriteMessage::NORMAL,
        };
        $message->setPrioriteMessage($selectedPriority);

        $uploadedFiles = $request->files->all('mediaFiles');
        $textContent = $this->normalizeMirroredLatinText(trim($request->request->get('message', '')));
        $gifUrl = trim((string) $request->request->get('gif_url', ''));
        $vocalBlobBase64 = trim((string) $request->request->get('vocal_blob_base64', ''));
        $vocalBlobMime = trim((string) $request->request->get('vocal_blob_mime', 'audio/webm'));
        $vocalBlobName = trim((string) $request->request->get('vocal_blob_name', 'vocale-' . time() . '.webm'));
        $isRecordedVocale = $request->request->getBoolean('is_recorded_vocale', false);
        $uploadedFiles = array_values(array_filter(is_array($uploadedFiles) ? $uploadedFiles : ($uploadedFiles ? [$uploadedFiles] : [])));

        if ($textContent !== '' && $moderationService->containsProhibitedContent($textContent)) {
            $this->addFlash('error', 'Message bloque: contenu inapproprie detecte.');
            return $isAdminSender
                ? $this->redirectToRoute('admin_messagerie_view', ['id' => $id_conversation])
                : $this->redirectToRoute('app_messagerie_selected', [
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
                        TypeMessage::VIDEO => 'video',
                        TypeMessage::AUDIO => 'Audio',
                        TypeMessage::VOCALE => 'Vocale',
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
                // Prevent storing local machine file paths as chat content.
                if (preg_match('/^[A-Za-z]:[\\\\\/]/', $textContent) === 1) {
                    $this->addFlash('error', 'Chemin local detecte. Veuillez joindre le fichier via le bouton piece jointe.');
                    return $isAdminSender
                        ? $this->redirectToRoute('admin_messagerie_view', ['id' => $id_conversation])
                        : $this->redirectToRoute('app_messagerie_selected', [
                            'id_user' => $id_user,
                            'id_conversation' => $id_conversation
                        ]);
                }

                $message->setContenu($textContent);
                if ($this->isEmojiOnlyMessage($textContent)) {
                    $message->setType_message(TypeMessage::EMOJI);
                }
            }
        } elseif (empty($uploadedFiles) && $gifUrl === '' && $vocalBlobBase64 === '') {
            $this->addFlash('error', 'Vous ne pouvez pas envoyer un message vide.');
            return $isAdminSender
                ? $this->redirectToRoute('admin_messagerie_view', ['id' => $id_conversation])
                : $this->redirectToRoute('app_messagerie_selected', [
                    'id_user' => $id_user,
                    'id_conversation' => $id_conversation
                ]);
        }

        $errors = $validator->validate($message);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $isAdminSender
                ? $this->redirectToRoute('admin_messagerie_view', ['id' => $id_conversation])
                : $this->redirectToRoute('app_messagerie_selected', [
                    'id_user' => $id_user,
                    'id_conversation' => $id_conversation
                ]);
        }

        $em->persist($message);
        $em->flush();

        $this->sendAutomatedGeminiReplyIfNeeded(
            $textContent,
            $message->getAttachments(),
            $user,
            $conversation,
            $em,
            $userRepo,
            $messageRepo,
            $geminiGifChatService
        );

        return $isAdminSender
            ? $this->redirectToRoute('admin_messagerie_view', ['id' => $conversation->getId_conversation()])
            : $this->redirectToRoute('app_messagerie_selected', [
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
        $edited = $this->normalizeMirroredLatinText(trim($request->request->get('edited_message', '')));

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

        if ($userToAdd && $this->isGeminiAssistantUser($userToAdd)) {
            $this->addFlash('error', 'Le chatbot IA ne peut pas etre ajoute comme membre normal d un groupe.');
            return $this->redirectToRoute('app_messagerie', ['id_user' => $id_user]);
        }

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
    $authenticatedUser = $this->getUser();
    if ($authenticatedUser instanceof UserApp) {
        $id_user = $authenticatedUser->getId_user();
    }

    $user = $em->getRepository(UserApp::class)->find($id_user);
    $conversation = $em->getRepository(Conversation::class)->find($id_conversation);

    if (!$user || !$this->canAccessConversation($user, $conversation)) {
        throw $this->createNotFoundException();
    }

    if ($this->messagingAccessManager->isConversationCallBlocked($conversation)) {
        return $this->json(['error' => 'Les appels audio et video sont bloques pour cette conversation.'], 403);
    }

    // Get call details from the request (sent by front‑end)
    $type = $request->request->get('type');
    $duration = (int) $request->request->get('duration', 0);
    $roomName = trim((string) $request->request->get('room_name', ''));
    $meetingUrl = trim((string) $request->request->get('meeting_url', ''));
    $provider = trim((string) $request->request->get('provider', ''));
    $startedAt = $this->parseIsoDateTime($request->request->get('started_at'));
    $endedAt = $this->parseIsoDateTime($request->request->get('ended_at')) ?? new \DateTimeImmutable();

    if (!in_array($type, ['audio', 'video'], true)) {
        return $this->json(['error' => 'Invalid call type'], 400);
    }

    // Format duration
    $durationSeconds = max(1, $duration);
    $durationStr = $this->formatCallDurationLabel($durationSeconds);
    $startedAt ??= $endedAt->sub(new \DateInterval(sprintf('PT%dS', $durationSeconds)));
    $provider = in_array($provider, ['jitsi', 'webrtc'], true) ? $provider : 'webrtc';

    // Create the message
    $message = new Message();
    $message->setUserApp($user);
    $message->setConversation($conversation);
    $message->setDate_envoi(new \DateTime());
    $message->setStatut_message(StatutMessage::ENVOYE);
    $message->setType_message($type === 'audio' ? TypeMessage::APPEL_AUDIO : TypeMessage::APPEL_VIDEO);
    $message->setContenu(sprintf('Appel %s termine (%s)', $type, $durationStr));
    $message->setAttachments([
        [
            'type' => 'CALL_META',
            'call_type' => $type,
            'status' => 'completed',
            'duration_seconds' => $durationSeconds,
            'duration_label' => $durationStr,
            'provider' => $provider,
            'room_name' => $roomName,
            'meeting_url' => $meetingUrl,
            'started_at' => $startedAt->format(DATE_ATOM),
            'ended_at' => $endedAt->format(DATE_ATOM),
            'started_label' => $startedAt->format('H:i'),
            'ended_label' => $endedAt->format('H:i'),
        ],
    ]);

    $em->persist($message);
    $em->flush();

    return $this->json([
        'success' => true,
        'message_id' => $message->getId_message(),
        'duration_label' => $durationStr,
    ]);
}

    #[Route('/messagerie/call/signal/{id_user}/{id_conversation}', name: 'app_call_signal_send', methods: ['POST'])]
    public function sendCallSignal(
        int $id_user,
        int $id_conversation,
        Request $request,
        EntityManagerInterface $em,
        CacheItemPoolInterface $cache
    ): Response {
        try {
            $authenticatedUser = $this->getUser();
            if ($authenticatedUser instanceof UserApp) {
                $id_user = $authenticatedUser->getId_user();
            }

            $sender = $em->getRepository(UserApp::class)->find($id_user);
            $conversation = $em->getRepository(Conversation::class)->find($id_conversation);

            if (!$sender || !$conversation || !$this->canAccessConversation($sender, $conversation)) {
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            if ($this->messagingAccessManager->isConversationCallBlocked($conversation)) {
                return $this->json(['error' => 'Les appels audio et video sont bloques pour cette conversation.'], 403);
            }

            $payload = json_decode($request->getContent(), true);
            if (!is_array($payload)) {
                $payload = $request->request->all();
            }

            $targetUserId = (int) ($payload['target_user_id'] ?? 0);
            $signalType = trim((string) ($payload['signal_type'] ?? ''));
            $callType = trim((string) ($payload['call_type'] ?? 'audio'));
            $sessionId = trim((string) ($payload['session_id'] ?? ''));
            $signalPayload = $payload['payload'] ?? [];
            $isGroupCallSignal = is_array($signalPayload) && (bool) ($signalPayload['is_group_call'] ?? false);
            $closeGroupSession = $isGroupCallSignal && (bool) ($signalPayload['close_session'] ?? false);
            $conversationId = (int) $conversation->getId_conversation();

            if ($targetUserId <= 0 || $sessionId === '' || !in_array($signalType, ['invite', 'accept', 'offer', 'answer', 'candidate', 'end', 'reject'], true)) {
                return $this->json(['error' => 'Invalid signaling payload'], 422);
            }

            $targetUser = $em->getRepository(UserApp::class)->find($targetUserId);
            if (!$targetUser || !$conversation->getParticipants()->contains($targetUser)) {
                return $this->json(['error' => 'Target user is not in conversation'], 422);
            }

            if (
                $isGroupCallSignal
                && $this->countCallableConversationParticipants($conversation) > $this->getMaxWebRtcGroupParticipants()
            ) {
                return $this->json([
                    'success' => false,
                    'closed' => false,
                    'error' => sprintf(
                        'Les appels WebRTC de groupe sont limites a %d participants.',
                        $this->getMaxWebRtcGroupParticipants()
                    ),
                ], 422);
            }

            $sessionState = $this->getCallSessionState($cache, $conversationId, $sessionId);
            if (
                $signalType === 'accept'
                && (
                    (!$isGroupCallSignal && $sessionState !== 'invited')
                    || ($isGroupCallSignal && $sessionState === 'closed')
                )
            ) {
                return $this->json([
                    'success' => false,
                    'closed' => true,
                    'session_state' => $sessionState,
                    'error' => 'Call session is already closed',
                ]);
            }

            if (in_array($signalType, ['offer', 'answer', 'candidate'], true) && $sessionState === 'closed') {
                return $this->json([
                    'success' => false,
                    'closed' => true,
                    'session_state' => $sessionState,
                    'error' => 'Call session is already closed',
                ]);
            }

            if (
                in_array($signalType, ['reject', 'end'], true)
                && (
                    (!$isGroupCallSignal && $sessionState === 'closed')
                    || ($isGroupCallSignal && $signalType === 'end' && $closeGroupSession && $sessionState === 'closed')
                )
            ) {
                return $this->json([
                    'success' => true,
                    'closed' => true,
                    'session_state' => $sessionState,
                ]);
            }

            $event = [
                // Microsecond precision avoids collisions when multiple signaling events are sent quickly.
                'id' => (int) floor(microtime(true) * 1000000),
                'conversation_id' => $conversationId,
                'sender_user_id' => $sender->getId_user(),
                'target_user_id' => $targetUserId,
                'signal_type' => $signalType,
                'call_type' => in_array($callType, ['audio', 'video'], true) ? $callType : 'audio',
                'session_id' => $sessionId,
                'payload' => is_array($signalPayload) ? $signalPayload : [],
                'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ];

            $groupPeers = [];
            if ($signalType === 'invite') {
                $this->setCallSessionState($cache, $conversationId, $sessionId, 'invited');
                if ($isGroupCallSignal) {
                    $callerName = trim((string) ($signalPayload['caller_name'] ?? $this->buildUserDisplayName($sender)));
                    $this->upsertCallSessionParticipant($cache, $conversationId, $sessionId, $sender->getId_user(), $callerName);
                }
            } elseif ($signalType === 'accept') {
                $this->setCallSessionState($cache, $conversationId, $sessionId, 'accepted');
                if ($isGroupCallSignal) {
                    $participantName = trim((string) ($signalPayload['participant_name'] ?? $this->buildUserDisplayName($sender)));
                    $existingParticipants = $this->getCallSessionParticipants($cache, $conversationId, $sessionId);
                    $groupPeers = array_values(array_filter(
                        $existingParticipants,
                        static fn (array $participant): bool => (int) $participant['user_id'] !== (int) $sender->getId_user()
                    ));
                    $this->upsertCallSessionParticipant($cache, $conversationId, $sessionId, $sender->getId_user(), $participantName);
                }
            } elseif (
                in_array($signalType, ['reject', 'end'], true)
                && (
                    !$isGroupCallSignal
                    || ($signalType === 'end' && $closeGroupSession)
                )
            ) {
                $this->setCallSessionState($cache, $conversationId, $sessionId, 'closed');
                if ($isGroupCallSignal) {
                    $this->clearCallSessionParticipants($cache, $conversationId, $sessionId);
                }
            } elseif ($signalType === 'end' && $isGroupCallSignal) {
                $this->removeCallSessionParticipant($cache, $conversationId, $sessionId, $sender->getId_user());
            }

            $this->appendCallSignalEvent($cache, $conversationId, $targetUserId, $event);

            return $this->json([
                'success' => true,
                'event_id' => $event['id'],
                'group_peers' => $groupPeers,
            ]);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'closed' => false,
                'error' => 'Signalisation indisponible pour le moment.',
            ]);
        }
    }

    #[Route('/messagerie/call/signal/{id_user}/{id_conversation}', name: 'app_call_signal_poll', methods: ['GET'])]
    public function pollCallSignals(
        int $id_user,
        int $id_conversation,
        Request $request,
        EntityManagerInterface $em,
        CacheItemPoolInterface $cache
    ): Response {
        try {
            $authenticatedUser = $this->getUser();
            if ($authenticatedUser instanceof UserApp) {
                $id_user = $authenticatedUser->getId_user();
            }

            $user = $em->getRepository(UserApp::class)->find($id_user);
            $conversation = $em->getRepository(Conversation::class)->find($id_conversation);

            if (!$user || !$conversation || !$this->canAccessConversation($user, $conversation)) {
                return $this->json(['error' => 'Unauthorized'], 403);
            }

            if ($this->messagingAccessManager->isConversationCallBlocked($conversation)) {
                return $this->json(['success' => true, 'events' => []]);
            }

            $after = (int) $request->query->get('after', 0);
            $events = $this->readCallSignalEvents($cache, $conversation->getId_conversation(), $user->getId_user(), $after);
            $events = array_values(array_filter($events, function (array $event) use ($cache, $conversation): bool {
                $sessionId = (string) ($event['session_id'] ?? '');
                $signalType = (string) ($event['signal_type'] ?? '');
                $isGroupCallSignal = (bool) ($event['payload']['is_group_call'] ?? false);

                if ($sessionId === '') {
                    return false;
                }

                if ($signalType === 'invite') {
                    if ($isGroupCallSignal) {
                        return $this->getCallSessionState($cache, $conversation->getId_conversation(), $sessionId) !== 'closed';
                    }

                    return $this->getCallSessionState($cache, $conversation->getId_conversation(), $sessionId) === 'invited';
                }

                return $this->hasActiveCallSession($cache, $conversation->getId_conversation(), $sessionId);
            }));

            return $this->json([
                'success' => true,
                'events' => $events,
            ]);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'events' => [],
                'error' => 'Poll d appel indisponible pour le moment.',
            ]);
        }
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
        try {
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
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        } catch (NotFoundHttpException $exception) {
            return $this->json([
                'error' => 'Media not found',
                'message' => $exception->getMessage(),
            ], 404);
        } catch (\Throwable $exception) {
            return $this->json([
                'error' => 'Media unavailable',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    private function buildMediaResponse(string $file, Request $request): BinaryFileResponse
    {
        $baseDir = rtrim((string) $this->getParameter('messages_upload_directory'), '/\\');
        $normalizedInput = str_replace('\\', '/', $file);

        // Legacy support: absolute local paths (Windows/Linux) saved in old rows.
        if (preg_match('/^[A-Za-z]:\//', $normalizedInput) === 1 || str_starts_with($normalizedInput, '/')) {
            $uploadsPos = stripos($normalizedInput, '/uploads/');
            if ($uploadsPos !== false) {
                $relative = ltrim(substr($normalizedInput, $uploadsPos + 9), '/');
            } else {
                $relative = basename($normalizedInput);
            }
        } else {
            $relative = ltrim($normalizedInput, '/');
        }

        $candidate = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolvedPath = $candidate;

        // Backward compatibility for old DB paths like /uploads/messages/xxx.ext
        if (!is_file($resolvedPath) && str_starts_with($relative, 'messages/')) {
            $fallback = $baseDir . DIRECTORY_SEPARATOR . basename($relative);
            if (is_file($fallback)) {
                $resolvedPath = $fallback;
            }
        }

        // If only a filename is known, try common upload folders.
        if (!is_file($resolvedPath) && !str_contains($relative, '/')) {
            foreach (['images', 'Gifs', 'video', 'Audio', 'Vocale', 'files'] as $folder) {
                $fallback = $baseDir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $relative;
                if (is_file($fallback)) {
                    $resolvedPath = $fallback;
                    break;
                }
            }
        }

        clearstatcache(true, $resolvedPath);
        if (!is_file($resolvedPath) || !is_readable($resolvedPath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($resolvedPath);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function isEmojiOnlyMessage(string $text): bool
    {
        $normalized = preg_replace('/\s+/u', '', $text) ?? '';
        if ($normalized === '') {
            return false;
        }

        return (bool) preg_match('/^(?:[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]|\x{FE0F}|\x{200D})+$/u', $normalized);
    }

    /**
     * @return array<int, string>
     */
    private function getSupportedReactionEmojis(): array
    {
        return ['❤️', '👍', '😂', '😮', '😢', '🔥'];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function getEmojiAliasMap(): array
    {
        return [
            '😀' => ['happy', 'smile', 'joy'],
            '😁' => ['happy', 'smile', 'grin'],
            '😄' => ['happy', 'smile', 'laugh'],
            '😅' => ['nervous laugh', 'relief', 'laugh'],
            '😆' => ['haha', 'hahaha', 'lol', 'laugh', 'funny', 'grinning squinting'],
            '😂' => ['haha', 'hahaha', 'lol', 'lmao', 'mdr', 'laugh', 'funny', 'tears of joy', 'crying laughing'],
            '🤣' => ['haha', 'hahaha', 'lol', 'lmao', 'mdr', 'laugh', 'funny', 'rolling laughing', 'rolling on the floor laughing'],
            '🙂' => ['smile', 'calm', 'friendly'],
            '😊' => ['smile', 'blush', 'cute', 'happy'],
            '😍' => ['love', 'heart eyes', 'crush'],
            '😘' => ['love', 'kiss', 'heart'],
            '😎' => ['cool', 'sunglasses', 'swag'],
            '😮' => ['wow', 'surprise', 'shocked'],
            '😢' => ['sad', 'cry', 'tear'],
            '😭' => ['sad', 'cry', 'crying', 'tears'],
            '😡' => ['angry', 'mad', 'rage'],
            '🤔' => ['think', 'thinking', 'hmm'],
            '😹' => ['haha', 'hahaha', 'lol', 'funny', 'laughing cat'],
            '👍' => ['ok', 'yes', 'like', 'thumbs up'],
            '👏' => ['clap', 'bravo', 'applause'],
            '🔥' => ['fire', 'lit', 'hot'],
            '❤️' => ['love', 'heart'],
            '💔' => ['broken heart', 'sad love'],
            '🥳' => ['party', 'celebration', 'birthday'],
            '🤩' => ['wow', 'star eyes', 'excited'],
        ];
    }

    private function normalizeEmojiSearchText(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? $normalized;

        return $normalized;
    }

    /**
     * @return string[]
     */
    private function buildEmojiSearchNeedles(string $query): array
    {
        $needles = [];
        $normalized = $this->normalizeEmojiSearchText($query);
        if ($normalized !== '') {
            $needles[] = $normalized;
        }

        $compact = str_replace(' ', '', $normalized);
        if ($compact !== '') {
            $needles[] = $compact;
        }

        if (
            $compact !== ''
            && (
                str_contains($compact, 'haha')
                || str_contains($compact, 'hehe')
                || in_array($compact, ['lol', 'lmao', 'mdr', 'funny'], true)
                || preg_match('/^(ha){2,}$/u', $compact) === 1
            )
        ) {
            array_push($needles, 'haha', 'hahaha', 'lol', 'lmao', 'mdr', 'laugh', 'laughing', 'funny', 'tears of joy', 'rolling laughing');
        }

        if ($compact !== '' && (str_contains($compact, 'love') || str_contains($compact, 'heart'))) {
            array_push($needles, 'love', 'heart', 'heart eyes');
        }

        if ($compact !== '' && (str_contains($compact, 'sad') || str_contains($compact, 'cry'))) {
            array_push($needles, 'sad', 'cry', 'crying', 'tears');
        }

        return array_values(array_unique($needles));
    }

    /**
     * @param array{emoji?:string,name?:string,group?:string,sub_group?:string} $item
     * @param string[] $needles
     */
    private function emojiItemMatchesQuery(array $item, array $needles, string $rawQuery): bool
    {
        if ($needles === [] && trim($rawQuery) === '') {
            return true;
        }

        $emoji = (string) ($item['emoji'] ?? '');
        $aliases = $this->getEmojiAliasMap()[$emoji] ?? [];
        $haystack = $this->normalizeEmojiSearchText(implode(' ', array_filter([
            (string) ($item['name'] ?? ''),
            (string) ($item['group'] ?? ''),
            (string) ($item['sub_group'] ?? ''),
            implode(' ', $aliases),
        ])));

        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return $emoji !== '' && str_contains($emoji, $rawQuery);
    }

    /**
     * @return array<int, array{emoji:string,name:string,group:string,sub_group:string}>
     */
    private function getEmojiPickerItems(string $query = '', int $limit = 20000, ?HttpClientInterface $httpClient = null): array
    {
        $limit = max(1, min($limit, 20000));
        $needles = $this->buildEmojiSearchNeedles($query);
        $apiItems = [];

        if ($httpClient) {
            $apiItems = $this->fetchEmojiPickerItemsFromApi($httpClient, $query, $limit);
        }

        static $catalog = null;

        if (!is_array($catalog)) {
            $catalog = [];
            $ranges = [
                [0x1F300, 0x1FAFF],
                [0x2600, 0x27BF],
            ];

            foreach ($ranges as [$start, $end]) {
                for ($cp = $start; $cp <= $end; $cp++) {
                    $char = mb_chr($cp, 'UTF-8');
                    if (!is_string($char) || $char === '') {
                        continue;
                    }

                    $aliases = $this->getEmojiAliasMap()[$char] ?? [];
                    $catalog[] = [
                        'emoji' => $char,
                        'name' => $aliases !== [] ? implode(' ', $aliases) : sprintf('U+%04X', $cp),
                        'group' => $cp >= 0x1F600 && $cp <= 0x1F64F ? 'smileys & emotion' : 'symbols',
                        'sub_group' => '',
                    ];

                    if (count($catalog) >= 20000) {
                        break 2;
                    }
                }
            }
        }

        $items = $catalog;
        if ($query !== '') {
            $items = array_values(array_filter(
                $items,
                fn (array $item): bool => $this->emojiItemMatchesQuery($item, $needles, $query)
            ));
        }

        if ($apiItems === []) {
            return array_slice($items, 0, $limit);
        }

        $merged = [];
        $seen = [];
        foreach (array_merge($apiItems, $items) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $emoji = (string) ($item['emoji'] ?? '');
            if ($emoji === '' || isset($seen[$emoji])) {
                continue;
            }

            $seen[$emoji] = true;
            $merged[] = $item;

            if (count($merged) >= $limit) {
                break;
            }
        }

        return $merged;
    }

    /**
     * @return array<int, array{emoji:string,name:string,group:string,sub_group:string}>
     */
    private function fetchEmojiPickerItemsFromApi(HttpClientInterface $httpClient, string $query, int $limit): array
    {
        try {
            $response = $httpClient->request('GET', 'https://emojihub.yurace.pro/api/all', [
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $rows = $response->toArray(false);

            $needles = $this->buildEmojiSearchNeedles($query);
            $items = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $htmlCode = $row['htmlCode'] ?? [];
                $emojiHtml = is_array($htmlCode) ? (string) ($htmlCode[0] ?? '') : (string) $htmlCode;
                $emoji = $emojiHtml !== '' ? html_entity_decode($emojiHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';
                if ($emoji === '') {
                    continue;
                }

                $name = trim((string) ($row['name'] ?? ''));
                $category = trim((string) ($row['category'] ?? ''));
                $groupRaw = trim((string) ($row['group'] ?? ''));
                $group = $category !== '' ? $category : $groupRaw;
                $subGroup = trim((string) ($row['subGroup'] ?? $groupRaw));
                $label = trim($name . ' ' . $group . ' ' . $subGroup);

                if (!$this->emojiItemMatchesQuery([
                    'emoji' => $emoji,
                    'name' => $label !== '' ? $label : 'emoji',
                    'group' => $group,
                    'sub_group' => $subGroup,
                ], $needles, $query)) {
                    continue;
                }

                $items[] = [
                    'emoji' => $emoji,
                    'name' => $label !== '' ? $label : 'emoji',
                    'group' => $group,
                    'sub_group' => $subGroup,
                ];

                if (count($items) >= $limit) {
                    break;
                }
            }

            return $items;
        } catch (\Throwable) {
            return [];
        }
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
        $allowedEmojis = $this->getSupportedReactionEmojis();
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
        if (!$this->canAccessConversation($user, $conversation)) {
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
        try {
            $user = $userRepo->find($id_user);
            $conversation = $conversationRepo->find($id_conversation);
            if (!$user || !$this->canAccessConversation($user, $conversation)) {
                return $this->json(['error' => 'Not found'], 404);
            }

            $lastSeenId = (int) $request->query->get('last_seen_id', 0);
            $stats = $messageRepo->getLatestIdAndIncomingCount($conversation, $user, $lastSeenId);

            return $this->json([
                'latest_id' => $stats['latest_id'],
                'incoming_count' => $stats['incoming_count'],
                'attention_priority' => $stats['attention_priority'] ?? 'normal',
            ]);
        } catch (\Throwable $exception) {
            return $this->json([
                'success' => false,
                'latest_id' => (int) $request->query->get('last_seen_id', 0),
                'incoming_count' => 0,
                'attention_priority' => 'normal',
                'error' => 'Polling failed',
                'message' => $exception->getMessage(),
            ]);
        }
    }

    #[Route('/messagerie/read/{id_user}/{id_conversation}', name: 'app_messagerie_read', methods: ['GET', 'POST'])]
    public function markConversationRead(
        int $id_user,
        int $id_conversation,
        UserAppRepository $userRepo,
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo
    ): Response {
        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);

        if (!$user || !$this->canAccessConversation($user, $conversation)) {
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
        $authenticatedUser = $this->getUser();
        if ($authenticatedUser instanceof UserApp) {
            $id_user = $authenticatedUser->getId_user();
        }

        $user = $userRepo->find($id_user);
        $conversation = $conversationRepo->find($id_conversation);
        if (!$user || !$conversation || !$this->canAccessConversation($user, $conversation)) {
            return $this->json(['error' => 'Conversation introuvable'], 404);
        }

        $prompt = trim((string) $request->request->get('prompt', ''));
        if ($prompt === '') {
            return $this->json(['error' => 'Prompt vide'], 422);
        }

        $assistant = $this->getOrCreateGeminiAssistant($userRepo, $em);
        if (!$conversation->isEst_groupe() && !$conversation->getParticipants()->contains($assistant)) {
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
        if (!$user || !$conversation || !$this->canAccessConversation($user, $conversation)) {
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
        $conversation = $conversationRepo->findLatestNonBlockedPrivateByParticipants($user, $assistant);

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

    #[Route('/messagerie/emoji/list', name: 'app_messagerie_emoji_list', methods: ['GET'])]
    public function emojiList(
        Request $request,
        ContentModerationService $moderationService,
        HttpClientInterface $httpClient
    ): Response
    {
        $query = trim((string) $request->query->get('query', ''));
        $limit = max(1, min((int) $request->query->get('limit', 20000), 20000));

        if ($query !== '' && $moderationService->containsProhibitedContent($query)) {
            return $this->json([
                'success' => true,
                'blocked' => true,
                'message' => 'Recherche bloquee: terme inapproprie detecte.',
                'items' => [],
                'reaction_items' => $this->getSupportedReactionEmojis(),
            ]);
        }

        $blockedMeaningHints = [
            'middle finger',
            'obscene',
            'vulgar',
            'insult',
            'profan',
            'swear',
            'sexual',
        ];

        $items = $this->getEmojiPickerItems($query, $limit, $httpClient);
        $items = array_values(array_filter($items, function (array $item) use ($moderationService, $blockedMeaningHints): bool {
            $name = trim((string) $item['name']);
            $emoji = trim((string) $item['emoji']);
            if ($name === '') {
                return $emoji === '' || !$moderationService->containsManualProhibitedContent($emoji);
            }

            if (
                $moderationService->containsManualProhibitedContent($name)
                || ($emoji !== '' && $moderationService->containsManualProhibitedContent($emoji))
            ) {
                return false;
            }

            $lower = mb_strtolower($name);
            foreach ($blockedMeaningHints as $hint) {
                if (str_contains($lower, $hint)) {
                    return false;
                }
            }

            return true;
        }));

        $items = array_slice($items, 0, $limit);

        return $this->json([
            'success' => true,
            'blocked' => false,
            'items' => $items,
            'reaction_items' => $this->getSupportedReactionEmojis(),
        ]);
    }

    private function sendAutomatedGeminiReplyIfNeeded(
        string $textContent,
        array $attachments,
        UserApp $sender,
        Conversation $conversation,
        EntityManagerInterface $em,
        UserAppRepository $userRepo,
        MessageRepository $messageRepo,
        GeminiGifChatService $geminiGifChatService
    ): void {
        $trimmed = trim($textContent);
        $hasAttachments = $attachments !== [];

        if ($trimmed === '' && !$hasAttachments) {
            return;
        }

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
        $needsAttachmentDescription = $hasAttachments && ($isAssistantConversation || str_starts_with($trimmed, '/describe'));
        if (!$needsReply && !$needsGif && !$needsSummary && !$needsLongMessage && !$needsAttachmentDescription) {
            return;
        }

        $assistant = $this->getOrCreateGeminiAssistant($userRepo, $em);
        if ($assistant->getId_user() === $sender->getId_user()) {
            return;
        }
        if (!$conversation->isEst_groupe() && !$conversation->getParticipants()->contains($assistant)) {
            $conversation->addParticipant($assistant);
            $em->persist($conversation);
        }

        // In assistant conversations, mark incoming unread messages as read server-side
        // so users see read state without opening the chat manually.
        if ($isAssistantConversation) {
            $messageRepo->markConversationAsRead($conversation, $assistant);
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

        if (($needsReply || $needsAttachmentDescription) && !$needsGif && !$needsSummary && !$needsLongMessage) {
            $prompt = $trimmed;
            if (preg_match('/^(\/ai|@gemini)\s+/i', $trimmed)) {
                $prompt = trim(preg_replace('/^(\/ai|@gemini)\s+/i', '', $trimmed) ?? '');
            } elseif (preg_match('/^\/describe\s+/i', $trimmed)) {
                $prompt = trim(preg_replace('/^\/describe\s+/i', '', $trimmed) ?? '');
            }

            if ($needsAttachmentDescription && $hasAttachments) {
                $replyText = $geminiGifChatService->generateReplyForAttachments(
                    $prompt,
                    $attachments,
                    (string) $this->getParameter('messages_upload_directory')
                );
            } else {
                $replyText = $prompt !== ''
                    ? $geminiGifChatService->generateReply($prompt)
                    : 'Ecris votre question apres /ai, @gemini, ou envoie une piece jointe a decrire.';
            }

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
        $assistant->setLast_seen(new \DateTime());

        $em->persist($assistant);
        $em->flush();

        return $assistant;
    }

    private function canAccessConversation(?UserApp $user, ?Conversation $conversation): bool
    {
        if (!$user instanceof UserApp || !$conversation instanceof Conversation) {
            return false;
        }

        if ($conversation->getParticipants()->contains($user)) {
            return true;
        }

        return $user->getRole() === RoleUser::ADMIN;
    }

    private function buildUserDisplayName(?UserApp $user): string
    {
        if (!$user instanceof UserApp) {
            return 'Utilisateur';
        }

        $fullName = trim(sprintf('%s %s', (string) $user->getNom(), (string) $user->getPrenom()));

        return $fullName !== '' ? $fullName : ((string) $user->getEmail() ?: 'Utilisateur');
    }

    private function resolveConversationDisplayName(Conversation $conversation, UserApp $currentUser): string
    {
        if ($conversation->isEst_groupe()) {
            return trim((string) $conversation->getTitre()) ?: 'Conversation de groupe';
        }

        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getId_user() === $currentUser->getId_user()) {
                continue;
            }

            return $this->buildUserDisplayName($participant);
        }

        return trim((string) $conversation->getTitre()) ?: 'Contact';
    }

    /**
     * @return array{0: array<int>, 1: array<string>}
     */
    private function resolveCallableParticipants(Conversation $conversation, UserApp $currentUser): array
    {
        $ids = [];
        $names = [];

        foreach ($conversation->getParticipants() as $participant) {
            if (
                $participant->getId_user() === $currentUser->getId_user()
                || $this->isGeminiAssistantUser($participant)
            ) {
                continue;
            }

            $ids[] = (int) $participant->getId_user();
            $names[] = $this->buildUserDisplayName($participant);
        }

        return [$ids, $names];
    }

    private function isGeminiAssistantUser(?UserApp $user): bool
    {
        if (!$user instanceof UserApp) {
            return false;
        }

        $assistantEmail = trim((string) ($_ENV['GEMINI_ASSISTANT_EMAIL'] ?? 'gemini.bot@ecoadventure.local'));

        return $assistantEmail !== '' && strcasecmp((string) $user->getEmail(), $assistantEmail) === 0;
    }

    private function getMaxWebRtcGroupParticipants(): int
    {
        return 5;
    }

    private function countCallableConversationParticipants(Conversation $conversation): int
    {
        $count = 0;
        foreach ($conversation->getParticipants() as $participant) {
            if ($this->isGeminiAssistantUser($participant)) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function normalizeMirroredLatinText(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '' || preg_match('/\p{Arabic}/u', $trimmed)) {
            return $text;
        }

        $isSimpleLatinSentence = (bool) preg_match("/^[\\p{L}' -]+$/u", $trimmed);
        $hasSeveralWords = count(preg_split('/\s+/u', $trimmed) ?: []) >= 2;
        $looksMirroredByMobile = (bool) preg_match('/^\p{Ll}/u', $trimmed)
            && (bool) preg_match('/\p{Lu}$/u', $trimmed);

        if (!$isSimpleLatinSentence || !$hasSeveralWords || !$looksMirroredByMobile) {
            return $text;
        }

        $reversed = implode('', array_reverse(preg_split('//u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: []));

        return preg_replace('/' . preg_quote($trimmed, '/') . '/u', $reversed, $text, 1) ?? $text;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function appendCallSignalEvent(
        CacheItemPoolInterface $cache,
        int $conversationId,
        int $targetUserId,
        array $event
    ): void {
        $key = $this->buildCallSignalCacheKey($conversationId, $targetUserId);
        $item = $cache->getItem($key);
        $events = $item->isHit() ? $item->get() : [];
        if (!is_array($events)) {
            $events = [];
        }

        $events[] = $event;
        if (count($events) > 120) {
            $events = array_slice($events, -120);
        }

        $item->set($events);
        $item->expiresAfter(3600);
        $cache->save($item);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCallSignalEvents(
        CacheItemPoolInterface $cache,
        int $conversationId,
        int $userId,
        int $after
    ): array {
        $key = $this->buildCallSignalCacheKey($conversationId, $userId);
        $item = $cache->getItem($key);
        $events = $item->isHit() ? $item->get() : [];
        if (!is_array($events)) {
            return [];
        }

        return array_values(array_filter($events, static fn ($event): bool => is_array($event) && (int) ($event['id'] ?? 0) > $after));
    }

    private function hasActiveCallSession(
        CacheItemPoolInterface $cache,
        int $conversationId,
        string $sessionId
    ): bool {
        return in_array(
            $this->getCallSessionState($cache, $conversationId, $sessionId),
            ['invited', 'accepted'],
            true
        );
    }

    private function buildCallSignalCacheKey(int $conversationId, int $userId): string
    {
        return sprintf('call_signal_%d_%d', $conversationId, $userId);
    }

    private function getCallSessionState(
        CacheItemPoolInterface $cache,
        int $conversationId,
        string $sessionId
    ): string {
        $item = $cache->getItem($this->buildCallSessionStateCacheKey($conversationId, $sessionId));
        $value = $item->isHit() ? (string) $item->get() : '';

        return $value !== '' ? $value : 'new';
    }

    private function setCallSessionState(
        CacheItemPoolInterface $cache,
        int $conversationId,
        string $sessionId,
        string $state
    ): void {
        $item = $cache->getItem($this->buildCallSessionStateCacheKey($conversationId, $sessionId));
        $item->set($state);
        $item->expiresAfter(3600);
        $cache->save($item);
    }

    private function buildCallSessionStateCacheKey(int $conversationId, string $sessionId): string
    {
        return sprintf('call_session_%d_%s', $conversationId, sha1($sessionId));
    }

    /**
     * @return array<int, array{user_id: int, name: string}>
     */
    private function getCallSessionParticipants(
        CacheItemPoolInterface $cache,
        int $conversationId,
        string $sessionId
    ): array {
        $item = $cache->getItem($this->buildCallSessionParticipantsCacheKey($conversationId, $sessionId));
        $value = $item->isHit() ? $item->get() : [];
        if (!is_array($value)) {
            return [];
        }

        $participants = [];
        foreach ($value as $participant) {
            if (!is_array($participant)) {
                continue;
            }

            $userId = (int) ($participant['user_id'] ?? 0);
            $name = trim((string) ($participant['name'] ?? ''));
            if ($userId <= 0 || $name === '') {
                continue;
            }

            $participants[] = [
                'user_id' => $userId,
                'name' => $name,
            ];
        }

        return $participants;
    }

    /**
     * @param array<int, array{user_id: int, name: string}> $participants
     */
    private function saveCallSessionParticipants(
        CacheItemPoolInterface $cache,
        int $conversationId,
        string $sessionId,
        array $participants
    ): void {
        $item = $cache->getItem($this->buildCallSessionParticipantsCacheKey($conversationId, $sessionId));
        $item->set(array_values($participants));
        $item->expiresAfter(3600);
        $cache->save($item);
    }

    private function upsertCallSessionParticipant(
        CacheItemPoolInterface $cache,
        int $conversationId,
        string $sessionId,
        int $userId,
        string $name
    ): void {
        $participants = $this->getCallSessionParticipants($cache, $conversationId, $sessionId);
        $updated = false;

        foreach ($participants as &$participant) {
            if ((int) $participant['user_id'] !== $userId) {
                continue;
            }

            $participant['name'] = $name;
            $updated = true;
            break;
        }
        unset($participant);

        if (!$updated) {
            $participants[] = [
                'user_id' => $userId,
                'name' => $name,
            ];
        }

        $this->saveCallSessionParticipants($cache, $conversationId, $sessionId, $participants);
    }

    private function removeCallSessionParticipant(
        CacheItemPoolInterface $cache,
        int $conversationId,
        string $sessionId,
        int $userId
    ): void {
        $participants = array_values(array_filter(
            $this->getCallSessionParticipants($cache, $conversationId, $sessionId),
            static fn (array $participant): bool => (int) $participant['user_id'] !== $userId
        ));

        $this->saveCallSessionParticipants($cache, $conversationId, $sessionId, $participants);
    }

    private function clearCallSessionParticipants(
        CacheItemPoolInterface $cache,
        int $conversationId,
        string $sessionId
    ): void {
        $item = $cache->getItem($this->buildCallSessionParticipantsCacheKey($conversationId, $sessionId));
        $item->set([]);
        $item->expiresAfter(1);
        $cache->save($item);
    }

    private function buildCallSessionParticipantsCacheKey(int $conversationId, string $sessionId): string
    {
        return sprintf('call_session_participants_%d_%s', $conversationId, sha1($sessionId));
    }

    /**
     * @param array<int, mixed> $messages
     * @return array<int, mixed>
     */
    private function sanitizeConversationMessagesForDisplay(array $messages): array
    {
        $baseUploadDir = rtrim((string) $this->getParameter('messages_upload_directory'), '/\\');

        foreach ($messages as $message) {
            if (!$message instanceof Message) {
                continue;
            }

            $attachments = $message->getAttachments();
            if ($attachments !== []) {
                $validAttachments = [];
                foreach ($attachments as $attachment) {
                    if (!is_array($attachment)) {
                        continue;
                    }

                    $path = trim((string) ($attachment['path'] ?? ''));
                    if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                        $validAttachments[] = $attachment;
                        continue;
                    }

                    if ($this->uploadedMediaExists($baseUploadDir, $path)) {
                        $validAttachments[] = $attachment;
                    }
                }

                if (count($validAttachments) !== count($attachments)) {
                    $message->setAttachments($validAttachments);

                    if ($validAttachments === []) {
                        $currentContent = trim((string) ($message->getContenu() ?? ''));
                        if ($currentContent === '' || str_starts_with($currentContent, '/uploads/')) {
                            $message->setContenu('[Media introuvable]');
                        }
                    }
                }
            }

            $content = trim((string) ($message->getContenu() ?? ''));
            if ($content !== '' && str_starts_with($content, '/uploads/')) {
                $rawPath = trim(explode('|', $content, 2)[0] ?? '');
                if ($rawPath !== '' && !$this->uploadedMediaExists($baseUploadDir, $rawPath)) {
                    $message->setContenu('[Media introuvable]');
                }
            }
        }

        return $messages;
    }

    private function uploadedMediaExists(string $baseUploadDir, string $path): bool
    {
        $normalizedPath = str_replace('\\', '/', trim($path));
        if ($normalizedPath === '' || str_starts_with($normalizedPath, 'http://') || str_starts_with($normalizedPath, 'https://')) {
            return false;
        }

        $relative = str_starts_with($normalizedPath, '/uploads/')
            ? ltrim(substr($normalizedPath, 9), '/')
            : ltrim($normalizedPath, '/');

        if ($relative === '') {
            return false;
        }

        $absolutePath = $baseUploadDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        clearstatcache(true, $absolutePath);

        return is_file($absolutePath) && is_readable($absolutePath);
    }

    private function parseIsoDateTime(mixed $value): ?\DateTimeImmutable
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($text);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatCallDurationLabel(int $durationSeconds): string
    {
        $minutes = intdiv($durationSeconds, 60);
        $seconds = $durationSeconds % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
