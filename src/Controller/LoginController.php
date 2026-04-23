<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/api/user/face-image', name: 'api_user_face_image', methods: ['POST'])]
    public function getUserFaceImage(\Symfony\Component\HttpFoundation\Request $request, \App\Repository\UserAppRepository $userRepo): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        // Si pas d'email, on renvoie tous les admins qui ont un descripteur (pour la recherche automatique)
        if (!$email) {
            try {
                $admins = $userRepo->createQueryBuilder('u')
                    ->where('u.faceDescriptor IS NOT NULL')
                    ->andWhere('u.role = :role')
                    ->setParameter('role', \App\Enum\RoleUser::ADMIN)
                    ->getQuery()
                    ->getResult();
                
                $descriptors = [];
                foreach ($admins as $admin) {
                    $descriptors[] = [
                        'email' => $admin->getEmail(),
                        'descriptor' => $admin->getFaceDescriptor()
                    ];
                }
                return new \Symfony\Component\HttpFoundation\JsonResponse(['admins' => $descriptors]);
            } catch (\Exception $e) {
                // Si la colonne n'existe pas encore ou autre erreur DB
                return new \Symfony\Component\HttpFoundation\JsonResponse(['admins' => [], 'debug' => 'Migration non effectuée ?']);
            }
        }

        $user = $userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => 'User not found'], 404);
        }

        return new \Symfony\Component\HttpFoundation\JsonResponse([
            'imageUrl' => $user->getImage_url() ? '/' . $user->getImage_url() : null,
            'descriptor' => $user->getFaceDescriptor()
        ]);
    }

    #[Route(path: '/api/user/save-face-descriptor', name: 'api_user_save_face_descriptor', methods: ['POST'])]
    public function saveFaceDescriptor(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\UserApp) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $descriptor = $data['descriptor'] ?? null;

        if (!$descriptor || !is_array($descriptor)) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => 'Invalid descriptor'], 400);
        }

        $user->setFaceDescriptor($descriptor);
        $em->flush();

        return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => true]);
    }

    #[Route(path: '/login/face', name: 'app_login_face', methods: ['POST'])]
    public function loginFace(\Symfony\Component\HttpFoundation\Request $request, \App\Repository\UserAppRepository $userRepo, \Symfony\Bundle\SecurityBundle\Security $security, \Symfony\Component\Routing\Generator\UrlGeneratorInterface $urlGenerator): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => 'Email required'], 400);
        }

        $user = $userRepo->findOneBy(['email' => $email]);
        if (!$user) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => 'User not found'], 404);
        }

        // Programmatically login the user
        $security->login($user, 'App\Security\LoginManagerAuthenticator', 'main');

        // Redirect based on role
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            $redirectUrl = $urlGenerator->generate('app_admin_dashboard');
        } else {
            $redirectUrl = $urlGenerator->generate('app_home');
        }

        return new \Symfony\Component\HttpFoundation\JsonResponse([
            'success' => true,
            'redirect' => $redirectUrl
        ]);
    }
}
