<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HediPackInscriptionController extends AbstractController
{
    #[Route('/portfolio-packs', name: 'app_portfolio_packs')]
    public function portfolio(): Response
    {
        return $this->render('front/hedisPackInscription/portfolio.html.twig');
    }
}