<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function unavailable(): Response
    {
        $this->addFlash('warning', 'La connexion Google est desactivee sur cet environnement local.');

        return $this->redirectToRoute('app_login');
    }
}
