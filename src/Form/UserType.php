<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $builder->getData();

        $builder
            ->add('firstname', null, ['label' => '* Prénom'])
            ->add('lastname', null, ['label' => '* Nom'])
            ->add('email', null, ['label' => '* Email'])
            ->add('password', PasswordType::class, ['label' => '* Mot de passe'])
            ->add('avatarFile', FileType::class, [
                'label' => 'Avatar',
                'required' => $user->getAvatar() ? false : true
                , 'mapped' => false,
                'constraints' => [
                    new Image([
                        'mimeTypesMessage' => 'Veuillez soumettre une image',
                        'maxSize' => "1M",
                        'maxSizeMessage' => "Votre image fait {{ size }} {{ suffix }}. Cela ne peut dépasser {{ limit }} {{ suffix }}"
                    ])
                ]
                ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
