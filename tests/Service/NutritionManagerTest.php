<?php
namespace App\Tests\Service;

use App\Entity\NutritionLog;
use App\Service\NutritionManager;
use PHPUnit\Framework\TestCase;

class NutritionManagerTest extends TestCase
{
public function testNutritionValide(): void
 {
$food = new NutritionLog();

 $food->setFood_name('Riz');
 $food->setCalories(300);
 $food->setProtein(5);
 $food->setLog_date(new \DateTime());

 $this->assertTrue((new NutritionManager())->validate($food));
}

 public function testNutritionSansNom(): void
 {
 $this->expectException(\InvalidArgumentException::class);

 $food = new NutritionLog();
 $food->setCalories(300);

 (new NutritionManager())->validate($food);
 }

 public function testNutritionCaloriesInvalides(): void
{
$this->expectException(\InvalidArgumentException::class);

 $food = new NutritionLog();
 $food->setFood_name('Test');
 $food->setCalories(0);
 (new NutritionManager())->validate($food);
    }   }