<?php

namespace App\Command;

use App\Repository\UserAppRepository;
use App\Repository\NutritionLogRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-goals',
    description: 'Vérifie les objectifs nutritionnels et envoie des emails',
)]
class CheckGoalsCommand extends Command
{
    public function __construct(
        private UserAppRepository $userRepo,
        private NutritionLogRepository $logRepo,
        private NotificationService $notificationService,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->userRepo->findAll();
        $today = new \DateTime();

        $achievedCount = 0;
        $notAchievedCount = 0;
        $hasUpdates = false;

        foreach ($users as $user) {

            // sécurité
            if (!$user->getWeight() || !$user->getHeight()) {
                continue;
            }

            // ===== STATS =====
            $stats = $this->logRepo->getTotalMacros($user->getId(), $today);

            // ===== GOALS =====
            $goals = $this->calculateGoals($user);

            // Do not send duplicates if this user was already notified for today.
            if ($user->isGoalNotified()) {
                continue;
            }

            // ===== 🎉 OBJECTIF ATTEINT =====
            if ($stats['calories'] >= $goals['calories']) {
                try {
                    $userName = trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''));
                    $this->notificationService->sendGoalAchieved($user->getEmail(), $userName);

                    $user->setGoalNotified(true);
                    $hasUpdates = true;
                    $achievedCount++;
                    $io->info("✅ Email envoyé à {$user->getEmail()} - Objectif atteint");
                } catch (\Exception $e) {
                    $io->error("❌ Erreur: {$e->getMessage()}");
                }
            }

            // ===== ⚠️ OBJECTIF NON ATTEINT =====
            elseif ($stats['calories'] < $goals['calories']) {
                try {
                    $userName = trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''));
                    $this->notificationService->sendGoalNotAchieved($user->getEmail(), $userName);

                    $user->setGoalNotified(true);
                    $hasUpdates = true;
                    $notAchievedCount++;
                    $io->info("⚠️ Email envoyé à {$user->getEmail()} - Objectif non atteint");
                } catch (\Exception $e) {
                    $io->error("❌ Erreur: {$e->getMessage()}");
                }
            }
        }

        if ($hasUpdates) {
            $this->em->flush();
        }

        $io->success("Emails envoyés: $achievedCount objectifs atteints, $notAchievedCount non atteints");

        return Command::SUCCESS;
    }

    // ===== 🧠 CALCUL CENTRALISÉ =====
    private function calculateGoals($user): array
    {
        $weight = $user->getWeight();
        $height = $user->getHeight();
        $age = $user->getAge() ?? 30;
        $gender = $user->getGender() ?? 'M';
        $activity = $user->getActivityLevel() ?? 1.5;

        // BMR
        if ($gender === 'M') {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age + 5;
        } else {
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age - 161;
        }

        $calories = $bmr * $activity;

        $protein = $weight * 1.8;
        $fat = ($calories * 0.25) / 9;
        $carbs = ($calories - ($protein * 4 + $fat * 9)) / 4;

        return [
            'calories' => round($calories),
            'protein' => round($protein),
            'fat' => round($fat),
            'carbs' => round($carbs)
        ];
    }
}