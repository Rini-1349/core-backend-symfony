<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

class AppFixtures extends Fixture
{
    public function __construct(private PasswordHasherFactoryInterface $passwordHasherFactory) {
    
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasherFactory->getPasswordHasher(User::class)->hash('secret'));
        $user->setFirstname('Didje');
        $user->setLastname('Hazanavicius');
        $user->setIsVerified(true);
        $manager->persist($user);

        $manager->flush();
    }
}
