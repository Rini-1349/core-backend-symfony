<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class EmailVerifiedVoter extends Voter
{
    const IS_EMAIL_VERIFIED = 'IS_EMAIL_VERIFIED';

    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === self::IS_EMAIL_VERIFIED;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        // Vérifie si l'utilisateur a `is_verified = true`
        return $user->getIsVerified();
    }
}
