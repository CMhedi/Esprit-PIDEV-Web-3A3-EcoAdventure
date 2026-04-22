<?php

namespace App\Controller;

use App\Service\AiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class AiController extends AbstractController
{
    #[Route('/admin/ai', name: 'admin_ai_dashboard')]
    public function index(Request $request, AiService $ai): Response
    {
        $text = $request->query->get('text');

        $label = null;
        $score = null;
        $error = null;
        $raw = null;

        if ($text) {
            $result = $ai->sentiment($text);

            // ❌ erreur API ou Symfony
            if (!($result['success'] ?? false)) {
                $error = $result['error'] ?? 'API error';
                $raw = $result['raw'] ?? null;
            }

            // ✅ Hugging Face format
            if (isset($result['data'][0][0])) {
                $label = $result['data'][0][0]['label'] ?? null;
                $score = $result['data'][0][0]['score'] ?? null;
            }
        }

        return $this->render('admin/ai_dashboard.html.twig', [
            'text' => $text,
            'label' => $label,
            'score' => $score,
            'error' => $error,
            'raw' => $raw
        ]);
    }
}