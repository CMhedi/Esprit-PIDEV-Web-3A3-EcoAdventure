<?php

namespace App\Controller;

use App\Repository\UserAppRepository;
use App\Repository\EvenementRepository;
use App\Repository\ActiviteRepository;
use App\Repository\PackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/search')]
class AdminSearchController extends AbstractController
{
    #[Route('', name: 'admin_global_search')]
    public function search(
        Request $request,
        UserAppRepository $userRepo,
        EvenementRepository $eventRepo,
        ActiviteRepository $activiteRepo,
        PackRepository $packRepo
    ): Response {
        $query = $request->query->get('q', '');
        
        $results = [
            'users' => [],
            'events' => [],
            'activities' => [],
            'packs' => []
        ];

        if (!empty($query)) {
            $results['users'] = $userRepo->createQueryBuilder('u')
                ->where('u.nom LIKE :q OR u.prenom LIKE :q OR u.email LIKE :q')
                ->setParameter('q', '%' . $query . '%')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            $results['events'] = $eventRepo->createQueryBuilder('e')
                ->where('e.titre LIKE :q OR e.description LIKE :q')
                ->setParameter('q', '%' . $query . '%')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            $results['activities'] = $activiteRepo->createQueryBuilder('a')
                ->where('a.nom LIKE :q OR a.description LIKE :q')
                ->setParameter('q', '%' . $query . '%')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            $results['packs'] = $packRepo->createQueryBuilder('p')
                ->where('p.nomPack LIKE :q OR p.descriptionPack LIKE :q')
                ->setParameter('q', '%' . $query . '%')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();
        }

        return $this->render('admin/search_results.html.twig', [
            'query' => $query,
            'results' => $results
        ]);
    }
}
