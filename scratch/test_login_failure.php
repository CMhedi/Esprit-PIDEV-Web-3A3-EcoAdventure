<?php

use App\Entity\UserApp;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/vendor/autoload.php';

$kernel = new \App\Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();
$eventDispatcher = $container->get('event_dispatcher');

$email = 'salmalahmar5@gmail.com';
$user = $entityManager->getRepository(UserApp::class)->findOneBy(['email' => $email]);

if (!$user) {
    echo "User not found\n";
    exit(1);
}

echo "Current failed attempts: " . $user->getFailedAttempts() . "\n";

// Simulate 3 failures
for ($i = 1; $i <= 3; $i++) {
    echo "Simulating failure $i...\n";
    
    $passport = new Passport(
        new UserBadge($email),
        new PasswordCredentials('wrong'),
        []
    );
    
    $event = new LoginFailureEvent(
        new BadCredentialsException(),
        $container->get('security.authenticator.manager.main'), // This might be tricky in cli
        new Request(),
        null,
        'main',
        $passport
    );
    
    $eventDispatcher->dispatch($event);
}

$entityManager->refresh($user);
echo "New failed attempts: " . $user->getFailedAttempts() . "\n";

if ($user->getFailedAttempts() >= 3) {
    echo "SUCCESS: Failed attempts tracked correctly.\n";
} else {
    echo "FAILURE: Failed attempts not tracked.\n";
}
