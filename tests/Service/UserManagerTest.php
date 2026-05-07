<?php

namespace App\Tests\Service;

use App\Entity\UserApp;
use App\Entity\Reclamation;
use App\Service\UserManager;
use App\Service\ReclamationProcessor;
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

    public function testValidResetIdentifierEmail()
    {
        $manager = new UserManager();
        $this->assertTrue($manager->validateResetIdentifier('test@gmail.com'));
    }

    public function testValidResetIdentifierPhone()
    {
        $manager = new UserManager();
        $this->assertTrue($manager->validateResetIdentifier('12345678'));
    }

    public function testEmptyResetIdentifier()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'identifiant est obligatoire');

        $manager = new UserManager();
        $manager->validateResetIdentifier('');
    }

    public function testInvalidResetIdentifier()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'identifiant doit être un email ou un numéro de téléphone valide');

        $manager = new UserManager();
        $manager->validateResetIdentifier('invalid');
    }

    public function testValidReclamation()
    {
        $reclamation = new Reclamation();
        $reclamation->setType('Technique');
        $reclamation->setContenu('Problème de connexion');

        $manager = new UserManager();
        $this->assertTrue($manager->validateReclamation($reclamation));
    }

    public function testReclamationWithoutContent()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu de la réclamation est obligatoire');

        $reclamation = new Reclamation();
        $reclamation->setType('Technique');

        $manager = new UserManager();
        $manager->validateReclamation($reclamation);
    }

    public function testReclamationWithoutType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de la réclamation est obligatoire');

        $reclamation = new Reclamation();
        $reclamation->setContenu('Problème de connexion');

        $manager = new UserManager();
        $manager->validateReclamation($reclamation);
    }

    public function testReclamationPriorityHAUTE()
    {
        $reclamation = new Reclamation();
        $reclamation->setType('Problème grave');
        $reclamation->setContenu('Il y a un danger imminent');

        $processor = new ReclamationProcessor();
        $processor->calculatePriority($reclamation);

        $this->assertEquals('HAUTE', $reclamation->getPriorite());
    }

    public function testReclamationPriorityMOYENNE()
    {
        $reclamation = new Reclamation();
        $reclamation->setType('Retard');
        $reclamation->setContenu('Le coach est en retard');

        $processor = new ReclamationProcessor();
        $processor->calculatePriority($reclamation);

        $this->assertEquals('MOYENNE', $reclamation->getPriorite());
    }

    public function testReclamationPriorityBASSE()
    {
        $reclamation = new Reclamation();
        $reclamation->setType('Suggestion');
        $reclamation->setContenu('Vous devriez ajouter plus de fleurs');

        $processor = new ReclamationProcessor();
        $processor->calculatePriority($reclamation);

        $this->assertEquals('BASSE', $reclamation->getPriorite());
    }

    public function testReclamationDefaultStatusIsEnAttente()
    {
        $reclamation = new Reclamation();
        $this->assertEquals(\App\Enum\StatutReclamation::EN_ATTENTE, $reclamation->getStatut());
    }

    public function testReclamationDateCreationIsSet()
    {
        $reclamation = new Reclamation();
        $this->assertInstanceOf(\DateTimeInterface::class, $reclamation->getDate_creation());
    }

    public function testReclamationUserAssociation()
    {
        $user = new UserApp();
        $user->setNom('Ben Foulen');

        $reclamation = new Reclamation();
        $reclamation->setUserApp($user);

        $this->assertSame($user, $reclamation->getUserApp());
    }
}
