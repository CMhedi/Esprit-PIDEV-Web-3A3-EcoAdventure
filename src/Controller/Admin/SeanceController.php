<?php
namespace App\Controller\Admin;

use App\Entity\Planning;
use App\Repository\SeanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Form\SeanceType;
use App\Entity\Seance;
use App\Repository\UserAppRepository;
use App\Service\GoogleCalendarService;
class SeanceController extends AbstractController
{

#[Route('/admin/planning/{id}/seances', name: 'app_admin_seances')]
public function index(
    Planning $planning,
    Request $request,
    SeanceRepository $repo,
    UserAppRepository $userRepo
): Response {

    // 🎯 FILTRES
    $search = $request->query->get('search');
    $date   = $request->query->get('date');
    $statut = $request->query->get('statut');
    $coach  = $request->query->get('coach');
    $sort   = $request->query->get('sort');

    // 🎯 SEANCES (avec filtre)
    $seances = $repo->filter(
          $planning->getIdPlanning(),
        $search,
        $date,
        $statut,
        $coach,
        $sort
    );

    // 🎯 COACHS (pour select)
    $coaches = $userRepo->findCoaches();

    return $this->render('admin/seance.html.twig', [
        'planning' => $planning,
        'seances' => $seances,
        'coaches' => $coaches
    ]);
}
    #[Route('/admin/planning/{id}/seances/new', name: 'app_admin_seance_new')]
public function new(
    Planning $planning,
    Request $request,
    SeanceRepository $repo
): Response {

    $seance = new Seance();
    $seance->setPlanning($planning);

    $form = $this->createForm(SeanceType::class, $seance);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        $repo->save($seance, true);

        return $this->redirectToRoute('app_admin_seances', [
            'id' => $planning->getIdPlanning()
        ]);
    }

    return $this->render('admin/seance_form.html.twig', [
        'form' => $form->createView(),
        'planning' => $planning,
          'editMode' => false
    ]);
}
#[Route('/admin/seance/{id}/edit', name: 'app_admin_seance_edit')]
public function edit(
    Seance $seance,
    Request $request,
    SeanceRepository $repo,
    GoogleCalendarService $googleService
): Response {

    $form = $this->createForm(SeanceType::class, $seance);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        $repo->save($seance, true);

        // 🔥 UPDATE GOOGLE EVENTS
        $timezone = new \DateTimeZone('Africa/Tunis');

        foreach ($seance->getReservationSeances() as $reservation) {

            if ($reservation->getGoogle_event_id()) {

                $user = $reservation->getUserApp();
                $token = json_decode($user->getGoogleToken(), true);

                if (!$token) continue;

                // 🕒 nouvelles dates
                $start = new \DateTime(
                    $seance->getDateSeance()->format('Y-m-d') . ' ' .
                    $seance->getHeureDebut()->format('H:i:s'),
                    $timezone
                );

                $end = new \DateTime(
                    $seance->getDateSeance()->format('Y-m-d') . ' ' .
                    $seance->getHeureFin()->format('H:i:s'),
                    $timezone
                );

                try {
                    $googleService->updateEvent(
                        $token,
                        $reservation->getGoogle_event_id(),
                        "Séance : " . $seance->getNom(),
                        "EcoAdventure",
                        $start,
                        $end
                    );
                } catch (\Exception $e) {
                    // 🔥 ne bloque pas toute la boucle
                }
            }
        }

        $this->addFlash('success', 'Séance mise à jour + Google Calendar synchronisé');

        return $this->redirectToRoute('app_admin_seances', [
            'id' => $seance->getPlanning()->getIdPlanning()
        ]);
    }

    return $this->render('admin/seance_form.html.twig', [
        'form' => $form->createView(),
        'planning' => $seance->getPlanning(),
        'editMode' => true,
    ]);
}
#[Route('/admin/seance/{id}/delete', name: 'app_admin_seance_delete', methods: ['POST'])]
public function delete(
    Request $request,
    Seance $seance,
    SeanceRepository $repo
): Response {

    if ($this->isCsrfTokenValid('delete'.$seance->getIdSeance(), $request->request->get('_token'))) {

        $planningId = $seance->getPlanning()->getIdPlanning();

        $repo->remove($seance, true);

        return $this->redirectToRoute('app_admin_seances', [
            'id' => $planningId
        ]);
    }

    return $this->redirectToRoute('app_admin_seances');
}
}