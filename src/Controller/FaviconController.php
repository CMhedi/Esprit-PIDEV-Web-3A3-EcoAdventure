<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

final class FaviconController
{
    #[Route('/favicon.ico', name: 'app_favicon', methods: ['GET'])]
    public function __invoke(): RedirectResponse
    {
        return new RedirectResponse('/img/Hero.png');
    }
}
