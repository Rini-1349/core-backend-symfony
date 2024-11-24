<?php 

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function sendEmailConfirmation(string $route, User $user, string $symfonyAppUrl, string $targetAppUrl, TemplatedEmail $email): void
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $route,
            $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()] // add the user's id as an extra query param
        );

        // Change Symfony URL to target app URL
        $signedUrl = str_replace(($symfonyAppUrl . '/api'), $targetAppUrl, $signatureComponents->getSignedUrl());

        $context = $email->getContext();
        $context['user'] = $user;
        $context['signedUrl'] = $signedUrl;
        $context['expiresAtMessageKey'] = $signatureComponents->getExpirationMessageKey();
        $context['expiresAtMessageData'] = $signatureComponents->getExpirationMessageData();

        $email->context($context);

        $this->mailer->send($email);
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, User $user)
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, $user->getId(), $user->getEmail());

        if ($user->getIsVerified() == false) {
            $user->setIsVerified(true);

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
    }
}