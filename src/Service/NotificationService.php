<?php
namespace App\Service;

use App\Notification\GoalStatusEmailNotification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

class NotificationService
{
    public function __construct(private NotifierInterface $notifier) {}

    public function sendGoalAchieved(string $to, string $userName = 'Utilisateur'): void
    {
        $notification = new GoalStatusEmailNotification(
            '🎉 Objectif atteint !',
            $this->getGoalAchievedTemplate($userName),
            GoalStatusEmailNotification::IMPORTANCE_HIGH,
            '🎉'
        );

        $this->notifier->send($notification, new Recipient($to));
    }

    public function sendGoalNotAchieved(string $to, string $userName = 'Utilisateur'): void
    {
        $notification = new GoalStatusEmailNotification(
            '⚠️ Objectif non atteint',
            $this->getGoalNotAchievedTemplate($userName),
            GoalStatusEmailNotification::IMPORTANCE_MEDIUM,
            '⚠️'
        );

        $this->notifier->send($notification, new Recipient($to));
    }

    // ===== 🎉 TEMPLATE OBJECTIF ATTEINT =====
    private function getGoalAchievedTemplate(string $userName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .logo {
            font-size: 20px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .emoji {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
        .content {
            padding: 40px 30px;
            color: #333;
            line-height: 1.6;
        }
        .content h2 {
            color: #22c55e;
            font-size: 24px;
            margin: 0 0 15px 0;
        }
        .content p {
            margin: 10px 0;
            font-size: 16px;
        }
        .stats {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .stats p {
            margin: 8px 0;
            font-weight: 500;
            color: #166534;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            text-align: center;
        }
        .cta-button:hover {
            opacity: 0.9;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🌲 Eco <span style="color: #fb923c;">Adventure</span></div>
            <span class="emoji">🎉</span>
            <h1>Bravo {$userName} !</h1>
        </div>
        
        <div class="content">
            <h2>Objectif nutritionnel atteint ! 🏆</h2>
            <p>Félicitations {$userName} ! Vous avez atteint votre objectif calorique d'aujourd'hui.</p>
            
            <div class="stats">
                <p>✅ <strong>Objectif complété</strong></p>
                <p>📅 Date: <strong>Aujourd'hui</strong></p>
                <p>💪 Continuez cet excellent travail !</p>
            </div>
            
            <p>Votre engagement envers une nutrition saine est exemplaire. Maintenez cette dynamique pour atteindre vos objectifs de santé et de bien-être.</p>
            
            <a href="https://ecotrack.com/nutrition/dashboard" class="cta-button">
                Voir mon tableau de bord
            </a>
        </div>
        
        <div class="footer">
            <p><strong>🌲 EcoAdventure App</strong></p>
            <p>Votre compagnon nutrition quotidien</p>
            <p>© 2026 EcoAdventure. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    // ===== ⚠️ TEMPLATE OBJECTIF NON ATTEINT =====
    private function getGoalNotAchievedTemplate(string $userName): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .logo {
            font-size: 20px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .emoji {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
        .content {
            padding: 40px 30px;
            color: #333;
            line-height: 1.6;
        }
        .content h2 {
            color: #f97316;
            font-size: 24px;
            margin: 0 0 15px 0;
        }
        .content p {
            margin: 10px 0;
            font-size: 16px;
        }
        .progress-box {
            background: #fef3c7;
            border-left: 4px solid #f97316;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .tips {
            background: #faf5f0;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
            border-left: 4px solid #fb923c;
        }
        .tips h3 {
            color: #f97316;
            margin-top: 0;
        }
        .tips ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .tips li {
            margin: 8px 0;
            font-size: 14px;
            color: #333;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            text-align: center;
        }
        .cta-button:hover {
            opacity: 0.9;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
        }
        .footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🌲 Eco <span style="color: #22c55e;">Adventure</span></div>
            <span class="emoji">⚠️</span>
            <h1>Bonjour {$userName}</h1>
        </div>
        
        <div class="content">
            <h2>Objectif nutritionnel non atteint</h2>
            <p>Vous n'avez pas encore atteint votre objectif calorique d'aujourd'hui, {$userName}.</p>
            
            <div class="progress-box">
                <p>📊 <strong>Ne vous découragez pas !</strong></p>
                <p>Vous avez encore du temps aujourd'hui pour atteindre votre objectif. Continuez vos efforts ! 💪</p>
            </div>
            
            <div class="tips">
                <h3>💡 Astuces pour atteindre votre objectif :</h3>
                <ul>
                    <li>🥗 Ajoutez une collation saine</li>
                    <li>🥤 Buvez des smoothies nutritifs</li>
                    <li>🍌 Fruits secs ou fruits frais</li>
                    <li>🥜 Noix et oléagineux</li>
                    <li>🍎 Produits laitiers enrichis</li>
                </ul>
            </div>
            
            <a href="https://ecotrack.com/nutrition/dashboard" class="cta-button">
                Ajouter des aliments
            </a>
        </div>
        
        <div class="footer">
            <p><strong>🌲 EcoAdventure App</strong></p>
            <p>Votre compagnon nutrition quotidien</p>
            <p>© 2026 EcoAdventure. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}