<?php

namespace App\Service;

use Vonage\Client\Credentials\Basic;
use Vonage\Client;
use Vonage\SMS\Message\SMS;
use Symfony\Component\HttpFoundation\RequestStack;

class SmsService
{
    private $client;
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
        
        $key = $_ENV['VONAGE_API_KEY'] ?? '85b174bc';
        $secret = $_ENV['VONAGE_API_SECRET'] ?? 'nk%XqZGpV5gEgs(';
        
        if ($key && $secret) {
            $basic  = new Basic($key, $secret);
            $this->client = new Client($basic);
        }
    }

    public function sendVerificationCode(string $to): void
    {
        if (!str_starts_with($to, '+')) {
            $to = '+216' . ltrim($to, '0');
        }

        $code = (string)random_int(100000, 999999);
        $this->requestStack->getSession()->set('vonage_sms_code', $code);

        if ($this->client) {
            try {
                $this->client->sms()->send(
                    new SMS($to, 'EcoAdven', "Votre code EcoAdventure est : $code")
                );
            } catch (\Exception $e) {
                error_log("Erreur Vonage: " . $e->getMessage());
                // Fallback simulation si erreur API
                $this->requestStack->getSession()->getFlashBag()->add('success', "MODE SIMULATION - Code SMS secret : $code");
            }
        } else {
            // Mode simulation si pas de clés configurées
            $this->requestStack->getSession()->getFlashBag()->add('success', "MODE SIMULATION - Code SMS secret : $code");
            error_log("Code SMS simulé: $code");
        }
    }

    public function checkVerificationCode(string $to, string $code): bool
    {
        $sessionCode = $this->requestStack->getSession()->get('vonage_sms_code');
        if ($sessionCode && $sessionCode === $code) {
            $this->requestStack->getSession()->remove('vonage_sms_code');
            return true;
        }
        return false;
    }
}
