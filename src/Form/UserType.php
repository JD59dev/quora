<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $builder->getData();
        $builder
            ->add('firstname', TextType::class, ['label' => '* Prénom'])
            ->add('lastname', TextType::class, ['label' => '* Nom'])
            ->add('email', EmailType::class, ['label' => '* Email'])
            ->add('password', PasswordType::class, ['label' => '* Mot de passe'])
            //->add('confirmPassword', PasswordType::class, ['label' => '* Confirmation de mot de passe'])
            ->add('pictureFile', FileType::class, [ // FileType -> données de type fichier
                'required' => $options['new_user'],
                // 'required' => $user->getAvatar() ? false : true, // Pas obligatoire puisqu'on peut lui fournir un avatar par défaut lors de l'inscription ou de l'édition
                'label' => 'Photo de profil',
                'mapped' => false, // l'image lui-même ne fait pas faire partie de la BDD (de l'entité User)
                'constraints' => [
                    new Image([ // nouvelle objet Image
                        'mimeTypesMessage' => "Veuillez télécharger une image (PNG, JPG, JPEG, SVG...).", // Pour détecter le type du fichier
                        'maxSize' => '2M', // Taille maximale de l'image
                        'maxSizeMessage' => 'Votre image fait {{size}} {{suffix}}. La limite est de {{limit}} {{suffix}}' //Message d'avertisemment au sujet de la taille et du type de fichier ne correspondant pas aux limies fixées
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'new_user' => true // Par défaut, le formulaire sera utilisé pour créer un nouvel utilisateur
        ]);
    }
}
