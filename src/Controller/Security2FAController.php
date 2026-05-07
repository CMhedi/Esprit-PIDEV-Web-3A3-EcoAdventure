<?php

namespace App\Controller;

use App\Entity\LoginRequest;
use App\Repository\LoginRequestRepository;
use App\Service\TelegramService;
use App\Entity\UserApp;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Security2FAController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private TelegramService $telegramService,
        private ManagerRegistry $doctrine
    ) {
    }

    #[Route('/profile/telegram/link', name: 'app_telegram_link')]
    public function linkTelegram(): Response
    {
        $user = $this->getUser();
        if (!$user || !($user instanceof UserApp)) {
            return $this->redirectToRoute('app_login');
        }

        $token = bin2hex(random_bytes(10));
        $user->setTelegramToken($token);
        
        $em = $this->doctrine->getManager();
        $em->flush();

        // Redirect to Bot with start parameter
        $botName = "eco_adventure_2fa_bot"; 
        return $this->redirect("https://t.me/$botName?start=$token");
    }

    #[Route('/login/2fa/waiting/{token}', name: 'app_2fa_waiting')]
    public function waiting(string $token, LoginRequestRepository $repository): Response
    {
        $loginRequest = $repository->findOneBy(['token' => $token]);

        if (!$loginRequest) {
            throw $this->createNotFoundException('Token invalide.');
        }

        return $this->render('security/2fa_waiting.html.twig', [
            'token' => $token
        ]);
    }

    #[Route('/login/2fa/status/{token}', name: 'app_2fa_status')]
    public function status(string $token, LoginRequestRepository $repository): JsonResponse
    {
        $loginRequest = $repository->findOneBy(['token' => $token]);

        if (!$loginRequest) {
            return new JsonResponse(['status' => 'not_found'], 404);
        }

        return new JsonResponse(['status' => $loginRequest->getStatus()]);
    }

    #[Route('/webhook/telegram', name: 'app_telegram_webhook', methods: ['POST'])]
    public function telegramWebhook(Request $request, LoginRequestRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (isset($data['message'])) {
            $message = $data['message'];
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';

            if (str_starts_with($text, '/start')) {
                $parts = explode(' ', $text);
                $token = $parts[1] ?? null;

                if ($token) {
                    $user = $this->doctrine->getRepository(UserApp::class)->findOneBy(['telegramToken' => $token]);
                    
                    if ($user) {
                        $user->setTelegramId((string)$chatId);
                        $user->setTelegramToken(null);
                        $em->flush();

                        $this->telegramService->sendMessage($chatId, "✅ Félicitations ! Votre compte EcoAdventure est désormais protégé par la 2FA Telegram.");
                        return new JsonResponse(['status' => 'linked']);
                    }
                }
            }
        }

        if (isset($data['callback_query'])) {
            $callbackQuery = $data['callback_query'];
            $callbackData = $callbackQuery['data'];
            $chatId = $callbackQuery['message']['chat']['id'];
            $messageId = $callbackQuery['message']['message_id'];

            if (str_starts_with($callbackData, 'login_accept_')) {
                $token = str_replace('login_accept_', '', $callbackData);
                $loginRequest = $repository->findOneBy(['token' => $token]);

                if ($loginRequest) {
                    $loginRequest->setStatus('approved');
                    $em->flush();

                    // Update Telegram message
                    $this->updateTelegramMessage($chatId, $messageId, "✅ Connexion approuvée !");
                }
            } elseif (str_starts_with($callbackData, 'login_refuse_')) {
                $token = str_replace('login_refuse_', '', $callbackData);
                $loginRequest = $repository->findOneBy(['token' => $token]);

                if ($loginRequest) {
                    $loginRequest->setStatus('rejected');
                    $em->flush();

                    // Update Telegram message
                    $this->updateTelegramMessage($chatId, $messageId, "❌ Connexion refusée.");
                }
            }
        }

        return new JsonResponse(['status' => 'ok']);
    }

    private function updateTelegramMessage(int $chatId, int $messageId, string $text): void
    {
        $botToken = $this->getParameter('telegram_bot_token');
        $url = sprintf('https://api.telegram.org/bot%s/editMessageText', $botToken);

        $this->httpClient->request('POST', $url, [
            'json' => [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text
            ]
        ]);
    }
}
