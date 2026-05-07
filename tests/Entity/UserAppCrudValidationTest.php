<?php

namespace App\Tests\Entity;

use App\Entity\UserApp;
use App\Enum\Disponibilite;
use App\Enum\RoleUser;
use App\Enum\Specialite;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserAppCrudValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new class implements ConstraintValidatorFactoryInterface {
                private ConstraintValidatorFactory $defaultFactory;

                public function __construct()
                {
                    $this->defaultFactory = new ConstraintValidatorFactory();
                }

                public function getInstance(Constraint $constraint): ConstraintValidatorInterface
                {
                    if ($constraint instanceof UniqueEntity) {
                        return new class implements ConstraintValidatorInterface {
                            public function initialize(ExecutionContextInterface $context): void
                            {
                            }

                            public function validate(mixed $value, Constraint $constraint): void
                            {
                            }
                        };
                    }

                    return $this->defaultFactory->getInstance($constraint);
                }
            })
            ->getValidator();
    }

    public function testNewUserAppHasCrudDefaults(): void
    {
        $user = new UserApp();

        self::assertInstanceOf(\DateTimeInterface::class, $user->getDate_creation());
        self::assertSame(0, $user->getLoyaltyPoints());
        self::assertSame(0, $user->getFailedAttempts());
        self::assertNotEmpty($user->getReferralCode());
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testValidationRejectsInvalidUserPayloadUsedByCreateAndEdit(): void
    {
        $user = new UserApp();
        $user->setNom('');
        $user->setPrenom('');
        $user->setEmail('invalid-email');
        $user->setRole(RoleUser::USER_SIMPLE);
        $user->setMot_de_passe('hashed-password');

        $violations = $this->validator->validate($user);
        $paths = array_map(
            static fn ($violation): string => $violation->getPropertyPath(),
            iterator_to_array($violations)
        );

        self::assertContains('nom', $paths);
        self::assertContains('prenom', $paths);
        self::assertContains('email', $paths);
    }

    public function testCoachCrudPayloadRequiresCoachSpecificFields(): void
    {
        $coach = $this->createValidUser();
        $coach->setRole(RoleUser::COACH);
        $coach->setAge(null);
        $coach->setExperience(null);
        $coach->setSpecialite(null);
        $coach->setDisponibilite(null);

        $violations = $this->validator->validate($coach);
        $paths = array_map(
            static fn ($violation): string => $violation->getPropertyPath(),
            iterator_to_array($violations)
        );

        self::assertContains('age', $paths);
        self::assertContains('experience', $paths);
        self::assertContains('specialite', $paths);
        self::assertContains('disponibilite', $paths);
    }

    public function testCompleteCoachCrudPayloadIsValid(): void
    {
        $coach = $this->createValidUser();
        $coach->setRole(RoleUser::COACH);
        $coach->setAge(35);
        $coach->setExperience('10');
        $coach->setSpecialite(Specialite::FITNESS);
        $coach->setDisponibilite(Disponibilite::MATIN);

        self::assertCount(0, $this->validator->validate($coach));
    }

    private function createValidUser(): UserApp
    {
        $user = new UserApp();
        $user->setNom('Doe');
        $user->setPrenom('Jane');
        $user->setEmail('jane.doe@example.com');
        $user->setRole(RoleUser::USER_SIMPLE);
        $user->setMot_de_passe('hashed-password');

        return $user;
    }
}
