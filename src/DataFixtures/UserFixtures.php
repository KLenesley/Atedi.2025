<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private $passwordEncoder;

    public function __construct(UserPasswordHasherInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager):void
    {
        $data = new User();
        $data->setEmail('admin@gmail.com');
        $data->setRoles(['ROLE_ADMIN']);
        $data->setFirstName('admin');
        $data->setPassword($this->passwordEncoder->hashPassword($data,'admin'));
        $manager->persist($data);

        $manager->flush();
    }
}
