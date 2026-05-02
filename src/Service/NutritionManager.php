<?php
namespace App\Service;

use App\Entity\NutritionLog;

class NutritionManager
{
    public function validate(NutritionLog $food): bool // ✅ IMPORTANT
    {
        if (empty($food->getFood_name())) {
            throw new \InvalidArgumentException('Nom obligatoire');
        }

        if ($food->getCalories() <= 0) {
            throw new \InvalidArgumentException('Calories invalides');
        }

        if ($food->getProtein() < 0) {
            throw new \InvalidArgumentException('Protéines invalides');
        }

        if (!$food->getLog_date()) {
            throw new \InvalidArgumentException('Date obligatoire');
        }

        return true;
    }
}