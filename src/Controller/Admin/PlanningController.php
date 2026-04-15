<?php

namespace App\Controller\Admin;

use App\Repository\PlanningRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Planning;
use App\Form\PlanningType;
use Symfony\Component\HttpFoundation\Request;

class PlanningController extends AbstractController
{

#[Route('/admin/planning', name: 'app_admin_planning')]
public function index(
    Request $request,
    PlanningRepository $planningRepository
): Response
{
    // 🔐 sécurité admin
    $this->denyAccessUnlessGranted('ROLE_ADMIN');

    // 🔍 FILTRES
    $search = $request->query->get('search');
    $annee  = $request->query->get('annee');
    $statut = $request->query->get('statut');

    // 📋 DONNÉES FILTRÉES
    $plannings = $planningRepository->filter($search, $annee, $statut);

    // =========================
    // 📊 STATISTIQUES
    // =========================
    $total = count($plannings);

    $actifs = 0;
    $archives = 0;
    $brouillons = 0;

    foreach ($plannings as $planning) {
        switch ($planning->getStatut()->value) {
            case 'ACTIF':
                $actifs++;
                break;
            case 'ARCHIVE':
                $archives++;
                break;
            case 'BROUILLON':
                $brouillons++;
                break;
        }
    }

    // =========================
    // 🔥 DONNÉES DYNAMIQUES
    // =========================

    $coachStats = [];
    $days = [];
    $months = [];

    foreach ($plannings as $planning) {
        foreach ($planning->getSeances() as $seance) {

            // 🔹 COACH STATS
            $coach = $seance->getCoach();
            if ($coach) {
                $coachId = $coach->getId_user();

                if (!isset($coachStats[$coachId])) {
                    $coachStats[$coachId] = [
                        'coach' => $coach,
                        'count' => 0
                    ];
                }

                $coachStats[$coachId]['count'] += count($seance->getReservationSeances());
            }

            // 🔹 BEST DAY
            if ($seance->getDateSeance()) {
                $day = $seance->getDateSeance()->format('l');

                if (!isset($days[$day])) {
                    $days[$day] = 0;
                }
                $days[$day]++;
            }

            // 🔹 BEST MONTH
            if ($seance->getDateSeance()) {
                $month = $seance->getDateSeance()->format('F');

                if (!isset($months[$month])) {
                    $months[$month] = 0;
                }
                $months[$month]++;
            }
        }
    }

    // =========================
    // 🏆 BEST COACH + TOP COACHES
    // =========================

    usort($coachStats, fn($a, $b) => $b['count'] <=> $a['count']);

    $bestCoach = count($coachStats) > 0
        ? $coachStats[0]['coach']->getNom()
        : 'N/A';

    $topCoaches = array_map(function ($c) {
        return $c['coach']->getNom();
    }, array_slice($coachStats, 0, 3));

    // =========================
    // 📅 BEST DAY & MONTH
    // =========================

    arsort($days);
    $bestDay = array_key_first($days) ?? 'N/A';

    arsort($months);
    $bestMonth = array_key_first($months) ?? 'N/A';

    // =========================
    // 🖥️ RENDER
    // =========================
    return $this->render('admin/planning.html.twig', [
        'plannings' => $plannings,
        'total' => $total,
        'actifs' => $actifs,
        'archives' => $archives,
        'brouillons' => $brouillons,
        'bestDay' => $bestDay,
        'bestCoach' => $bestCoach,
        'bestMonth' => $bestMonth,
        'topCoaches' => $topCoaches,
    ]);
}
      // =========================
    // ➕ AJOUT
    // =========================
    #[Route('/admin/planning/new', name: 'app_admin_planning_new')]
    public function new(Request $request, PlanningRepository $repo): Response
    {
        $planning = new Planning();

        $form = $this->createForm(PlanningType::class, $planning);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $planning->setCreatedAt(new \DateTime());
            $planning->setUpdatedAt(new \DateTime());

            $repo->save($planning, true);

            return $this->redirectToRoute('app_admin_planning');
        }

        return $this->render('admin/planning_form.html.twig', [
            'form' => $form->createView(),
             'editMode' => false 
        ]);
    }

    #[Route('/admin/planning/{id}/edit', name: 'app_admin_planning_edit')]
public function edit(
    Planning $planning,
    Request $request,
    PlanningRepository $repo
): Response {

    $form = $this->createForm(PlanningType::class, $planning);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {

        $planning->setUpdatedAt(new \DateTime());

        $repo->save($planning, true);

        return $this->redirectToRoute('app_admin_planning');
    }

    return $this->render('admin/planning_form.html.twig', [
        'form' => $form->createView(),
        'editMode' => true
    ]);
}
#[Route('/admin/planning/{id}/delete', name: 'app_admin_planning_delete')]
public function delete(Planning $planning, PlanningRepository $repo): Response
{
    if (!$planning->getSeances()->isEmpty()) {
        $this->addFlash('error', '❌ Impossible de supprimer ce planning car il contient des séances.');

        return $this->redirectToRoute('app_admin_planning');
    }

    $repo->remove($planning, true);

    $this->addFlash('success', '✅ Planning supprimé avec succès.');

    return $this->redirectToRoute('app_admin_planning');
}
}