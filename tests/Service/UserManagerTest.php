<?php

namespace App\Tests\Service;

use App\Entity\UserApp;
use App\Service\UserManager;
use PHPUnit\Framework\TestCase;

class UserManagerTest extends TestCase
{
    public function testValidUser()
    {
        $user = new UserApp();
        $user->setNom('Ben Foulen');
        $user->setPrenom('Foulen');
        $user->setEmail('foulen@gmail.com');
        $user->setMot_de_passe('password123'); // 11 characters

        $manager = new UserManager();

        $this->assertTrue($manager->validate($user));
    }

    public function testUserWithoutName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $user = new UserApp();
        $user->setEmail('test@gmail.com');
        $user->setMot_de_passe('password123');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserWithInvalidEmail()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email invalide');

        $user = new UserApp();
        $user->setNom('User Test');
        $user->setEmail('email_invalide');
        $user->setMot_de_passe('password123');

        $manager = new UserManager();
        $manager->validate($user);
    }

    public function testUserWithShortPassword()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le mot de passe doit contenir au moins 8 caractères');

        $user = new UserApp();
        $user->setNom('User Test');
        $user->setEmail('test@gmail.com');
        $user->setMot_de_passe('123'); // less than 8 characters

        $manager = new UserManager();
        $manager->validate($user);
    }
}
