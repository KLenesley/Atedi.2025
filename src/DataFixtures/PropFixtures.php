<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

use App\Entity\Prop;

class PropFixtures extends Fixture
{
    public function load(ObjectManager $manager):void
    {
        $data = new Prop();
        $data->setTitle('Sacoche');
        $manager->persist($data);

        $data = new Prop();
        $data->setTitle('Chargeur');
        $manager->persist($data);

        $manager->flush();
    }
}
