<?php

namespace App\Controller;

use App\Entity\UserApp;
use App\Enum\RoleUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class SetupController extends AbstractController
{
    #[Route('/setup-users', name: 'setup_users')]
    public function setupUsers(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // Check if users already exist
        $userRepository = $em->getRepository(UserApp::class);
        $existingUsers = $userRepository->findAll();
        
        if (!empty($existingUsers)) {
            return new Response('Users already exist in the database.');
        }

        // Create test users
        $users = [
            [
                'email' => 'admin@ecoadventure.com',
                'password' => 'admin123',
                'nom' => 'Admin',
                'prenom' => 'System',
                'role' => RoleUser::ADMIN,
                'telephone' => '71234567',
                'age' => 35
            ],
            [
                'email' => 'coach@ecoadventure.com',
                'password' => 'coach123',
                'nom' => 'Coach',
                'prenom' => 'Sportif',
                'role' => RoleUser::COACH,
                'telephone' => '71234568',
                'age' => 28
            ],
            [
                'email' => 'user@ecoadventure.com',
                'password' => 'user123',
                'nom' => 'User',
                'prenom' => 'Simple',
                'role' => RoleUser::USER_SIMPLE,
                'telephone' => '71234569',
                'age' => 25
            ],
            [
                'email' => 'alice@ecoadventure.com',
                'password' => 'alice123',
                'nom' => 'Alice',
                'prenom' => 'Martin',
                'role' => RoleUser::USER_SIMPLE,
                'telephone' => '71234570',
                'age' => 22
            ],
            [
                'email' => 'bob@ecoadventure.com',
                'password' => 'bob123',
                'nom' => 'Bob',
                'prenom' => 'Wilson',
                'role' => RoleUser::USER_SIMPLE,
                'telephone' => '71234571',
                'age' => 30
            ]
        ];

        foreach ($users as $userData) {
            $user = new UserApp();
            $user->setEmail($userData['email']);
            $user->setNom($userData['nom']);
            $user->setPrenom($userData['prenom']);
            $user->setRole($userData['role']);
            $user->setTelephone($userData['telephone']);
            $user->setAge($userData['age']);
            
            // Hash the password
            $hashedPassword = $passwordHasher->hashPassword(
                $user,
                $userData['password']
            );
            $user->setMot_de_passe($hashedPassword);
            
            $em->persist($user);
        }

        $em->flush();

        return new Response('<h1>Test Users Created Successfully!</h1>
            <p>Use these credentials to test the application:</p>
            <ul>
                <li><strong>Admin:</strong> admin@ecoadventure.com / admin123</li>
                <li><strong>Coach:</strong> coach@ecoadventure.com / coach123</li>
                <li><strong>User:</strong> user@ecoadventure.com / user123</li>
                <li><strong>Alice:</strong> alice@ecoadventure.com / alice123</li>
                <li><strong>Bob:</strong> bob@ecoadventure.com / bob123</li>
            </ul>
            <p><a href="/">Go to Home</a> | <a href="/login">Go to Login</a></p>');
    }
}
