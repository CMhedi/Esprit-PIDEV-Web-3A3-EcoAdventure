<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Entity\Pack;
use App\Enum\CategorieAct;
use App\Enum\NiveauAct;
use App\Enum\Statut;
use App\Enum\TypeActivite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ActiviteController extends AbstractController
{
    #[Route('/activitefront', name: 'app_activitefront')]
    public function activiteFront(): Response
    {
        return $this->renderCreateForm();
    }

    #[Route('/activite/create', name: 'app_activite_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        $formData = [
            'nom' => trim((string) $request->request->get('nom', '')),
            'type_activite' => trim((string) $request->request->get('type_activite', '')),
            'categorie_act' => trim((string) $request->request->get('categorie_act', '')),
            'niveau_act' => trim((string) $request->request->get('niveau_act', '')),
            'prix' => trim((string) $request->request->get('prix', '')),
            'statut' => trim((string) $request->request->get('statut', '')),
            'id_pack' => trim((string) $request->request->get('id_pack', '')),
        ];

        $fieldErrors = [];
        $activite = new Activite();
        $imageFile = $request->files->get('image_url');
        $normalizedPrix = str_replace(',', '.', $formData['prix']);

        $activite->setNom($formData['nom']);
        $activite->setTypeActivite(TypeActivite::tryFrom($formData['type_activite']));
        $activite->setCategorieAct(CategorieAct::tryFrom($formData['categorie_act']));
        $activite->setNiveauAct(NiveauAct::tryFrom($formData['niveau_act']));
        $activite->setStatut(Statut::tryFrom($formData['statut']));
        $activite->setImageUrl($imageFile?->getClientOriginalName());

        if ($formData['prix'] === '') {
            $activite->setPrix(null);
        } elseif (!is_numeric($normalizedPrix)) {
            $activite->setPrix(null);
            $fieldErrors['prix'][] = 'Le prix doit etre un nombre valide.';
        } else {
            $activite->setPrix((float) $normalizedPrix);
        }

        if ($formData['id_pack'] === '') {
            $activite->setPack(null);
        } elseif (!ctype_digit($formData['id_pack'])) {
            $activite->setPack(null);
            $fieldErrors['id_pack'][] = 'Le pack doit etre un identifiant numerique.';
        } else {
            $pack = $em->getRepository(Pack::class)->find((int) $formData['id_pack']);
            $activite->setPack($pack);

            if ($pack === null) {
                $fieldErrors['id_pack'][] = 'Le pack selectionne est introuvable.';
            }
        }

        $validationErrors = $this->mapViolations($validator->validate($activite));

        foreach (array_keys($fieldErrors) as $fieldName) {
            unset($validationErrors[$fieldName]);
        }

        $fieldErrors = array_merge_recursive($fieldErrors, $validationErrors);

        if ($fieldErrors !== []) {
            return $this->renderCreateForm($fieldErrors, $formData);
        }

        if ($imageFile !== null) {
            $uploadPath = 'C:/wamp64/www/uploads/';
            $extension = $imageFile->guessExtension() ?: $imageFile->getClientOriginalExtension() ?: 'bin';
            $newFilename = uniqid('activite_', true) . '.' . $extension;

            try {
                $imageFile->move($uploadPath, $newFilename);
            } catch (FileException) {
                return $this->renderCreateForm([
                    'image_url' => ["Erreur lors du telechargement de l'image."],
                ], $formData);
            }

            $activite->setImageUrl('uploads/' . $newFilename);
        }

        $em->persist($activite);
        $em->flush();

        return $this->redirectToRoute('app_activite_affichage', [
            'id' => $activite->getIdActivite(),
        ]);
    }

    #[Route('/activite/affichage/{id}', name: 'app_activite_affichage')]
    public function activiteAffichage(int $id, EntityManagerInterface $em): Response
    {
        $activite = $em->getRepository(Activite::class)->find($id);

        return $this->render('front/activiteaffichage.html.twig', [
            'activite' => $activite,
        ]);
    }

    private function renderCreateForm(array $fieldErrors = [], array $formData = []): Response
    {
        return $this->render('front/activitefront.html.twig', [
            'fieldErrors' => $fieldErrors,
            'formData' => $formData,
        ]);
    }

    private function mapViolations(ConstraintViolationListInterface $violations): array
    {
        $fieldErrors = [];

        foreach ($violations as $violation) {
            $fieldName = $violation->getPropertyPath();
            $fieldErrors[$fieldName][] = $violation->getMessage();
        }

        return $fieldErrors;
    }
}
