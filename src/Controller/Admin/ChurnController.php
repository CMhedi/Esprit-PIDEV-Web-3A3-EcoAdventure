<?php

namespace App\Controller\Admin;

use App\Entity\UserApp;
use App\Repository\UserAppRepository;
use App\Service\ChurnService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use App\Enum\RoleUser;
/**
 * 🚀 Contrôleur Churn - CORRIGÉ et Optimisé
 */
#[Route('/admin/api/churn')]
#[IsGranted('ROLE_ADMIN')]
class ChurnController extends AbstractController
{
    
    public function __construct(
        private ChurnService $churnService,
        private UserAppRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}
    
    /**
     * 🔥 GET /admin/api/churn/health
     * Vérifier l'état de l'API ML
     */
    #[Route('/health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        try {
            $isHealthy = $this->churnService->isHealthy();
            
            return $this->json([
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'message' => $isHealthy ? 'API ML opérationnelle' : 'API ML indisponible',
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ], $isHealthy ? 200 : 503);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 503);
        }
    }
    
    /**
     * 🔥 GET /admin/api/churn/view
     * Affichage HTML du dashboard
     */
    #[Route('/view', name: 'admin_churn_view', methods: ['GET'])]
    public function churnView(): Response
    {
        $users = $this->userRepository->findBy(
    ['role' => RoleUser::USER_SIMPLE],
    ['churnProbability' => 'DESC']
);

        $stats = [
            'total_users' => count($users),
            'high_risk' => 0,
            'medium_risk' => 0,
            'low_risk' => 0
        ];

        foreach ($users as $user) {
            match ($user->getChurnRisk()) {
                'high' => $stats['high_risk']++,
                'medium' => $stats['medium_risk']++,
                default => $stats['low_risk']++
            };
        }

        return $this->render('admin/churn_dashboard.html.twig', [
            'users' => $users,
            'stats' => $stats
        ]);
    }
    
    /**
     * 🔥 POST /admin/api/churn/predict/{userId}
     * Prédiction pour UN utilisateur
     * ✅ CORRIGÉ: getIdUser() + getUserFeatures()
     */
    #[Route('/predict/{userId}', methods: ['POST'])]
    public function predictUser(int $userId): JsonResponse
    {
        try {
            // ✅ Récupérer l'utilisateur
            $user = $this->userRepository->find($userId);
            if (!$user) {
                return $this->json(['error' => 'Utilisateur non trouvé'], 404);
            }

           $userId = $user->getId_user();

if (!$userId) {
    return $this->json(['error' => 'ID utilisateur invalide'], 400);
}

$features = $this->userRepository->getUserFeatures($userId);
            if (!$features) {
                return $this->json(['error' => 'Impossible de générer les features'], 400);
            }

            // ✅ Nettoyer les features
            unset($features['id_user']);
            $features = array_map(fn($v) => $v ?? 0, $features);

            // 🔥 Prédiction
            $prediction = $this->churnService->predictUser($features);

            // 💾 Sauvegarde
            $this->savePrediction($user, $prediction);
            $this->entityManager->flush();

            $this->logger->info("✅ Prédiction user $userId: prob={$prediction['probability']}");

            return $this->json($prediction);

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur prédiction: {$e->getMessage()}");
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * 🔥 POST /admin/api/churn/predict-all
     * Prédictions pour TOUS les utilisateurs
     * ✅ CORRIGÉ: mapping index + idUser + validUsers
     */
    #[Route('/predict-all', methods: ['POST', 'GET'])]
    public function predictAllUsers(Request $request): JsonResponse
    {
        try {
            $batchSize = (int) $request->query->get('batch_size', 100);

            $this->logger->info("🔄 Prédictions pour tous les utilisateurs");

            $totalUsers = $this->userRepository->count([]);
            $totalPages = ceil($totalUsers / $batchSize);

            $totalProcessed = 0;
            $totalErrors = 0;

            for ($page = 0; $page < $totalPages; $page++) {
                $offset = $page * $batchSize;

$users = $this->userRepository->createQueryBuilder('u')
    ->where('u.role = :role')
    ->setParameter('role', RoleUser::USER_SIMPLE)
    ->setFirstResult($offset)
    ->setMaxResults($batchSize)
    ->orderBy('u.id_user', 'DESC')
    ->getQuery()
    ->getResult();

                // ✅ CORRIGÉ: générer features + tracker validUsers
                $usersData = [];
                $validUsers = [];

                foreach ($users as $user) {
                    $userId = $user->getId_user();

if (!$userId) {
    return $this->json(['error' => 'ID utilisateur invalide'], 400);
}

$features = $this->userRepository->getUserFeatures($userId);// ✅ getIdUser()

                    if (!$features) {
                        $totalErrors++;
                        continue;
                    }

                    unset($features['id_user']);
                    $features = array_map(fn($v) => $v ?? 0, $features);

                    $usersData[] = $features;
                    $validUsers[] = $user;  // ✅ IMPORTANT: tracker les users valides
                }

                // Passer au batch si pas de données
                if (empty($usersData)) {
                    continue;
                }

                // 🔥 Prédictions batch
                $predictions = $this->churnService->predictBatch($usersData);

                // ✅ CORRIGÉ: mapper avec validUsers (pas users)
                foreach ($predictions['predictions'] as $index => $pred) {
                    if (isset($validUsers[$index])) {
                        $this->savePrediction($validUsers[$index], $pred);
                        $totalProcessed++;
                    }
                }

                // ⚡ Flush optimisé
                $this->entityManager->flush();
                $this->entityManager->clear();
                $this->logger->info("💾 Batch " . ($page + 1) . "/$totalPages sauvegardé");
            }

            return $this->json([
                'total_processed' => $totalProcessed,
                'total_errors' => $totalErrors,
                'total_users' => $totalUsers,
                'message' => 'Prédictions mises à jour',
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur globale: {$e->getMessage()}");
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * 🔥 GET /admin/api/churn/dashboard
     * Dashboard avec filtres
     * ✅ CORRIGÉ: utiliser getIdUser()
     */
    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, (int) $request->query->get('limit', 50));
            $riskFilter = $request->query->get('risk_level');
            
            $criteria = $riskFilter ? ['churnRisk' => $riskFilter] : [];
            $users = $this->userRepository->findBy(
                $criteria,
                ['churnProbability' => 'DESC'],
                $limit,
                ($page - 1) * $limit
            );
            
            $total = $this->userRepository->count($criteria);
            
            $data = array_map(function ($user) {
                $probability = round($user->getChurnProbability(), 4);
               $riskLevel = $user->getChurnRisk();
                
                $riskColor = match($riskLevel) {
                    'high' => '#dc3545',
                    'medium' => '#ffc107',
                    'low' => '#28a745',
                    default => '#6c757d'
                };
                
                return [
                    'id' => $user->getId_user(),
                    'name' => $user->getNom() . ' ' . $user->getPrenom(),
                    'email' => $user->getEmail(),
                    'churn' => [
                        'probability' => $probability,
                        'prediction' => (int) $user->getChurnPrediction(),
                        'risk_level' => $riskLevel,
                        'risk_color' => $riskColor,
                        'last_update' => $user->getLastPredictionAt()?->format('Y-m-d H:i:s') ?? 'jamais',
                    ],
                    'metrics' => [
                        'reservations_7j' => $user->getReservations7Days(),
                        'absences_30j' => $user->getAbsences30Days(),
                        'active_days' => $user->getActiveDays30(),
                    ]
                ];
            }, $users);
            
            return $this->json([
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit),
                'filter' => $riskFilter ?? 'all',
                'users' => $data,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur dashboard: {$e->getMessage()}");
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * 🔥 GET /admin/api/churn/high-risk
     * Utilisateurs à risque ÉLEVÉ
     */
    #[Route('/high-risk', methods: ['GET'])]
    public function highRiskUsers(Request $request): JsonResponse
    {
        try {
            $limit = min(500, (int) $request->query->get('limit', 100));
            
            $users = $this->userRepository->findBy(
                ['churnRisk' => 'high'],
                ['churnProbability' => 'DESC'],
                $limit
            );
            
            $data = array_map(fn($user) => [
                'id' => $user->getId_user(),
                'name' => $user->getNom() . ' ' . $user->getPrenom(),
                'email' => $user->getEmail(),
                'probability' => round($user->getChurnProbability(), 4),
                'risk_color' => '#dc3545',
                'last_update' => $user->getLastPredictionAt()?->format('Y-m-d H:i:s'),
            ], $users);
            
            return $this->json([
                'count' => count($data),
                'users' => $data,
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur high risk: {$e->getMessage()}");
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * 🔥 GET /admin/api/churn/stats
     * Statistiques globales
     */
    #[Route('/stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        try {
            $totalUsers = $this->userRepository->count([]);
            $highRisk = $this->userRepository->count(['churnRisk' => 'high']);
            $mediumRisk = $this->userRepository->count(['churnRisk' => 'medium']);
            $lowRisk = $this->userRepository->count(['churnRisk' => 'low']);
            
            return $this->json([
                'total_users' => $totalUsers,
                'high_risk' => $highRisk,
                'high_risk_percent' => round(($highRisk / max(1, $totalUsers)) * 100, 2),
                'medium_risk' => $mediumRisk,
                'medium_risk_percent' => round(($mediumRisk / max(1, $totalUsers)) * 100, 2),
                'low_risk' => $lowRisk,
                'low_risk_percent' => round(($lowRisk / max(1, $totalUsers)) * 100, 2),
                'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error("❌ Erreur stats: {$e->getMessage()}");
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * 🔥 GET /admin/api/churn/user/{userId}
     * Détails d'UN utilisateur
     */
    #[Route('/user/{userId}', methods: ['GET'])]
    public function userDetail(int $userId): JsonResponse
    {
        try {
            $user = $this->userRepository->find($userId);
            if (!$user) {
                return $this->json(['error' => 'Utilisateur non trouvé'], 404);
            }
            
            $probability = round($user->getChurnProbability(), 4);
          $riskLevel = $user->getChurnRisk();
            
            $riskColor = match($riskLevel) {
                'high' => '#dc3545',
                'medium' => '#ffc107',
                'low' => '#28a745',
                default => '#6c757d'
            };
            
            return $this->json([
                'id' => $user->getId_user(),
                'name' => $user->getNom() . ' ' . $user->getPrenom(),
                'email' => $user->getEmail(),
                'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'churn' => [
                    'probability' => $probability,
                    'prediction' => (int) ($user->getChurnPrediction()),
                    'risk_level' => $riskLevel,
                    'risk_color' => $riskColor,
                    'last_update' => $user->getLastPredictionAt()?->format('Y-m-d H:i:s') ?? 'jamais',
                ],
                'metrics' => [
                    'reservations_7j' => $user->getReservations7Days(),
                    'absences_30j' => $user->getAbsences30Days(),
                    'absence_rate' => round($user->getAbsenceRate(), 4),
                    'active_days' => $user->getActiveDays30(),
                    'avg_calories' => round($user->getAvgCalories7Days(), 2),
                ],
                'trends' => [
                    'reservations' => round($user->getReservationTrend(), 4),
                    'absence' => round($user->getAbsenceTrend(), 4),
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
    
    // ==================== PRIVATE METHODS ====================
    
/**
 * @param array{
 *     churn: int|bool,
 *     probability: float
 * } $prediction
 */
private function savePrediction(UserApp $user, array $prediction): void
{

    $probability = (float) $prediction['probability'];

    // 🎯 Définir le risque
if ($probability > 0.6) {
    $risk = 'high';
} elseif ($probability > 0.3) {
    $risk = 'medium';
} else {
    $risk = 'low';
}
    $user->setChurnProbability($probability);
    $user->setChurnPrediction((bool) $prediction['churn']);
    $user->setChurnRisk($risk);
    $user->setLastPredictionAt(new \DateTime());
}
}