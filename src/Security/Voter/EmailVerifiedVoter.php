<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class EmailVerifiedVoter extends Voter
{
    public const IS_VERIFIED = 'IS_VERIFIED';

    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, $subject): bool
    {
        // Only vote on IS_VERIFIED attribute and User objects
        return $attribute === self::IS_VERIFIED && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // The user must be logged in
        if (!$user instanceof User) {
            return false;
        }

        // Admins bypass the verification requirement
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Check if the user's email is verified
        return $user->isVerified();
    }
}
