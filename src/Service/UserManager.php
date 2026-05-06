<?php

namespace App\Service;

use App\Entity\UserApp;

class UserManager
{
    public function validate(UserApp $user): bool
    {
        if (empty($user->getNom())) {
            throw new \InvalidArgumentException('Le nom est obligatoire');
        }

        if (!filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        if (strlen($user->getMot_de_passe()) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères');
        }

        return true;
    }

    public function validateResetIdentifier(string $identifier): bool
    {
        if (empty($identifier)) {
            throw new \InvalidArgumentException('L\'identifiant est obligatoire');
        }

        // Check if it's a valid email or a valid phone number (basic check)
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $isPhone = preg_match('/^[0-9]{8,}$/', $identifier);

        if (!$isEmail && !$isPhone) {
            throw new \InvalidArgumentException('L\'identifiant doit être un email ou un numéro de téléphone valide');
        }

        return true;
    }
}
