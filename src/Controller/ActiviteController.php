<?php

namespace App\Controller;
use App\Entity\Activite;
use App\Entity\Pack;
use App\Enum\CategorieAct;
use App\Enum\NiveauAct;
use App\Enum\Statut;
use App\Enum\TypeActivite;
use App\Repository\PackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Repository\ActiviteRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ActiviteController extends AbstractController
{
    #[Route('/activitefront', name: 'app_activitefront')]
    public function activiteFront(PackRepository $packRepository, SessionInterface $session): Response
    {
        // Generate fresh CAPTCHA
        $captcha = $this->generateCaptcha();
        $session->set('captcha_code', $captcha);
        
        return $this->renderCreateForm($packRepository);
    }

    #[Route('/activite/create', name: 'app_activite_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        PackRepository $packRepository,
        SessionInterface $session
    ): Response {

        $formData = [
            'nom' => trim((string) $request->request->get('nom', '')),
            'type_activite' => trim((string) $request->request->get('type_activite', '')),
            'categorie_act' => trim((string) $request->request->get('categorie_act', '')),
            'niveau_act' => trim((string) $request->request->get('niveau_act', '')),
            'prix' => trim((string) $request->request->get('prix', '')),
            'statut' => trim((string) $request->request->get('statut', '')),
            'id_pack' => trim((string) $request->request->get('id_pack', '')),
            'latitude' => $request->request->get('latitude'),
            'longitude' => $request->request->get('longitude'),
        ];

        $fieldErrors = [];
        $activite = new Activite();
        $imageFile = $request->files->get('image_url');
        $normalizedPrix = trim(str_replace(',', '.', $formData['prix']));

        $activite->setNom($formData['nom']);
        $activite->setTypeActivite(TypeActivite::tryFrom($formData['type_activite']));
        $activite->setCategorieAct(CategorieAct::tryFrom($formData['categorie_act']));
        $activite->setNiveauAct(NiveauAct::tryFrom($formData['niveau_act']));
        $activite->setStatut(Statut::tryFrom($formData['statut']));
        $activite->setImageUrl($imageFile?->getClientOriginalName());
        $activite->setLatitude($formData['latitude'] !== null ? (float) $formData['latitude'] : null);
        $activite->setLongitude($formData['longitude'] !== null ? (float) $formData['longitude'] : null);

        if ($formData['prix'] === '') {
            $activite->setPrix(null);
        } elseif (!is_numeric($normalizedPrix) || (float)$normalizedPrix > 10000 || (float)$normalizedPrix < 0) {
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
            return $this->renderCreateForm($packRepository, $fieldErrors, $formData);
        }

        // ✅ STORE ACTIVITE DATA IN SESSION - DO NOT STORE FILE OBJECT
        $session->set('activite_temp_data', [
            'formData' => $formData,
            'imageFileName' => $imageFile?->getClientOriginalName(),
            'imageMimeType' => $imageFile?->getMimeType(),
            'normalizedPrix' => $normalizedPrix,
        ]);

        // ✅ ALSO STORE THE FILE IN TEMP LOCATION IF IT EXISTS
        if ($imageFile !== null) {
            $tempDir = sys_get_temp_dir();
            $tempFileName = uniqid('activite_temp_', true) . '.' . $imageFile->getClientOriginalExtension();
            $tempPath = $tempDir . DIRECTORY_SEPARATOR . $tempFileName;
            
            try {
                $imageFile->move($tempDir, $tempFileName);
                // Add temp file path to session
                $tempData = $session->get('activite_temp_data');
                $tempData['imageTempPath'] = $tempPath;
                $session->set('activite_temp_data', $tempData);
            } catch (FileException $e) {
                $fieldErrors['image_url'][] = 'Error uploading image: ' . $e->getMessage();
                return $this->renderCreateForm($packRepository, $fieldErrors, $formData);
            }
        }

        // ✅ REDIRECT TO CAPTCHA VERIFICATION PAGE
        $captcha = $this->generateCaptcha();
        $session->set('captcha_code', $captcha);
        
        return $this->redirectToRoute('app_captcha_verify_page');
    }

    #[Route('/activite/captcha', name: 'app_captcha_verify_page')]
    public function captchaVerifyPage(SessionInterface $session): Response
    {
        $captcha = $session->get('captcha_code');
        
        if (!$captcha) {
            $this->addFlash('error', 'Session expired. Please try again.');
            return $this->redirectToRoute('app_activitefront');
        }

        return $this->render('front/captcha.html.twig', [
            'captcha' => $captcha
        ]);
    }

    #[Route('/activite/captcha/submit', name: 'app_captcha_submit', methods: ['POST'])]
    public function submitCaptcha(
        Request $request,
        EntityManagerInterface $em,
        SessionInterface $session
    ): Response {
        $userCaptcha = strtoupper(trim((string) $request->request->get('captcha_user', '')));
        $realCaptcha = $session->get('captcha_code');

        // ✅ CAPTCHA VALIDATION
        if (!$realCaptcha || $userCaptcha !== $realCaptcha) {
            $this->addFlash('error', 'Captcha incorrect. Please try again.');
            $newCaptcha = $this->generateCaptcha();
            $session->set('captcha_code', $newCaptcha);
            return $this->redirectToRoute('app_captcha_verify_page');
        }

        // ✅ CAPTCHA CORRECT - SAVE ACTIVITY TO DATABASE
        $tempData = $session->get('activite_temp_data');

        if (!$tempData) {
            $this->addFlash('error', 'Session expired. Please start over.');
            return $this->redirectToRoute('app_activitefront');
        }

        $formData = $tempData['formData'];
        $normalizedPrix = $tempData['normalizedPrix'];
        $imageTempPath = $tempData['imageTempPath'] ?? null;

        $activite = new Activite();

        $activite->setNom($formData['nom']);
        $activite->setTypeActivite(TypeActivite::tryFrom($formData['type_activite']));
        $activite->setCategorieAct(CategorieAct::tryFrom($formData['categorie_act']));
        $activite->setNiveauAct(NiveauAct::tryFrom($formData['niveau_act']));
        $activite->setStatut(Statut::tryFrom($formData['statut']));
        $activite->setLatitude($formData['latitude'] !== null ? (float) $formData['latitude'] : null);
        $activite->setLongitude($formData['longitude'] !== null ? (float) $formData['longitude'] : null);

        if ($formData['prix'] === '') {
            $activite->setPrix(null);
        } else {
            $activite->setPrix((float) $normalizedPrix);
        }

        if ($formData['id_pack'] !== '') {
            $pack = $em->getRepository(Pack::class)->find((int) $formData['id_pack']);
            $activite->setPack($pack);
        } else {
            $activite->setPack(null);
        }

        // ✅ HANDLE IMAGE UPLOAD FROM TEMP LOCATION
        if ($imageTempPath && file_exists($imageTempPath)) {
            $uploadPath = rtrim((string) $this->getParameter('activite_resv_image_directory'), '/\\');
            $publicPath = trim((string) $this->getParameter('activite_resv_image_public_path'), '/\\');
            $extension = $tempData['imageFileName'] ? pathinfo($tempData['imageFileName'], PATHINFO_EXTENSION) : 'bin';
            $newFilename = uniqid('activite_', true) . '.' . $extension;

            try {
                if (!is_dir($uploadPath) && !mkdir($uploadPath, 0775, true) && !is_dir($uploadPath)) {
                    throw new FileException("Impossible de creer le dossier d'upload.");
                }

                copy($imageTempPath, $uploadPath . DIRECTORY_SEPARATOR . $newFilename);
                unlink($imageTempPath); // Delete temp file
                $activite->setImageUrl($publicPath . '/' . $newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Error uploading image: ' . $e->getMessage());
                $newCaptcha = $this->generateCaptcha();
                $session->set('captcha_code', $newCaptcha);
                return $this->redirectToRoute('app_captcha_verify_page');
            }
        }

        // ✅ SAVE TO DATABASE
        try {
            $em->persist($activite);
            $em->flush();
            
            // Clear session data
            $session->remove('activite_temp_data');
            $session->remove('captcha_code');
            
            $this->addFlash('success', 'Activity created successfully!');
            return $this->redirectToRoute('app_activite_affichage', [
                'id' => $activite->getIdActivite(),
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error saving activity: ' . $e->getMessage());
            $newCaptcha = $this->generateCaptcha();
            $session->set('captcha_code', $newCaptcha);
            return $this->redirectToRoute('app_captcha_verify_page');
        }
    }

    #[Route('/activite/affichage/{id}', name: 'app_activite_affichage')]
    public function activiteAffichage(int $id, EntityManagerInterface $em): Response
    {
        $activite = $em->getRepository(Activite::class)->find($id);

        return $this->render('front/activiteaffichage.html.twig', [
            'activite' => $activite,
        ]);
    }

    private function renderCreateForm(
        PackRepository $packRepository,
        array $fieldErrors = [],
        array $formData = []
    ): Response {
        $packs = $packRepository->findBy([], ['nom' => 'ASC']);
        $selectedPack = null;

        if (($formData['id_pack'] ?? '') !== '' && ctype_digit((string) $formData['id_pack'])) {
            $selectedPack = $packRepository->find((int) $formData['id_pack']);
        }

        return $this->render('front/activitefront.html.twig', [
            'fieldErrors' => $fieldErrors,
            'formData' => $formData,
            'packs' => $packs,
            'selectedPack' => $selectedPack,
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

    private function generateCaptcha(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $captcha = '';

        for ($i = 0; $i < 5; $i++) {
            $captcha .= $letters[random_int(0, strlen($letters) - 1)];
        }

        return $captcha;
    }

    #[Route('/activites/map', name: 'app_activite_map')]
    public function map(ActiviteRepository $repo): Response
    {
        $activites = $repo->findAllValid();

        return $this->render('front/map.html.twig', [
            'activites' => $activites
        ]);
    }
#[Route('/activities/discover', name: 'app_activities_discover')]
public function discover(ActiviteRepository $repo): Response
{
    $activities = $repo->findAllValid();

    return $this->render('front/discover.html.twig', [
        'activities' => $activities
    ]);
}
#[Route('/activities/trending', name: 'app_activities_trending')]
public function trending(ActiviteRepository $repo): Response
{
    $activities = $repo->findTrendingValid();

    return $this->render('front/trending.html.twig', [
        'activities' => $activities
    ]);
}
#[Route('/activities/insight', name: 'app_activities_insight')]
public function insight(ActiviteRepository $repo): Response
{
    $activities = $repo->findAllValid();

    $insight = "Analyse automatique : ";

    if (count($activities) > 5) {
        $insight .= "Bonne diversité d'activités. Augmenter la promotion des plus populaires.";
    } else {
        $insight .= "Ajouter plus d'activités pour améliorer l'offre.";
    }

    return $this->render('front/insight.html.twig', [
        'insight' => $insight
    ]);
}
}

