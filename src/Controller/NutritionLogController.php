<?php

namespace App\Controller;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\NutritionLog;
use App\Entity\UserApp;
use App\Repository\NutritionLogRepository;
use App\Service\NutritionApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\NotificationService;
#[Route('/nutrition', name: 'nutrition_')]
#[IsGranted('ROLE_USER')]
class NutritionLogController extends AbstractController
{
    private NutritionLogRepository $repository;
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;
    private NotificationService $notificationService;
    public function __construct(
        NutritionLogRepository $repository,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        NotificationService $notificationService
    ) {
        $this->repository = $repository;
        $this->em = $em;
        $this->validator = $validator;
        $this->notificationService = $notificationService;
    }

    // ================================
    // ===== DASHBOARD SECTION =====
    // ================================

    /**
     * Affiche la page du dashboard
     */
    #[Route('/nutrition-dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('nutrition/dashboard.html.twig');
    }

    /**
     * Récupère les stats du jour (Calories + Macros)
     */
  #[Route('/today', name: 'today', methods: ['GET'])]
public function getTodayStats(Request $request): JsonResponse
{
    try {
        /** @var UserApp $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $userId = $user->getId();
        $today = new \DateTime('today');

        // ===== 🧠 OBJECTIFS DYNAMIQUES (DB) =====
        $goals = [
            'calories' => 2000,
            'protein' => 150,
            'fat' => 70,
            'carbs' => 250
        ];

        if ($user->getWeight() && $user->getHeight()) {
            $calc = $this->calculateNutritionGoals(
                $user->getWeight(),
                $user->getHeight(),
                $user->getAge() ?? 30,
                $user->getGender() ?? 'M',
                $user->getActivityLevel() ?? 1.5
            );

            $goals = [
                'calories' => $calc['calories'],
                'protein' => $calc['protein'],
                'fat' => $calc['fat'],
                'carbs' => $calc['carbs'],
            ];
        }

        // ===== CALORIES =====
        try {
            $totalCalories = $this->repository->getTodayTotal($userId);
        } catch (\Exception $e) {
            error_log('getTodayTotal error: ' . $e->getMessage());
            $totalCalories = 0;
        }

        // ===== MACROS =====
        try {
            $macros = $this->repository->getTotalMacros($userId, $today);
        } catch (\Exception $e) {
            error_log('getTotalMacros error: ' . $e->getMessage());
            $macros = [
                'calories' => 0,
                'protein' => 0,
                'fat' => 0,
                'carbs' => 0
            ];
        }

        // ===== LOGS =====
        try {
            $logs = $this->repository->findByDateRange(
                $userId,
                new \DateTime('today'),
                new \DateTime('tomorrow')
            );
        } catch (\Exception $e) {
            error_log('findByDateRange error: ' . $e->getMessage());
            $logs = [];
        }

        return $this->json([
            'success' => true,
            'date' => $today->format('Y-m-d'),

            // ===== CONSOMMATION =====
            'total_calories' => (float)($totalCalories ?? 0),
            'macros' => [
                'calories' => (float)($macros['calories'] ?? 0),
                'protein' => (float)($macros['protein'] ?? 0),
                'fat' => (float)($macros['fat'] ?? 0),
                'carbs' => (float)($macros['carbs'] ?? 0),
            ],

            // ===== 🎯 OBJECTIFS =====
            'goals' => $goals,

            // ===== BONUS UTILE =====
            'progress' => [
                'calories' => $goals['calories'] > 0 ? round(($totalCalories / $goals['calories']) * 100) : 0,
                'protein' => $goals['protein'] > 0 ? round(($macros['protein'] / $goals['protein']) * 100) : 0,
                'fat' => $goals['fat'] > 0 ? round(($macros['fat'] / $goals['fat']) * 100) : 0,
                'carbs' => $goals['carbs'] > 0 ? round(($macros['carbs'] / $goals['carbs']) * 100) : 0,
            ],

            // ===== LOGS =====
            'logs_count' => is_array($logs) ? count($logs) : 0,
            'logs' => is_array($logs)
                ? array_map(fn($log) => $this->formatLog($log), $logs)
                : []
        ]);

    } catch (\Exception $e) {
        error_log('getTodayStats error: ' . $e->getMessage());

        return $this->json([
            'success' => false,
            'error' => 'Erreur: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Récupère les stats de la semaine groupées par jour
     */
    #[Route('/week', name: 'week', methods: ['GET'])]
    public function getWeekStats(): JsonResponse
    {
        try {
            /** @var UserApp $user */
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Utilisateur non authentifié'], 401);
            }

            $userId = $user->getId();

            try {
                $logs = $this->repository->findThisWeek($userId);
            } catch (\Exception $e) {
                error_log('findThisWeek error: ' . $e->getMessage());
                $logs = [];
            }

            try {
                $average = $this->repository->getWeeklyAverage($userId);
            } catch (\Exception $e) {
                error_log('getWeeklyAverage error: ' . $e->getMessage());
                $average = 0;
            }

            try {
                $totalMacros = $this->repository->getTotalMacros($userId);
            } catch (\Exception $e) {
                error_log('getTotalMacros error: ' . $e->getMessage());
                $totalMacros = ['calories' => 0, 'protein' => 0, 'fat' => 0, 'carbs' => 0];
            }

            // ===== GROUPER PAR JOUR =====
            $byDay = [];
            
            // Initialiser avec jours vides (7 jours)
            for ($i = 0; $i < 7; $i++) {
                $day = new \DateTime("-" . (6 - $i) . " days");
                $dayKey = $day->format('Y-m-d');
                $byDay[$dayKey] = [
                    'day' => $day->format('l'),
                    'date' => $dayKey,
                    'calories' => 0,
                    'count' => 0
                ];
            }

            // Remplir avec les logs
            if (is_array($logs)) {
                foreach ($logs as $log) {
                    try {
                        $logDate = $log->getLog_date();
                        if ($logDate) {
                            $dayKey = $logDate->format('Y-m-d');
                            if (isset($byDay[$dayKey])) {
                                $byDay[$dayKey]['calories'] += (float)($log->getCalories() ?? 0);
                                $byDay[$dayKey]['count']++;
                            }
                        }
                    } catch (\Exception $e) {
                        error_log('Error processing log: ' . $e->getMessage());
                        continue;
                    }
                }
            }

            return $this->json([
                'success' => true,
                'week' => 'This Week',
                'total_logs' => is_array($logs) ? count($logs) : 0,
                'daily_average' => round($average, 2),
                'total_macros' => $totalMacros,
                'by_day' => array_values($byDay)
            ]);

        } catch (\Exception $e) {
            error_log('getWeekStats error: ' . $e->getMessage());
            return $this->json(
                ['error' => 'Erreur: ' . $e->getMessage()],
                500
            );
        }
    }

    /**
     * Récupère tous les logs de l'utilisateur - FIX 🔧
     */
    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function getLogs(): JsonResponse
    {
        try {
            error_log('=== getLogs START ===');
            
            /** @var UserApp $user */
            $user = $this->getUser();

            if (!$user) {
                error_log('No user authenticated');
                return $this->json(['error' => 'Utilisateur non authentifié'], 401);
            }

            $userId = $user->getId();
            error_log('User ID: ' . $userId);

            // ===== VÉRIFIER QUE LA MÉTHODE EXISTE =====
            if (!method_exists($this->repository, 'getByUser')) {
                error_log('Method getByUser does not exist in repository');
                return $this->json(['error' => 'Repository method not found'], 500);
            }

            // ===== RÉCUPÉRER LES LOGS =====
            $logs = $this->repository->getByUser($userId);
            error_log('Logs count: ' . (is_array($logs) ? count($logs) : 'not array'));

            if (!is_array($logs)) {
                error_log('Result is not array, type: ' . gettype($logs));
                $logs = [];
            }

            // ===== FORMATER LES LOGS =====
            $formattedLogs = [];
            
            foreach ($logs as $log) {
                try {
                    if (!is_object($log)) {
                        error_log('Log is not object: ' . gettype($log));
                        continue;
                    }

                    $logDate = $log->getLog_date();
                    $dateString = 'N/A';
                    
                    if ($logDate instanceof \DateTimeInterface) {
                        $dateString = $logDate->format('Y-m-d');
                    } elseif (is_string($logDate)) {
                        $dateString = $logDate;
                    }

                    $formattedLogs[] = [
                        'id' => (int)$log->getId(),
                        'food_name' => (string)($log->getFood_name() ?? 'N/A'),
                        'calories' => (float)($log->getCalories() ?? 0),
                        'protein' => (float)($log->getProtein() ?? 0),
                        'fat' => (float)($log->getFat() ?? 0),
                        'carbs' => (float)($log->getCarbs() ?? 0),
                        'log_date' => $dateString
                    ];
                } catch (\Exception $logE) {
                    error_log('Error formatting log: ' . $logE->getMessage());
                    continue;
                }
            }

            error_log('Formatted logs count: ' . count($formattedLogs));
            error_log('=== getLogs SUCCESS ===');

            return $this->json([
                'success' => true,
                'count' => count($formattedLogs),
                'logs' => $formattedLogs
            ]);

        } catch (\Exception $e) {
            error_log('=== getLogs ERROR ===');
            error_log('Message: ' . $e->getMessage());
            error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            error_log('Stack: ' . $e->getTraceAsString());
            error_log('=== END ERROR ===');
            
            return $this->json([
                'success' => false,
                'error' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajouter un log de nutrition
     */
#[Route('/add', name: 'add', methods: ['POST'])]
public function add(Request $request): JsonResponse
{
    try {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['food_name']) || empty(trim($data['food_name']))) {
            return $this->json(
                ['error' => 'Le nom de l\'aliment est requis'],
                400
            );
        }

        if (!isset($data['calories']) || (float)$data['calories'] < 0) {
            return $this->json(
                ['error' => 'Les calories doivent être >= 0'],
                400
            );
        }

        /** @var UserApp $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        // ===== CREATE LOG =====
        $log = new NutritionLog();
        $log->setUser($user);
        $log->setFood_name(trim($data['food_name']));
        $log->setCalories((float)$data['calories']);
        $log->setProtein((float)($data['protein'] ?? 0));
        $log->setFat((float)($data['fat'] ?? 0));
        $log->setCarbs((float)($data['carbs'] ?? 0));

        if (isset($data['log_date']) && !empty($data['log_date'])) {
            $log->setLog_date(new \DateTime($data['log_date']));
        } else {
            $log->setLog_date(new \DateTime('today'));
        }

        // ===== VALIDATION =====
        $errors = $this->validator->validate($log);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], 400);
        }

        // ===== SAVE =====
        $this->repository->add($log, true);

        // ===== 🧠 CALCUL TOTAL JOUR =====
        $stats = $this->repository->getTotalMacros($user->getId(), new \DateTime());

        // ===== 🧠 CALCUL OBJECTIFS =====
        $goals = $this->calculateNutritionGoals(
            $user->getWeight(),
            $user->getHeight(),
            $user->getAge() ?? 30,
            $user->getGender() ?? 'M',
            $user->getActivityLevel() ?? 1.5
        );

        // ===== 🎉 EMAIL SI OBJECTIF ATTEINT =====
        if (
            $stats['calories'] >= $goals['calories'] &&
            !$user->isGoalNotified()
        ) {
            $this->notificationService->sendGoalAchieved($user->getEmail());

            $user->setGoalNotified(true);
            $this->em->flush();
        }

        return $this->json([
            'success' => true,
            'message' => 'Log de nutrition ajouté avec succès',
            'log' => $this->formatLog($log)
        ], 201);

    } catch (\Exception $e) {
        error_log('add error: ' . $e->getMessage());

        return $this->json(
            ['error' => 'Erreur: ' . $e->getMessage()],
            500
        );
    }
}

    /**
     * Supprimer un log
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST', 'DELETE'])]
    public function deleteLog(NutritionLog $log): JsonResponse
    {
        try {
            /** @var UserApp $user */
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Utilisateur non authentifié'], 401);
            }

            if ($log->getUser()?->getId() !== $user->getId()) {
                return $this->json(
                    ['error' => 'Non autorisé à supprimer ce log'],
                    403
                );
            }

            $this->repository->remove($log, true);

            return $this->json([
                'success' => true,
                'message' => 'Log supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            error_log('deleteLog error: ' . $e->getMessage());
            return $this->json(
                ['error' => 'Erreur: ' . $e->getMessage()],
                500
            );
        }
    }

    /**
     * Mettre à jour un log
     */
    #[Route('/{id}/update', name: 'update', methods: ['PUT', 'PATCH'])]
    public function updateLog(Request $request, NutritionLog $log): JsonResponse
    {
        try {
            /** @var UserApp $user */
            $user = $this->getUser();

            if (!$user) {
                return $this->json(['error' => 'Utilisateur non authentifié'], 401);
            }

            if ($log->getUser()?->getId() !== $user->getId()) {
                return $this->json(['error' => 'Non autorisé'], 403);
            }

            $data = json_decode($request->getContent(), true);

            if (isset($data['food_name'])) {
                $log->setFood_name(trim($data['food_name']));
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
            if (isset($data['log_date'])) {
                $log->setLog_date(new \DateTime($data['log_date']));
            }

            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Log mis à jour',
                'log' => $this->formatLog($log)
            ]);

        } catch (\Exception $e) {
            error_log('updateLog error: ' . $e->getMessage());
            return $this->json(['error' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Exporter les logs en CSV
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(): Response
    {
        try {
            /** @var UserApp $user */
            $user = $this->getUser();

            if (!$user) {
                throw new \Exception('Utilisateur non authentifié');
            }

            $logs = $this->repository->getByUser($user->getId());
            if (!is_array($logs)) {
                $logs = [];
            }

            $csv = "Date,Aliment,Calories,Protéines,Graisses,Glucides\n";
            
            foreach ($logs as $log) {
                $csv .= sprintf(
                    '"%s","%s",%.2f,%.2f,%.2f,%.2f' . "\n",
                    $log->getLog_date()->format('Y-m-d'),
                    str_replace('"', '""', $log->getFood_name()),
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
                    'Content-Type' => 'text/csv; charset=utf-8',
                    'Content-Disposition' => 'attachment; filename="nutrition-logs-' . date('Y-m-d') . '.csv"'
                ]
            );

        } catch (\Exception $e) {
            error_log('export error: ' . $e->getMessage());
            return $this->json(['error' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }

    // ================================
    // ===== IMC SECTION =====
    // ================================

    #[Route('/imc-api', name: 'imc_api')]
    public function imcApi(): Response
    {
        return $this->render('nutrition/imc-api.html.twig');
    }

    #[Route('/imc/calculate', name: 'imc_calculate', methods: ['POST'])]
    public function calculateIMC(Request $request, SessionInterface $session): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['weight']) || $data['weight'] <= 0) {
                return $this->json(['error' => 'Poids invalide (kg)'], 400);
            }
            if (!isset($data['height']) || $data['height'] <= 0) {
                return $this->json(['error' => 'Taille invalide (cm)'], 400);
            }

            $weight = (float)$data['weight'];
            $height = (float)$data['height'];
            $heightM = $height / 100;
            $imc = round($weight / ($heightM * $heightM), 2);

            $analysis = $this->analyzeIMC($imc, $weight, $height);

            $imcHistory = $session->get('imc_history', []);
            $imcHistory[] = [
                'date' => date('Y-m-d H:i:s'),
                'imc' => $imc,
                'weight' => $weight,
                'height' => $height,
                'category' => $analysis['category']
            ];

            if (count($imcHistory) > 30) {
                array_shift($imcHistory);
            }

            $session->set('imc_history', $imcHistory);

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
            error_log('calculateIMC error: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/imc/analysis', name: 'imc_analysis', methods: ['POST'])]
    public function getIMCAnalysis(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['weight']) || !isset($data['height'])) {
                return $this->json(['error' => 'Données manquantes'], 400);
            }

            $weight = (float)$data['weight'];
            $height = (float)$data['height'];
            $age = (int)($data['age'] ?? 30);
            $gender = $data['gender'] ?? 'M';

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
            error_log('getIMCAnalysis error: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

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
            error_log('getIMCHistory error: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

  #[Route('/imc/dashboard', name: 'imc_dashboard', methods: ['POST'])]
public function getIMCDashboard(Request $request, SessionInterface $session): JsonResponse
{
    try {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['weight']) || !isset($data['height'])) {
            return $this->json(['error' => 'Données manquantes'], 400);
        }

        // ===== INPUT =====
        $weight = (float)$data['weight'];
        $height = (float)$data['height'];
        $age = (int)($data['age'] ?? 30);
        $gender = $data['gender'] ?? 'M';
        $activityLevel = (float)($data['activity_level'] ?? 1.5);

        /** @var UserApp $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        // ===== 💾 SAVE USER DATA IN DB =====
        $user->setWeight($weight);
        $user->setHeight($height);
        $user->setAge($age);
        $user->setGender($gender);
        $user->setActivityLevel($activityLevel);

        $this->em->flush();

        // ===== IMC =====
        $heightM = $height / 100;
        $imc = round($weight / ($heightM * $heightM), 2);

        // ===== ANALYSIS =====
        $analysis = $this->analyzeIMC($imc, $weight, $height);
        $recommendationsText = $this->getRecommendations($imc, $weight, $height, $age, $gender);
        $ideal = $this->calculateIdealWeight($height, $gender);

        // ===== HISTORY =====
        $history = $session->get('imc_history', []);

        // ===== BMR & TDEE =====
        $bmr = $this->calculateBMR($weight, $height, $age, $gender);
        $tdee = round($bmr * $activityLevel);

        // ===== 🧠 OBJECTIFS =====
        $goals = $this->calculateNutritionGoals(
            $weight,
            $height,
            $age,
            $gender,
            $activityLevel
        );

        // ===== 🧠 SAVE SESSION (OPTIONNEL MAIS UTILE) =====
        $session->set('nutrition_goals', $goals);

        // ===== STATS =====
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

            // ===== USER DATA =====
            'measurements' => [
                'weight' => $weight,
                'height' => $height,
                'age' => $age,
                'gender' => $gender
            ],

            // ===== IMC =====
            'imc' => $imc,
            'analysis' => $analysis,
            'ideal_weight' => $ideal,

            // ===== ENERGY =====
            'bmr' => $goals['bmr'],
            'tdee' => $goals['calories'],

            // ===== 🧠 MACROS =====
            'recommendations' => [
                'protein' => $goals['protein'],
                'fat' => $goals['fat'],
                'carbs' => $goals['carbs']
            ],

            // ===== DEBUG / FRONT =====
            'goals' => $goals,

            'statistics' => $stats,
            'history_count' => count($history)
        ]);

    } catch (\Exception $e) {
        error_log('getIMCDashboard error: ' . $e->getMessage());
        return $this->json(['error' => $e->getMessage()], 500);
    }
}

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function searchIngredients(Request $request, NutritionApiService $apiService): JsonResponse
    {
        try {
            $query = $request->query->get('q');

            if (!$query || strlen($query) < 2) {
                return $this->json(['error' => 'Requête trop courte'], 400);
            }

            $results = $apiService->searchIngredients($query, 10);

            return $this->json([
                'success' => true,
                'query' => $query,
                'count' => count($results),
                'results' => $results
            ]);

        } catch (\Exception $e) {
            error_log('searchIngredients error: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/get', name: 'api_get')]
    public function getNutrition(Request $request, NutritionApiService $apiService): JsonResponse
    {
        try {
            $ingredient = $request->query->get('ingredient');

            if (!$ingredient) {
                return $this->json(['error' => 'Ingrédient manquant'], 400);
            }

            $nutrition = $apiService->getNutritionValues($ingredient);

            return $this->json([
                'success' => true,
                'ingredient' => $ingredient,
                'nutrition' => $nutrition
            ]);

        } catch (\Exception $e) {
            error_log('getNutrition error: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/compare', name: 'api_compare')]
    public function compareIngredients(Request $request, NutritionApiService $apiService): JsonResponse
    {
        try {
            $ingredient1 = $request->query->get('ingredient1');
            $ingredient2 = $request->query->get('ingredient2');

            if (!$ingredient1 || !$ingredient2) {
                return $this->json(['error' => 'Ingrédients manquants'], 400);
            }

            $ingredient1 = $this->normalize($ingredient1);
            $ingredient2 = $this->normalize($ingredient2);

            $comparison = $apiService->compareIngredients($ingredient1, $ingredient2);

            return $this->json([
                'success' => true,
                'comparison' => $comparison
            ]);

        } catch (\Exception $e) {
            error_log('compareIngredients error: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/daily-recommendations', name: 'daily_recommendations', methods: ['GET'])]
    public function getDailyRecommendations(Request $request, NutritionApiService $apiService): JsonResponse
    {
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
            error_log('getDailyRecommendations error: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/get-recipe', name: 'get_recipe', methods: ['GET'])]
    public function getRecipeNutritionApi(Request $request, NutritionApiService $apiService): JsonResponse
    {
        try {
            $id = (int)$request->query->get('id');

            if (!$id) {
                return $this->json(['error' => 'ID recette manquant'], 400);
            }

            $nutrition = $apiService->getRecipeNutrition($id);

            return $this->json([
                'success' => true,
                'recipe_id' => $id,
                'nutrition' => $nutrition
            ]);

        } catch (\Exception $e) {
            error_log('getRecipeNutritionApi error: ' . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    // ================================
    // ===== HELPERS =====
    // ================================

    private function analyzeIMC(float $imc, float $weight, float $height): array
    {
        $category = '';
        $color = '';
        $remark = '';
        $severity = '';

        if ($imc < 18.5) {
            $category = 'Sous-poids';
            $color = '#3498db';
            $severity = 'warning';
            $remark = 'Votre IMC indique un poids insuffisant. Cela peut entraîner des déficiences nutritionnelles.';
        } elseif ($imc < 25) {
            $category = 'Poids normal';
            $color = '#2ecc71';
            $severity = 'success';
            $remark = 'Votre IMC est dans la plage de poids normal. Continuez ainsi !';
        } elseif ($imc < 30) {
            $category = 'Surpoids';
            $color = '#f39c12';
            $severity = 'warning';
            $remark = 'Vous êtes en surpoids. Une amélioration des habitudes alimentaires et de l\'exercice est recommandée.';
        } elseif ($imc < 35) {
            $category = 'Obésité légère';
            $color = '#e74c3c';
            $severity = 'danger';
            $remark = 'Vous êtes en obésité légère. Une prise en charge médicale est conseillée.';
        } elseif ($imc < 40) {
            $category = 'Obésité modérée';
            $color = '#c0392b';
            $severity = 'danger';
            $remark = 'Vous êtes en obésité modérée. Une consultation médicale est fortement recommandée.';
        } else {
            $category = 'Obésité sévère';
            $color = '#8b0000';
            $severity = 'critical';
            $remark = 'Vous êtes en obésité sévère. Une prise en charge médicale urgente est nécessaire.';
        }

        return compact('imc', 'category', 'color', 'severity', 'remark', 'weight', 'height');
    }

    private function getRecommendations(float $imc, float $weight, float $height, int $age, string $gender): array
    {
        $recommendations = [
            'nutrition' => [],
            'exercise' => [],
            'lifestyle' => [],
            'medical' => []
        ];

        if ($imc < 18.5) {
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
                '😌 Gestion du stress professionnelle',
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

    private function calculateIdealWeight(float $height, string $gender): array
    {
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

    private function calculateBMR(float $weight, float $height, int $age, string $gender): float
    {
        if ($gender === 'F') {
            $bmr = 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        } else {
            $bmr = 88.362 + (13.397 * $weight) + (4.799 * $height) - (5.677 * $age);
        }

        return round($bmr);
    }

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
                '✅ Vous avez perdu %.2f kg. IMC: %.2f. Continuez !',
                abs($diff),
                $imc
            );
            $alert['severity'] = 'success';
        }

        return $alert;
    }

    private function normalize(string $ingredient): string
    {
        $ingredient = strtolower(trim($ingredient));

        if (preg_match('/\d/', $ingredient)) {
            return $ingredient;
        }

        return '200g ' . $ingredient;
    }

    private function formatLog(NutritionLog $log): array
    {
        $logDate = $log->getLog_date();
        $dateString = $logDate instanceof \DateTimeInterface ? $logDate->format('Y-m-d') : date('Y-m-d');

        return [
            'id' => (int)$log->getId(),
            'food_name' => (string)($log->getFood_name() ?? 'N/A'),
            'calories' => (float)($log->getCalories() ?? 0),
            'protein' => (float)($log->getProtein() ?? 0),
            'fat' => (float)($log->getFat() ?? 0),
            'carbs' => (float)($log->getCarbs() ?? 0),
            'log_date' => $dateString,
            'macros_total' => (float)(($log->getProtein() ?? 0) + ($log->getFat() ?? 0) + ($log->getCarbs() ?? 0))
        ];
    }
    private function calculateNutritionGoals(
    float $weight,
    float $height,
    int $age,
    string $gender,
    float $activityLevel
): array {

    // ===== 1. BMR (Mifflin-St Jeor) =====
    if ($gender === 'M') {
        $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
    } else {
        $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
    }

    // ===== 2. TDEE =====
    $tdee = $bmr * $activityLevel;

    // ===== 3. PROTEIN =====
    // 1.6 - 2.2 g/kg → on prend 1.8
    $protein = $weight * 1.8;

    // ===== 4. FAT =====
    // 25% des calories
    $fat = ($tdee * 0.25) / 9;

    // ===== 5. CARBS =====
    // reste des calories
    $carbs = ($tdee - ($protein * 4 + $fat * 9)) / 4;

    return [
        'bmr' => round($bmr),
        'calories' => round($tdee),
        'protein' => round($protein),
        'fat' => round($fat),
        'carbs' => round($carbs)
    ];
}


}