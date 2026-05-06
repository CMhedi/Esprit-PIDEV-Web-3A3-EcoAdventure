<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class SmsService
{
    private ?\Vonage\Client $client = null;
    private \Symfony\Component\HttpFoundation\RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
        
        $key = $_ENV['VONAGE_API_KEY'] ?? '85b174bc';
        $secret = $_ENV['VONAGE_API_SECRET'] ?? 'nk%XqZGpV5gEgs(';
        
        if ($key && $secret && class_exists('Vonage\\Client') && class_exists('Vonage\\Client\\Credentials\\Basic')) {
            $basicClass = 'Vonage\\Client\\Credentials\\Basic';
            $clientClass = 'Vonage\\Client';
            $basic = new $basicClass($key, $secret);
            $this->client = new $clientClass($basic);
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
                if (class_exists('Vonage\\SMS\\Message\\SMS')) {
                    $smsClass = 'Vonage\\SMS\\Message\\SMS';
                    $this->client->sms()->send(
                        new $smsClass($to, 'EcoAdven', "Votre code EcoAdventure est : $code")
                    );
                }
            } catch (\Exception $e) {
                error_log("Erreur Vonage: " . $e->getMessage());
                // Fallback simulation si erreur API
                $session = $this->requestStack->getSession();
                if ($session instanceof \Symfony\Component\HttpFoundation\Session\Session) {
                    $session->getFlashBag()->add('success', "MODE SIMULATION - Code SMS secret : $code");
                }
            }
        } else {
            // Mode simulation si pas de clés configurées
            $session = $this->requestStack->getSession();
            if ($session instanceof \Symfony\Component\HttpFoundation\Session\Session) {
                $session->getFlashBag()->add('success', "MODE SIMULATION - Code SMS secret : $code");
            }
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
