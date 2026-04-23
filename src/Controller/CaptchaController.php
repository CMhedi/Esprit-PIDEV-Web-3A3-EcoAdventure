<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CaptchaController extends AbstractController
{
    #[Route('/captcha', name: 'captcha')]
    public function index(SessionInterface $session): Response
    {
        // Générer captcha
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $captcha = '';

        for ($i = 0; $i < 5; $i++) {
            $captcha .= $letters[random_int(0, strlen($letters) - 1)];
        }

        // Stocker en session
        $session->set('captcha_code', $captcha);

        return $this->render('captcha/index.html.twig', [
            'captcha' => $captcha
        ]);
    }

    #[Route('/captcha/verify', name: 'captcha_verify', methods: ['POST'])]
    public function verify(Request $request, SessionInterface $session): Response
    {
        $userCaptcha = strtoupper($request->request->get('captcha'));
        $realCaptcha = $session->get('captcha_code');

        if ($userCaptcha !== $realCaptcha) {
            $this->addFlash('error', 'Captcha incorrect');
            return $this->redirectToRoute('captcha');
        }

        // Captcha correct → redirection vers ajout activité
        return $this->redirectToRoute('add_activite'); // ⚠️ adapte le nom de ta route
    }
}