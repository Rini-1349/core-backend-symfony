<?php

namespace App\DataFixtures;

use App\Entity\Role;
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
        foreach (['ROLE_SUPERADMIN' => 'Superadmin', 'ROLE_USER' => 'Utilisateur'] as $roleId => $description) {
            $role = new Role();
            $role->setId($roleId);
            $role->setDescription($description);
            $manager->persist($role);
        }
        $manager->flush();

        $user = new User();
        $user->setId(1);
        $user->setEmail('test@test.com');
        $user->setRoles(['ROLE_SUPERADMIN']);
        $user->setPassword($this->passwordHasherFactory->getPasswordHasher(User::class)->hash('secret'));
        $user->setFirstname('Didje');
        $user->setLastname('Hazanavicius');
        $user->setIsVerified(true);
        $manager->persist($user);

        $manager->flush();
    }
}
