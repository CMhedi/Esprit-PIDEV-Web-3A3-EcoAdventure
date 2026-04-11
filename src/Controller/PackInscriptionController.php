<?php

namespace App\Controller;

use App\Entity\Inscription;
use App\Entity\Pack;
use App\Entity\UserApp;
use App\Enum\StatutInscription;
use App\Form\InscriptionPackType;
use App\Repository\InscriptionRepository;
use App\Repository\PackRepository;
use App\Service\AI\AiPackExplainer;
use App\Service\Context\HolidayContextProvider;
use App\Service\Inscription\PackInscriptionReceiptBuilder;
use App\Service\Pack\PackInsightAssembler;
use App\Service\Pack\PackRecommendationEngine;
use App\Service\Tracking\PackFeedbackTracker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class PackInscriptionController extends AbstractController
{
    #[Route('/packs/{id}/inscription', name: 'app_pack_inscription', methods: ['GET', 'POST'])]
    public function inscrire(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        PackRepository $packRepository,
        InscriptionRepository $inscriptionRepository,
        PackInsightAssembler $packInsightAssembler,
        PackRecommendationEngine $packRecommendationEngine,
        PackFeedbackTracker $packFeedbackTracker,
        HolidayContextProvider $holidayContextProvider,
        AiPackExplainer $aiPackExplainer,
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

        $allPacks = $packRepository->findForFront();
        $packInsights = $packInsightAssembler->buildInsights($allPacks);
        $currentInsight = $packInsights[$pack->getIdPack()] ?? null;
        $alternativePack = $packRecommendationEngine->recommendAlternative($pack, $allPacks, $packInsights, $user);
        $holidayContext = $holidayContextProvider->getContext();
        $packExplanation = $currentInsight
            ? $aiPackExplainer->explainChoice($currentInsight, $user, $alternativePack, $holidayContext)
            : null;

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

        if ($request->isMethod('GET')) {
            $packFeedbackTracker->track($user, $pack, 'view_pack_detail', ['route' => 'app_pack_inscription']);
            $packFeedbackTracker->track($user, $pack, 'open_inscription', ['route' => 'app_pack_inscription']);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $inscription->setUserApp($user);
            $inscription->setNomUser($displayName);
            $inscription->setNomPack($pack->getNom());
            $inscription->setMontantTotal((string) $pack->getPrixFinal());
            $inscription->setDateInscription(new \DateTime());

            $entityManager->persist($inscription);
            $entityManager->flush();

            $packFeedbackTracker->track($user, $pack, 'inscription_confirmed', [
                'inscription_id' => $inscription->getIdInscription(),
                'statut' => $inscription->getStatutInscr()?->value,
            ]);

            $this->addFlash('success', 'Votre inscription au pack a ete enregistree avec succes.');

            return $this->redirectToRoute('app_pack_inscription', ['id' => $pack->getIdPack()]);
        }

        $latestInscription = $inscriptionRepository->findLatestForUserAndPack($user->getId_user(), $pack->getIdPack());

        return $this->render('front/hedisPackInscription/pack_inscription.html.twig', [
            'pack' => $pack,
            'form' => $form->createView(),
            'currentUser' => $user,
            'packInsight' => $currentInsight,
            'alternativePack' => $alternativePack,
            'packExplanation' => $packExplanation,
            'holidayContext' => $holidayContext,
            'latestInscription' => $latestInscription,
        ]);
    }

    #[Route('/inscriptions/{id}/receipt.pdf', name: 'app_pack_inscription_receipt', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function receipt(
        int $id,
        InscriptionRepository $inscriptionRepository,
        PackInscriptionReceiptBuilder $receiptBuilder,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof UserApp) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        $inscription = $inscriptionRepository->find($id);
        if (!$inscription) {
            throw $this->createNotFoundException('Inscription introuvable.');
        }

        $ownerId = $inscription->getUserApp()?->getId_user();
        if ($ownerId !== $user->getId_user() && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Acces refuse a ce recu.');
        }

        $response = new Response($receiptBuilder->buildPdf($inscription));
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('inscription-pack-%d.pdf', $inscription->getIdInscription())
        );

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
