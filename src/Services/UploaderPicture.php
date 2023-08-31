<?php

namespace App\Services;

use Symfony\Component\Filesystem\Filesystem;

class UploaderPicture
{
    public function __construct(private $profileFolder, private $profilePublicFolder, private Filesystem $fs) // Appel des variables/arguments précisés dans services.yaml
    {
        // paramètre -> profile.folder: "%kernel.project_dir%/public/profiles" => $profileFolder = "%profile.folder%"
        // paramètre -> profile.folder.public_path: "profiles" => $profilePublicFolder = "%profile.folder.public_path%"
    }

    public function upload($picture, $oldPicture = null)
    {
        $folder = $this->profileFolder;
        $extension = $picture->guessExtension() ?? 'bin'; // Devine l'extension du fichier, sinon on le nomme 'bin'
        $filename = bin2hex(random_bytes(20)) . '.' . $extension; // Renommage du fichier image en hexadecimal de bytes aléatoires
        $picture->move($this->profileFolder, $filename); // Déplacement du fichier vers le dossier cible écrit par la paramètre du fichier servces.yaml
        if ($oldPicture) {
            $this->fs->remove($folder . '/' . pathinfo($oldPicture, PATHINFO_BASENAME)); // PATHINFO_BASENAME -> pour récupérér le fichier et son extension
        }

        return $this->profilePublicFolder . '/' . $filename; // On retourne le chemin de l'image 
    }
}
