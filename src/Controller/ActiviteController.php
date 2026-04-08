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
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ActiviteController extends AbstractController
{
    #[Route('/activitefront', name: 'app_activitefront')]
    public function activiteFront(): Response
    {
        return $this->render('front/activitefront.html.twig');
    }

    #[Route('/activite/create', name: 'app_activite_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $activite = new Activite();

        // Conversion en enum
        $activite->setNom($request->request->get('nom'));
        $activite->setTypeActivite(TypeActivite::from($request->request->get('type_activite')));
        $activite->setCategorieAct(CategorieAct::from($request->request->get('categorie_act')));
        $activite->setNiveauAct(NiveauAct::from($request->request->get('niveau_act')));
        $activite->setStatut(Statut::from($request->request->get('statut')));
        $activite->setPrix((float)$request->request->get('prix'));

        // Pack associé (optionnel)
        $idPack = $request->request->get('id_pack');
        if ($idPack) {
            $activite->setPack($em->getReference('App\Entity\Pack', $idPack));
        }

        // Upload image
        $imageFile = $request->files->get('image_url');
        if ($imageFile) {
            $newFilename = uniqid() . '.' . $imageFile->guessExtension();
            try {
                $imageFile->move($this->getParameter('upload_dir'), $newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de l’upload de l’image');
            }
            // Sauvegarde chemin relatif dans la base
            $activite->setImageUrl('uploads/' . $newFilename);
        }

        $em->persist($activite);
        $em->flush();

        $this->addFlash('success', 'Activité ajoutée avec succès !');

        return $this->redirectToRoute('app_activite_affichage', [
            'id' => $activite->getIdActivite()
        ]);
    }

    #[Route('/activite/affichage/{id}', name: 'app_activite_affichage')]
    public function activiteAffichage(int $id, EntityManagerInterface $em): Response
    {
        $activite = $em->getRepository(Activite::class)->find($id);
        if (!$activite) {
            throw $this->createNotFoundException('Activité non trouvée');
        }

        return $this->render('front/activiteaffichage.html.twig', [
            'activite' => $activite
        ]);
    }
}