<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FrontController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        $activities = [
            [
                'title' => 'Événements Sportifs',
                'description' => 'Découvrez nos événements sportifs en pleine nature.',
                'icon' => 'bi-trophy'
            ],
            [
                'title' => 'Activités de Plein Air',
                'description' => 'Randonnée, kayak, escalade et aventures guidées.',
                'icon' => 'bi-tree'
            ],
            [
                'title' => 'Coachs Expérimentés',
                'description' => 'Encadrement professionnel pour une expérience sécurisée.',
                'icon' => 'bi-person-badge'
            ]
        ];

        $events = [
            [
                'title' => 'Randonnée en Montagne',
                'location' => 'Ain Draham',
                'price' => '50 DT',
                'date' => '12 Avril 2026',
                'image' => 'assets/img/event1.png',
                'category' => 'Nature'
            ],
            [
                'title' => 'Kayak Adventure',
                'location' => 'Bizerte',
                'price' => '70 DT',
                'date' => '20 Avril 2026',
                'image' => 'assets/img/event2.png',
                'category' => 'Nautique'
            ],
            [
                'title' => 'Escalade en Pleine Nature',
                'location' => 'Zaghouan',
                'price' => '65 DT',
                'date' => '28 Avril 2026',
                'image' => 'assets/img/event3.png',
                'category' => 'Aventure'
            ],
        ];

        $packs = [
            [
                'name' => 'Pack Découverte',
                'price' => '99 DT',
                'features' => ['1 activité/mois', 'Support email', 'Réduction 5%']
            ],
            [
                'name' => 'Pack Explorer',
                'price' => '199 DT',
                'features' => ['3 activités/mois', 'Support prioritaire', 'Réduction 10%']
            ],
            [
                'name' => 'Pack Premium',
                'price' => '299 DT',
                'features' => ['Activités illimitées', 'Coach dédié', 'Réduction 15%']
            ]
        ];

        return $this->render('front/home.html.twig', [
            'activities' => $activities,
            'events' => $events,
            'packs' => $packs,
        ]);
    }

    #[Route('/events', name: 'app_events')]
    public function events(): Response
    {
        $events = [
            [
                'title' => 'Randonnée en Montagne',
                'location' => 'Ain Draham',
                'price' => '50 DT',
                'date' => '12 Avril 2026',
                'image' => 'assets/img/event1.png',
                'category' => 'Nature'
            ],
            [
                'title' => 'Kayak Adventure',
                'location' => 'Bizerte',
                'price' => '70 DT',
                'date' => '20 Avril 2026',
                'image' => 'assets/img/event2.png',
                'category' => 'Nautique'
            ],
            [
                'title' => 'Escalade en Pleine Nature',
                'location' => 'Zaghouan',
                'price' => '65 DT',
                'date' => '28 Avril 2026',
                'image' => 'assets/img/event3.png',
                'category' => 'Aventure'
            ],
        ];

        return $this->render('front/events.html.twig', [
            'events' => $events
        ]);
    }
    // The route /events is now handled by EventFrontController (app_event_front_index)

    #[Route('/packs', name: 'app_packs')]
    public function packs(): Response
    {
        $packs = [
            [
                'name' => 'Pack Découverte',
                'price' => '99 DT',
                'features' => ['1 activité/mois', 'Support email', 'Réduction 5%']
            ],
            [
                'name' => 'Pack Explorer',
                'price' => '199 DT',
                'features' => ['3 activités/mois', 'Support prioritaire', 'Réduction 10%']
            ],
            [
                'name' => 'Pack Premium',
                'price' => '299 DT',
                'features' => ['Activités illimitées', 'Coach dédié', 'Réduction 15%']
            ]
        ];

        return $this->render('front/packs.html.twig', [
            'packs' => $packs
        ]);
    }
}