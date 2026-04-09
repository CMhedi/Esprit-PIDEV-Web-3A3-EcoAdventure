<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Enum\TypeActivite;
use App\Enum\CategorieAct;
use App\Enum\NiveauAct;
use App\Enum\Statut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/activites')]
class ActiviteAdminController extends AbstractController
{
    #[Route('/', name: 'app_admin_activites')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $nom = $request->query->get('nom', '');
        $tri = $request->query->get('tri', 'asc');

        $qb = $em->getRepository(Activite::class)->createQueryBuilder('a');

        if ($nom) {
            $qb->where('a.nom LIKE :nom')
               ->setParameter('nom', '%'.$nom.'%');
        }

        $qb->orderBy('a.prix', $tri === 'desc' ? 'DESC' : 'ASC');

        $activites = $qb->getQuery()->getResult();

        return $this->render('admin/activiteadmin.html.twig', [
            'activites' => $activites,
            'nom' => $nom,
            'tri' => $tri,
        ]);
    }

    #[Route('/edit/{id}', name: 'app_admin_activite_edit', methods: ['GET','POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $activite = $em->getRepository(Activite::class)->find($id);
        if (!$activite) {
            throw $this->createNotFoundException('Activité non trouvée');
        }

        if ($request->isMethod('POST')) {
            $activite->setNom($request->request->get('nom'));
            $activite->setTypeActivite(TypeActivite::from($request->request->get('typeActivite')));
            $activite->setCategorieAct(CategorieAct::from($request->request->get('categorieAct')));
            $activite->setNiveauAct(NiveauAct::from($request->request->get('niveauAct')));
            $activite->setPrix((float)$request->request->get('prix'));
            $activite->setStatut(Statut::from($request->request->get('statut')));

            $em->flush();
            $this->addFlash('success', 'Activité modifiée avec succès !');

            return $this->redirectToRoute('app_admin_activites');
        }

        return $this->render('admin/activite_edit_modal.html.twig', [
            'activite' => $activite,
        ]);
    }

    #[Route('/delete/{id}', name: 'app_admin_activite_delete', methods: ['POST'])]
    public function delete(int $id, EntityManagerInterface $em): Response
    {
        $activite = $em->getRepository(Activite::class)->find($id);
        if ($activite) {
            $em->remove($activite);
            $em->flush();
            $this->addFlash('success', 'Activité supprimée avec succès !');
        }

        return $this->redirectToRoute('app_admin_activites');
    }
}