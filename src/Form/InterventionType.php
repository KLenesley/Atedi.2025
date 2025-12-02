<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Prop;
use App\Entity\Client;
use App\Entity\Equipment;
use App\Entity\Intervention;
use App\Entity\OperatingSystem;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityRepository;
use App\Repository\PropRepository;
use App\Repository\ClientRepository;
use App\Repository\EquipmentRepository;
use Symfony\Component\Form\AbstractType;
use App\Repository\OperatingSystemRepository;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class InterventionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $clientId = $options['clientId'];
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'query_builder' => function (ClientRepository $cr) use ($clientId){
                    return $cr->createQueryBuilder('c')
                    ->addSelect('CASE WHEN c.id = :clientId THEN 0 ELSE 1 END as HIDDEN orderByValue')
                    ->setParameter('clientId', $clientId)
                    ->orderBy('orderByValue')
                    ->addOrderBy('c.last_name', 'ASC');
                }
            ])
            ->add('operating_system', EntityType::class, [
                'class' => OperatingSystem::class,
                'query_builder' => function (OperatingSystemRepository $osr) {
                    return $osr->createQueryBuilder('os')
                        ->orderBy('os.id', 'DESC');
                }
            ])
            ->add('equipment', EntityType::class, [
                'class' => Equipment::class,
                'query_builder' => function (EquipmentRepository $er) {
                    return $er->createQueryBuilder('e')
                        ->orderBy('e.id', 'DESC');
                }
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'first_name', // Utilisez le prénom de l'utilisateur comme libellé
                'query_builder' => function (UserRepository $ur) {
                    return $ur->createQueryBuilder('u')
                        ->orderBy('u.first_name', 'ASC');
                }
            ])
            ->add('comment')
            ->add('tasks')
            ->add('props')
            ->add('return_date', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Intervention::class,
            'clientId' => null,
        ]);
    }
}
