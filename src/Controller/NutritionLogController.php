<?php

namespace App\Controller;

use App\Entity\NutritionLog;
use App\Entity\UserApp;
use App\Repository\NutritionLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use App\Service\NutritionApiService;
#[Route('/nutrition', name: 'nutrition_')]
#[IsGranted('ROLE_USER')]
class NutritionLogController extends AbstractController
{
    private NutritionLogRepository $repository;
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;

    public function __construct(
        NutritionLogRepository $repository,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ) {
        $this->repository = $repository;
        $this->em = $em;
        $this->validator = $validator;
    }

    // ===== ADD LOG =====
    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // ===== VALIDATION =====
            if (!isset($data['food_name']) || empty($data['food_name'])) {
                return $this->json(['error' => 'food_name est requis'], Response::HTTP_BAD_REQUEST);
            }

            if (!isset($data['calories']) || $data['calories'] < 0) {
                return $this->json(['error' => 'calories doit être >= 0'], Response::HTTP_BAD_REQUEST);
            }

            // ===== CRÉATION =====
            $log = new NutritionLog();
            $log->setUser($this->getUser());
            $log->setFood_name($data['food_name']);
            $log->setCalories((float)$data['calories']);
            $log->setProtein((float)($data['protein'] ?? 0));
            $log->setFat((float)($data['fat'] ?? 0));
            $log->setCarbs((float)($data['carbs'] ?? 0));

            // ===== DATE =====
            $logDate = isset($data['log_date']) 
                ? new \DateTime($data['log_date']) 
                : new \DateTime();
            $log->setLog_date($logDate);

            // ===== VALIDATION ENTITY =====
            $errors = $this->validator->validate($log);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            // ===== SAUVEGARDE =====
            $this->repository->add($log, true);

            return $this->json([
                'success' => true,
                'message' => 'Log de nutrition ajouté avec succès',
                'log' => $this->formatLog($log)
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== GET USER LOGS =====
    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function getLogs(Request $request): Response
    {
        try {
            $page = (int)$request->query->get('page', 1);
            $limit = (int)$request->query->get('limit', 20);
            $offset = ($page - 1) * $limit;

            /** @var UserApp $user */
            $user = $this->getUser();

            $logs = $this->repository->getByUser($user->getId());
            $paginated = array_slice($logs, $offset, $limit);

            return $this->render('nutrition/logs.html.twig', [
                'logs' => $paginated,
                'total' => count($logs),
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil(count($logs) / $limit)
            ]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
            return $this->redirectToRoute('nutrition_logs');
        }
    }

    // ===== GET TODAY STATS =====
    #[Route('/today', name: 'today', methods: ['GET'])]
    public function getTodayStats(): JsonResponse
    {
        try {
            /** @var UserApp $user */
            $user = $this->getUser();

            $totalCalories = $this->repository->getTodayTotal($user->getId());
            $macros = $this->repository->getTotalMacros($user->getId(), new \DateTime());
            $logs = $this->repository->findByDateRange(
                $user->getId(),
                new \DateTime('today'),
                new \DateTime('tomorrow')
            );

            return $this->json([
                'date' => date('Y-m-d'),
                'total_calories' => $totalCalories,
                'macros' => $macros,
                'logs_count' => count($logs),
                'logs' => array_map(fn($log) => $this->formatLog($log), $logs)
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== GET WEEK STATS =====
    #[Route('/week', name: 'week', methods: ['GET'])]
    public function getWeekStats(): JsonResponse
    {
        try {
            /** @var UserApp $user */
            $user = $this->getUser();

            $logs = $this->repository->findThisWeek($user->getId());
            $average = $this->repository->getWeeklyAverage($user->getId());
            $macros = $this->repository->getTotalMacros($user->getId());

            // Grouper par jour
            $byDay = [];
            foreach ($logs as $log) {
                $day = $log->getLog_date()->format('Y-m-d');
                if (!isset($byDay[$day])) {
                    $byDay[$day] = [];
                }
                $byDay[$day][] = $log;
            }

            return $this->json([
                'week' => 'This Week',
                'total_logs' => count($logs),
                'daily_average' => $average,
                'total_macros' => $macros,
                'by_day' => array_map(function($dayLogs) {
                    return [
                        'count' => count($dayLogs),
                        'calories' => array_sum(array_map(fn($l) => $l->getCalories(), $dayLogs)),
                        'logs' => array_map(fn($log) => $this->formatLog($log), $dayLogs)
                    ];
                }, $byDay)
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== GET MONTH STATS =====
    #[Route('/month', name: 'month', methods: ['GET'])]
    public function getMonthStats(): JsonResponse
    {
        try {
            /** @var UserApp $user */
            $user = $this->getUser();

            $logs = $this->repository->findThisMonth($user->getId());
            $totalDays = count(array_unique(array_map(fn($log) => $log->getLog_date()->format('Y-m-d'), $logs)));
            $macros = $this->repository->getTotalMacros($user->getId());

            return $this->json([
                'month' => date('F Y'),
                'total_logs' => count($logs),
                'active_days' => $totalDays,
                'total_macros' => $macros,
                'logs_count' => count($logs)
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== SEARCH FOOD =====
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function searchFood(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('q');

            if (!$query || strlen($query) < 2) {
                return $this->json(['error' => 'Requête trop courte'], Response::HTTP_BAD_REQUEST);
            }

            $results = $this->repository->findByFoodName($query);

            return $this->json([
                'query' => $query,
                'count' => count($results),
                'results' => array_map(fn($log) => $this->formatLog($log), $results)
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== DELETE LOG =====
    #[Route('/{id}/delete', name: 'delete', methods: ['POST', 'DELETE'])]
    public function deleteLog(NutritionLog $log): JsonResponse
    {
        try {
            // ===== SÉCURITÉ - Vérifier l'ownership =====
            if ($log->getUser()->getId() !== $this->getUser()->getId()) {
                return $this->json(['error' => 'Non autorisé'], Response::HTTP_FORBIDDEN);
            }

            $this->repository->remove($log, true);

            return $this->json([
                'success' => true,
                'message' => 'Log supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== UPDATE LOG =====
    #[Route('/{id}/update', name: 'update', methods: ['PUT', 'PATCH'])]
    public function updateLog(Request $request, NutritionLog $log): JsonResponse
    {
        try {
            // ===== SÉCURITÉ =====
            if ($log->getUser()->getId() !== $this->getUser()->getId()) {
                return $this->json(['error' => 'Non autorisé'], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);

            // ===== MISE À JOUR =====
            if (isset($data['food_name'])) {
                $log->setFood_name($data['food_name']);
            }
            if (isset($data['calories'])) {
                $log->setCalories((float)$data['calories']);
            }
            if (isset($data['protein'])) {
                $log->setProtein((float)$data['protein']);
            }
            if (isset($data['fat'])) {
                $log->setFat((float)$data['fat']);
            }
            if (isset($data['carbs'])) {
                $log->setCarbs((float)$data['carbs']);
            }

            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Log mis à jour',
                'log' => $this->formatLog($log)
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== GET STATS DASHBOARD =====
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function getDashboard(): JsonResponse
    {
        try {
            /** @var UserApp $user */
            $user = $this->getUser();
            $userId = $user->getId();

            $today = $this->repository->getTotalMacros($userId, new \DateTime());
            $week = $this->repository->findThisWeek($userId);
            $month = $this->repository->findThisMonth($userId);

            return $this->json([
                'user' => [
                    'id' => $userId,
                    'name' => $user->getFullName() ?? 'User'
                ],
                'today' => $today,
                'week' => [
                    'logs_count' => count($week),
                    'average' => $this->repository->getWeeklyAverage($userId)
                ],
                'month' => [
                    'logs_count' => count($month),
                    'active_days' => count(array_unique(
                        array_map(fn($l) => $l->getLog_date()->format('Y-m-d'), $month)
                    ))
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== EXPORT CSV =====
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(): Response
    {
        try {
            /** @var UserApp $user */
            $user = $this->getUser();
            $logs = $this->repository->getByUser($user->getId());

            $csv = "Date,Aliment,Calories,Protéines,Graisses,Glucides\n";
            
            foreach ($logs as $log) {
                $csv .= sprintf(
                    '"%s","%s",%.2f,%.2f,%.2f,%.2f\n',
                    $log->getLog_date()->format('Y-m-d'),
                    $log->getFood_name(),
                    $log->getCalories(),
                    $log->getProtein(),
                    $log->getFat(),
                    $log->getCarbs()
                );
            }

            return new Response(
                $csv,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="nutrition-logs.csv"'
                ]
            );

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ================================
    // ===== IMC SECTION COMPLÈTE =====
    // ================================

    // ===== CALCULER IMC =====
    #[Route('/imc/calculate', name: 'imc_calculate', methods: ['POST'])]
    public function calculateIMC(Request $request, SessionInterface $session): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // ===== VALIDATION =====
            if (!isset($data['weight']) || $data['weight'] <= 0) {
                return $this->json(['error' => 'Poids invalide (kg)'], Response::HTTP_BAD_REQUEST);
            }
            if (!isset($data['height']) || $data['height'] <= 0) {
                return $this->json(['error' => 'Taille invalide (cm)'], Response::HTTP_BAD_REQUEST);
            }

            $weight = (float)$data['weight'];
            $height = (float)$data['height'];

            // ===== CONVERSION HAUTEUR EN MÈTRES =====
            $heightM = $height / 100;

            // ===== CALCUL IMC =====
            $imc = round($weight / ($heightM * $heightM), 2);

            // ===== ANALYSE IMC =====
            $analysis = $this->analyzeIMC($imc, $weight, $height);

            // ===== SAUVEGARDER EN SESSION POUR HISTORIQUE =====
            $imcHistory = $session->get('imc_history', []);
            $imcHistory[] = [
                'date' => date('Y-m-d H:i:s'),
                'imc' => $imc,
                'weight' => $weight,
                'height' => $height,
                'category' => $analysis['category']
            ];

            // Garder seulement les 30 dernières mesures
            if (count($imcHistory) > 30) {
                array_shift($imcHistory);
            }

            $session->set('imc_history', $imcHistory);

            // ===== DÉTECTION CHANGEMENT SIGNIFICATIF =====
            $alert = null;
            if (count($imcHistory) > 1) {
                $previousIMC = $imcHistory[count($imcHistory) - 2]['imc'];
                $diff = $imc - $previousIMC;

                if (abs($diff) > 1) {
                    $alert = $this->generateAlert($diff, $imc);
                }
            }

            return $this->json([
                'success' => true,
                'imc' => $imc,
                'weight' => $weight,
                'height' => $height,
                'analysis' => $analysis,
                'alert' => $alert
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== GET IMC ANALYSIS DÉTAILLÉE =====
    #[Route('/imc/analysis', name: 'imc_analysis', methods: ['POST'])]
    public function getIMCAnalysis(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['weight']) || !isset($data['height'])) {
                return $this->json(['error' => 'Données manquantes'], Response::HTTP_BAD_REQUEST);
            }

            $weight = (float)$data['weight'];
            $height = (float)$data['height'];
            $age = (int)($data['age'] ?? 30);
            $gender = $data['gender'] ?? 'M'; // M ou F

            $heightM = $height / 100;
            $imc = round($weight / ($heightM * $heightM), 2);

            $analysis = $this->analyzeIMC($imc, $weight, $height);
            $recommendations = $this->getRecommendations($imc, $weight, $height, $age, $gender);
            $ideal = $this->calculateIdealWeight($height, $gender);

            return $this->json([
                'success' => true,
                'imc' => $imc,
                'analysis' => $analysis,
                'recommendations' => $recommendations,
                'ideal_weight' => $ideal,
                'weight_to_lose' => max(0, $weight - $ideal['max']),
                'weight_to_gain' => max(0, $ideal['min'] - $weight)
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== GET IMC HISTORY =====
    #[Route('/imc/history', name: 'imc_history', methods: ['GET'])]
    public function getIMCHistory(SessionInterface $session): JsonResponse
    {
        try {
            $history = $session->get('imc_history', []);

            if (empty($history)) {
                return $this->json([
                    'success' => true,
                    'message' => 'Aucun historique IMC',
                    'history' => []
                ]);
            }

            // ===== CALCULS STATISTIQUES =====
            $imcValues = array_column($history, 'imc');
            $weights = array_column($history, 'weight');

            $stats = [
                'total_measurements' => count($history),
                'first_imc' => reset($imcValues),
                'current_imc' => end($imcValues),
                'imc_change' => end($imcValues) - reset($imcValues),
                'min_imc' => min($imcValues),
                'max_imc' => max($imcValues),
                'avg_imc' => round(array_sum($imcValues) / count($imcValues), 2),
                'min_weight' => min($weights),
                'max_weight' => max($weights),
                'weight_change' => end($weights) - reset($weights)
            ];

            return $this->json([
                'success' => true,
                'history' => $history,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== GET IMC CHART DATA =====
    #[Route('/imc/chart', name: 'imc_chart', methods: ['GET'])]
    public function getIMCChartData(SessionInterface $session): JsonResponse
    {
        try {
            $history = $session->get('imc_history', []);

            if (empty($history)) {
                return $this->json([
                    'success' => true,
                    'labels' => [],
                    'imc_data' => [],
                    'weight_data' => []
                ]);
            }

            $labels = array_column($history, 'date');
            $imcData = array_column($history, 'imc');
            $weightData = array_column($history, 'weight');

            return $this->json([
                'success' => true,
                'labels' => $labels,
                'imc_data' => $imcData,
                'weight_data' => $weightData
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== GET COMPLETE IMC DASHBOARD =====
    #[Route('/imc/dashboard', name: 'imc_dashboard', methods: ['POST'])]
    public function getIMCDashboard(Request $request, SessionInterface $session): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['weight']) || !isset($data['height'])) {
                return $this->json(['error' => 'Données manquantes'], Response::HTTP_BAD_REQUEST);
            }

            $weight = (float)$data['weight'];
            $height = (float)$data['height'];
            $age = (int)($data['age'] ?? 30);
            $gender = $data['gender'] ?? 'M';

            $heightM = $height / 100;
            $imc = round($weight / ($heightM * $heightM), 2);

            // ===== GET ALL DATA =====
            $analysis = $this->analyzeIMC($imc, $weight, $height);
            $recommendations = $this->getRecommendations($imc, $weight, $height, $age, $gender);
            $ideal = $this->calculateIdealWeight($height, $gender);
            $history = $session->get('imc_history', []);

            // ===== CALCULER MÉTABOLISME DE BASE =====
            $bmr = $this->calculateBMR($weight, $height, $age, $gender);

            // ===== CALORIES RECOMMANDÉES =====
            $activityLevel = $data['activity_level'] ?? 1.5; // 1.2 = sédentaire, 1.5 = modéré, 1.9 = actif
            $tdee = round($bmr * $activityLevel);

            // ===== STATISTIQUES HISTORIQUE =====
            $stats = [];
            if (!empty($history)) {
                $imcValues = array_column($history, 'imc');
                $stats = [
                    'total_measurements' => count($history),
                    'min_imc' => min($imcValues),
                    'max_imc' => max($imcValues),
                    'avg_imc' => round(array_sum($imcValues) / count($imcValues), 2),
                    'imc_trend' => end($imcValues) - reset($imcValues)
                ];
            }

            return $this->json([
                'success' => true,
                'measurements' => [
                    'weight' => $weight,
                    'height' => $height,
                    'age' => $age,
                    'gender' => $gender
                ],
                'imc' => $imc,
                'analysis' => $analysis,
                'ideal_weight' => $ideal,
                'bmr' => $bmr,
                'tdee' => $tdee,
                'recommendations' => $recommendations,
                'statistics' => $stats,
                'history_count' => count($history)
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // ===== HELPER: Analyser IMC =====
    private function analyzeIMC(float $imc, float $weight, float $height): array
    {
        $category = '';
        $color = '';
        $remark = '';
        $severity = '';

        if ($imc < 18.5) {
            $category = 'Sous-poids';
            $color = '#3498db'; // Bleu
            $severity = 'warning';
            $remark = 'Votre IMC indique un poids insuffisant. Cela peut entraîner des déficiences nutritionnelles.';
        } elseif ($imc < 25) {
            $category = 'Poids normal';
            $color = '#2ecc71'; // Vert
            $severity = 'success';
            $remark = 'Votre IMC est dans la plage de poids normal. Continuez ainsi !';
        } elseif ($imc < 30) {
            $category = 'Surpoids';
            $color = '#f39c12'; // Orange
            $severity = 'warning';
            $remark = 'Vous êtes en surpoids. Une amélioration des habitudes alimentaires et de l\'exercice est recommandée.';
        } elseif ($imc < 35) {
            $category = 'Obésité légère';
            $color = '#e74c3c'; // Rouge clair
            $severity = 'danger';
            $remark = 'Vous êtes en obésité légère. Une prise en charge médicale est conseillée.';
        } elseif ($imc < 40) {
            $category = 'Obésité modérée';
            $color = '#c0392b'; // Rouge
            $severity = 'danger';
            $remark = 'Vous êtes en obésité modérée. Une consultation médicale est fortement recommandée.';
        } else {
            $category = 'Obésité sévère';
            $color = '#8b0000'; // Rouge foncé
            $severity = 'critical';
            $remark = 'Vous êtes en obésité sévère. Une prise en charge médicale urgente est nécessaire.';
        }

        return [
            'imc' => $imc,
            'category' => $category,
            'color' => $color,
            'severity' => $severity,
            'remark' => $remark,
            'weight' => $weight,
            'height' => $height
        ];
    }

    // ===== HELPER: Recommandations personnalisées =====
    private function getRecommendations(float $imc, float $weight, float $height, int $age, string $gender): array
    {
        $recommendations = [
            'nutrition' => [],
            'exercise' => [],
            'lifestyle' => [],
            'medical' => []
        ];

        if ($imc < 18.5) {
            // Sous-poids
            $recommendations['nutrition'] = [
                '🥛 Augmentez votre apport calorique avec des aliments nutritifs',
                '🥜 Consommez des protéines suffisantes (1,6g par kg)',
                '🍌 Incluez des fruits secs et oléagineux',
                '🍗 Viandes, poissons et produits laitiers entiers',
                '⭐ Consultez un nutritionniste'
            ];
            $recommendations['exercise'] = [
                '💪 Musculation modérée (3x/semaine)',
                '❌ Évitez l\'exercice excessif',
                '🚶 Marche légère et yoga'
            ];
            $recommendations['lifestyle'] = [
                '😴 Sommeil suffisant (8 heures)',
                '📊 Pesez-vous régulièrement'
            ];
            $recommendations['medical'] = [
                '👨‍⚕️ Consultez un médecin pour vérifier des déficiences nutritionnelles',
                '🔬 Bilan sanguin recommandé'
            ];
        } elseif ($imc < 25) {
            // Poids normal
            $recommendations['nutrition'] = [
                '✅ Maintenez votre alimentation équilibrée',
                '🥗 5 fruits et légumes par jour',
                '🍎 Variez vos sources de protéines',
                '💧 Hydratez-vous régulièrement'
            ];
            $recommendations['exercise'] = [
                '🏃 150 minutes d\'exercice modéré par semaine',
                '💪 Renforcement musculaire 2x/semaine',
                '🧘 Flexibilité et équilibre'
            ];
            $recommendations['lifestyle'] = [
                '😴 Maintenez un bon sommeil',
                '📊 Suivi régulier de votre poids'
            ];
            $recommendations['medical'] = [
                '✅ Visite médicale annuelle'
            ];
        } elseif ($imc < 30) {
            // Surpoids
            $recommendations['nutrition'] = [
                '🥗 Augmentez les fruits et légumes',
                '⚖️ Réduisez progressivement les portions',
                '🚫 Limitez les aliments transformés',
                '💧 Remplacez les boissons sucrées par de l\'eau',
                '🍽️ Mangez lentement et consciemment',
                '📊 Suivi calorique recommandé'
            ];
            $recommendations['exercise'] = [
                '🚶 Marche : 30 minutes/jour',
                '🏊 Sports aquatiques (impact léger)',
                '💪 Musculation légère 2x/semaine',
                '🧗 Augmentez progressivement l\'intensité'
            ];
            $recommendations['lifestyle'] = [
                '😴 8 heures de sommeil minimum',
                '😌 Gestion du stress (yoga, méditation)',
                '📊 Pesez-vous hebdomadairement'
            ];
            $recommendations['medical'] = [
                '👨‍⚕️ Consultation médicale',
                '💊 Vérification de la tension artérielle',
                '🔬 Bilan lipidique'
            ];
        } else {
            // Obésité
            $recommendations['nutrition'] = [
                '👨‍⚕️ Régime sous supervision médicale',
                '🥗 Régime pauvre en calories (-500-750 kcal/jour)',
                '🥦 Aliments riches en fibres',
                '🍗 Protéines maigres',
                '🚫 Évitez les sucres et graisses saturées',
                '💧 Au moins 2 litres d\'eau/jour',
                '📊 Application de suivi alimentaire'
            ];
            $recommendations['exercise'] = [
                '🚶 Commencez doucement : 15-20 min/jour',
                '🏊 Natation ou vélo aquatique (impact léger)',
                '⬆️ Augmentez progressivement',
                '💪 Musculation légère sous supervision'
            ];
            $recommendations['lifestyle'] = [
                '😴 Sommeil de qualité (7-9 heures)',
                '😌 Gestion du stress profesionnelle',
                '📊 Suivi quotidien du poids',
                '👥 Soutien psychologique/groupe'
            ];
            $recommendations['medical'] = [
                '👨‍⚕️ Suivi médical régulier (mensuel)',
                '💊 Vérification tension et sucre',
                '🔬 Bilan complet',
                '🏥 Considérez un programme de perte de poids structuré'
            ];
        }

        return $recommendations;
    }

    // ===== HELPER: Poids idéal =====
    private function calculateIdealWeight(float $height, string $gender): array
    {
        // Formule de Devine
        $heightCm = $height;
        
        if ($gender === 'F') {
            $ideal_min = 45.5 + 2.3 * (($heightCm - 152.4) / 2.54);
            $ideal_max = 50 + 2.3 * (($heightCm - 152.4) / 2.54);
        } else {
            $ideal_min = 50 + 2.3 * (($heightCm - 152.4) / 2.54);
            $ideal_max = 54.5 + 2.3 * (($heightCm - 152.4) / 2.54);
        }

        return [
            'min' => round($ideal_min, 1),
            'max' => round($ideal_max, 1),
            'formula' => 'Devine'
        ];
    }

    // ===== HELPER: Métabolisme de base (BMR) =====
    private function calculateBMR(float $weight, float $height, int $age, string $gender): float
    {
        // Harris-Benedict Revised
        if ($gender === 'F') {
            $bmr = 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        } else {
            $bmr = 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        }

        return round($bmr);
    }

    // ===== HELPER: Générer alerte =====
    private function generateAlert(float $diff, float $imc): array
    {
        $alert = [
            'type' => $diff > 0 ? 'weight_gain' : 'weight_loss',
            'message' => '',
            'severity' => 'info'
        ];

        if ($diff > 0) {
            $alert['message'] = sprintf(
                '⚠️ Vous avez pris %.2f kg. IMC: %.2f',
                $diff,
                $imc
            );
            $alert['severity'] = 'warning';
        } else {
            $alert['message'] = sprintf(
                '✅ Vous avez perdu %.2f kg. Continuez !',
                abs($diff),
                $imc
            );
            $alert['severity'] = 'success';
        }

        return $alert;
    }

    // ===== HELPER: Format Log =====
    private function formatLog(NutritionLog $log): array
    {
        return [
            'id' => $log->getId(),
            'food_name' => $log->getFood_name(),
            'calories' => $log->getCalories(),
            'protein' => $log->getProtein(),
            'fat' => $log->getFat(),
            'carbs' => $log->getCarbs(),
            'log_date' => $log->getLog_date()?->format('Y-m-d H:i:s'),
            'macros_total' => $log->getProtein() + $log->getFat() + $log->getCarbs()
        ];
    }
    // Dans NutritionLogController

#[Route('/api/nutrition/search', name: 'nutrition_search', methods: ['GET'])]
public function searchIngredients(
    Request $request,
    NutritionApiService $apiService
): JsonResponse {
    try {
        $query = $request->query->get('q');

        if (!$query || strlen($query) < 2) {
            return $this->json(['error' => 'Requête trop courte'], Response::HTTP_BAD_REQUEST);
        }

        $results = $apiService->searchIngredients($query, 10);

        return $this->json([
            'success' => true,
            'query' => $query,
            'count' => count($results),
            'results' => $results
        ]);

    } catch (\Exception $e) {
        return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

#[Route('/api/get', name: 'get')]
public function getNutrition(
    Request $request,
    NutritionApiService $apiService
): JsonResponse {
    try {
        $ingredient = $request->query->get('ingredient');

        if (!$ingredient) {
            return $this->json(['error' => 'Ingrédient manquant'], Response::HTTP_BAD_REQUEST);
        }

        $nutrition = $apiService->getNutritionValues($ingredient);

        return $this->json([
            'success' => true,
            'ingredient' => $ingredient,
            'nutrition' => $nutrition
        ]);

    } catch (\Exception $e) {
        return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

#[Route('/api/compare', name: 'compare')]
public function compareIngredients(
    Request $request,
    NutritionApiService $apiService
): JsonResponse {
    try {
        $ingredient1 = $request->query->get('ingredient1');
        $ingredient2 = $request->query->get('ingredient2');

        if (!$ingredient1 || !$ingredient2) {
            return $this->json(['error' => 'Ingrédients manquants'], 400);
        }

        // 🔥 NORMALISATION (IMPORTANT)
        $ingredient1 = $this->normalize($ingredient1);
        $ingredient2 = $this->normalize($ingredient2);

        $comparison = $apiService->compareIngredients($ingredient1, $ingredient2);

        return $this->json([
            'success' => true,
            'comparison' => $comparison
        ]);

    } catch (\Exception $e) {
        return $this->json(['error' => $e->getMessage()], 500);
    }
}
private function normalize(string $ingredient): string
{
    $ingredient = strtolower(trim($ingredient));

    // Si déjà avec quantité → garder
    if (preg_match('/\d/', $ingredient)) {
        return $ingredient;
    }

    // Sinon ajouter 200g
    return '200g ' . $ingredient;
}

#[Route('/api/nutrition/daily-recommendations', name: 'daily_recommendations', methods: ['GET'])]
public function getDailyRecommendations(
    Request $request,
    NutritionApiService $apiService
): JsonResponse {
    try {
        $age = (int)$request->query->get('age', 30);
        $gender = $request->query->get('gender', 'M');
        $activity = (float)$request->query->get('activity', 1.5);

        $recommendations = $apiService->getDailyRecommendations($age, $gender, $activity);

        return $this->json([
            'success' => true,
            'recommendations' => $recommendations
        ]);

    } catch (\Exception $e) {
        return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
#[Route('/imc-api', name: 'imc_api')]
public function imcApi(): Response
{
    return $this->render('nutrition/imc-api.html.twig');
}

#[Route('/dashboard', name: 'dashboard')]
public function dashboard(): Response
{
    return $this->render('nutrition/dashboard.html.twig');
}

#[Route('/api/get-recipe', name: 'get_recipe', methods: ['GET'])]
public function getRecipeNutritionApi(
    Request $request,
    NutritionApiService $apiService
): JsonResponse {
    try {
        $id = (int)$request->query->get('id');

        if (!$id) {
            return $this->json([
                'error' => 'ID recette manquant'
            ], 400);
        }

        $nutrition = $apiService->getRecipeNutrition($id);

        return $this->json([
            'success' => true,
            'recipe_id' => $id,
            'nutrition' => $nutrition
        ]);

    } catch (\Exception $e) {
        return $this->json([
            'error' => $e->getMessage()
        ], 500);
    }
}


}