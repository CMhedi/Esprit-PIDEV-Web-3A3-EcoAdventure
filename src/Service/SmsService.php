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
        
        $key = $_ENV['VONAGE_API_KEY'] ?? '27b2c3b4';
        $secret = $_ENV['VONAGE_API_SECRET'] ?? '^0^tsGMbqXbgMpoEJ';
        
        if ($key && $secret && class_exists('Vonage\\Client') && class_exists('Vonage\\Client\\Credentials\\Basic')) {
            $basicClass = 'Vonage\\Client\\Credentials\\Basic';
            $clientClass = 'Vonage\\Client';
            $basic = new $basicClass($key, $secret);
            $this->client = new $clientClass($basic);
        }
    }

    public function sendVerificationCode(string $to): void
    {
        // Nettoyage et formatage du numéro
        $to = preg_replace('/[^0-9]/', '', $to);
        if (!str_starts_with($to, '216') && strlen($to) === 8) {
            $to = '216' . $to;
        } elseif (str_starts_with($to, '00216')) {
            $to = substr($to, 2);
        }
        
        $to = '+' . ltrim($to, '+');

        $code = (string)random_int(100000, 999999);
        $this->requestStack->getSession()->set('vonage_sms_code', $code);

        if ($this->client) {
            try {
                if (class_exists('Vonage\\SMS\\Message\\SMS')) {
                    $smsClass = 'Vonage\\SMS\\Message\\SMS';
                    // Utilisation d'un Sender ID standard
                    $from = 'Vonage'; 
                    
                    $response = $this->client->sms()->send(
                        new $smsClass($to, $from, "Votre code EcoAdventure est : $code")
                    );
                    
                    $message = $response->current();
                    
                    if ($message->getStatus() != 0) {
                        throw new \Exception("Erreur Vonage (Status " . $message->getStatus() . "): " . $message->getErrorText());
                    }
                    
                    error_log("SMS envoyé avec succès à $to. Message ID: " . $message->getMessageId());
                }
            } catch (\Exception $e) {
                error_log("Erreur d'envoi SMS: " . $e->getMessage());
                
                $session = $this->requestStack->getSession();
                if ($session instanceof \Symfony\Component\HttpFoundation\Session\Session) {
                    $msg = $e->getMessage();
                    // Message personnalisé si le numéro n'est pas vérifié (Erreur 29 ou contient 'verified')
                    if (str_contains(strtolower($msg), 'verified') || str_contains($msg, '29')) {
                        $session->getFlashBag()->add('reset_password_error', "Votre compte Vonage est en mode essai. Vous devez ajouter le numéro $to dans 'Test Numbers' sur le tableau de bord Vonage pour recevoir l'SMS.");
                    } else {
                        $session->getFlashBag()->add('reset_password_error', "Erreur SMS : " . $msg);
                    }
                    
                    // Code de secours visible pour le développement
                    $session->getFlashBag()->add('reset_password_error', "[DEV] Code de secours : $code");
                }
                
                throw $e;
            }
        } else {
            // Mode simulation si pas de clés configurées
            $session = $this->requestStack->getSession();
            if ($session instanceof \Symfony\Component\HttpFoundation\Session\Session) {
                $session->getFlashBag()->add('reset_password_error', "MODE SIMULATION - Code SMS secret : $code");
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
