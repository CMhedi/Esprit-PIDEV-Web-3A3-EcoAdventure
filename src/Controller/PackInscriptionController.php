<?php

namespace App\Controller;

use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Form\InscriptionPackType;
use App\Enum\StatutInscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PackInscriptionController extends AbstractController
{
    #[Route('/packs/{id}/inscription', name: 'app_pack_inscription', methods: ['GET', 'POST'])]
    public function inscrire(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof UserApp) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $pack = $entityManager->getRepository(Pack::class)->find($id);

        if (!$pack) {
            throw $this->createNotFoundException('Pack introuvable.');
        }

        $displayName = trim(sprintf('%s %s', $user->getPrenom(), $user->getNom()));

        $inscription = new Inscription();
        $inscription->setPack($pack);
        $inscription->setUserApp($user);
        $inscription->setNomUser($displayName);
        $inscription->setNomPack($pack->getNom());
        $inscription->setMontantTotal((string) $pack->getPrixFinal());
        $inscription->setDateInscription(new \DateTime());
        $inscription->setStatutInscr(StatutInscription::EN_ATTENTE);

        $form = $this->createForm(InscriptionPackType::class, $inscription);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $inscription->setUserApp($user);
            $inscription->setNomUser($displayName);
            $inscription->setNomPack($pack->getNom());
            $inscription->setMontantTotal((string) $pack->getPrixFinal());
            $inscription->setDateInscription(new \DateTime());

            $entityManager->persist($inscription);
            $entityManager->flush();

            $this->addFlash('success', 'Votre inscription au pack a été enregistrée avec succès.');

            return $this->redirectToRoute('app_pack_inscription', ['id' => $pack->getIdPack()]);
        }

        return $this->render('front/hedisPackInscription/pack_inscription.html.twig', [
            'pack' => $pack,
            'form' => $form->createView(),
            'currentUser' => $user,
        ]);
    }
}
