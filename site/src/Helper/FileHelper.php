<?php

// namespace My\Module\Hello\Site\Helper;
namespace NCB\Component\Gda\Site\Helper;


\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use NCB\Component\Gda\Site\Helper\ToolsHelper;
use NCB\Component\Gda\Site\Helper\ConfHelper;



class FileHelper
{
	// 
	public static $validIMGExts = array(
		"image/png",
		'image/jpg',
		'image/jpeg',
		'image/webp'
	);
	/**
	 * Moves an uploaded file to a destination folder
	 *
	 * @param   string   $TagertFile         The tagert name of file
	 * @param   string   $FolderFile         The path  to move the uploaded file to
	 * @param   array    $ObjUpload          Object provide by upload HHTP
	 *
	 * @return  boolean  True on success
	 *
	 * @since   1.0
	 * @throws  Exception
	 */

	public static function UploadFile(string $TagertFile, string $FolderFile, $ObjUpload, ?int $maxWidth = null, ?int $maxHeight = null, int $quality = 85)
	{

		$baseRoot = Path::clean(JPATH_ROOT);
		$targetDir = Path::clean($baseRoot . '/' . trim($FolderFile, '/\\'));

		if (!Folder::exists($baseRoot)) {
			throw new \RuntimeException('Racine Joomla introuvable: ' . $baseRoot);
		}


		// Sécurité: rester sous JPATH_ROOT
		$rootNorm   = rtrim(str_replace('\\', '/', strtolower($baseRoot)), '/');
		$targetNorm = str_replace('\\', '/', strtolower($targetDir));

		if (
			!str_starts_with($targetNorm, $rootNorm . '/')
			&& $targetNorm !== $rootNorm
		) {
			throw new \RuntimeException('Chemin hors racine autorisée: ' . $targetDir);
		}

		// Création auto du dossier si absent
		if (!Folder::exists($targetDir) && !Folder::create($targetDir)) {
			throw new \RuntimeException('Impossible de créer le répertoire cible: ' . $targetDir);
		}



		// if (\is_null($FolderFile) or \is_dir(JPATH_ROOT . $FolderFile) === false) {
		// 	throw new \Exception('Repertoire cible inexistant');
		// }

		if (!isset($ObjUpload['type']) || !\in_array($ObjUpload['type'], self::$validIMGExts, true)) {
			throw new \RuntimeException('Format invalide', 10);
		}

		if (is_null($TagertFile) or empty($TagertFile)) {
			// Nom du fichier cible Par defaut
			$FileExtension = File::getExt($ObjUpload['name']);
			$TagertFile = ToolsHelper::getUniqStr() . '.' . $FileExtension;
		}

		// $Source = $ObjUpload['tmp_name'];
		$Source = (string) ($ObjUpload['tmp_name'] ?? '');
		// $Target = JPATH_ROOT . $FolderFile . $TagertFile;
		$Target = Path::clean($targetDir . '/' . $TagertFile);

		if ($Source === '' || !is_uploaded_file($Source)) {
			throw new \RuntimeException('Fichier temporaire upload invalide.');
		}

		if (! File::upload($Source, $Target)) {
			throw new \Exception('Erreur upload vers: ' . JPATH_ROOT . $Source);
		}

		// If resizing requested and file is an accepted image, attempt to resize
		$ext = strtolower(File::getExt($TagertFile));
		if (($maxWidth !== null || $maxHeight !== null) && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
			$width = $maxWidth ?? 0;
			$height = $maxHeight ?? 0;
			// Only call reTaillerImage when at least one dimension is specified
			if ($width > 0 || $height > 0) {
				// If one dimension is zero, keep aspect ratio by calculating from source
				// Use getimagesize to derive missing dimension
				list($origW, $origH) = getimagesize($Target);
				if ($width === 0 && $height > 0) {
					$width = (int) round($origW * ($height / $origH));
				}
				if ($height === 0 && $width > 0) {
					$height = (int) round($origH * ($width / $origW));
				}

				// Perform resize (replaces file at $Target)
				try {
					self::reTaillerImage($Target, $Target, $width, $height, $quality);
				} catch (\Throwable $e) {
					// If resizing fails, keep original uploaded file; do not break upload
				}
			}
		}

		return $TagertFile;
	}

	/**
	 * Delete a file stored in a configured path.
	 *
	 * @param string|null $filename The filename to delete (not URL).
	 * @param string $pathConf Configuration key that holds the relative folder path.
	 * @return bool True if file deleted or not present, false on failure.
	 */
	public static function deleteFile(?string $filename, string $pathConf): bool
	{
		if (empty($filename)) {
			return true;
		}

		$path = (string) ConfHelper::getValue($pathConf);
		$filePath = Path::clean(JPATH_ROOT . '/' . ltrim($path, '/\\') . '/' . ltrim($filename, '/\\'));

		if (!File::exists($filePath)) {
			return true;
		}

		try {
			return File::delete($filePath);
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Returns the source URL for a user's profile photo.
	 *
	 * Cette méthode construit le chemin d'accès au fichier de la photo en utilisant le "ProfilPhotoPath" de la configuration 
	 * et le nom de fichier de la photo fourni.
	 * nom de fichier de la photo fournie. Si la photo n'existe pas ou est nulle, elle revient au chemin "DefaultProfilPhoto" de la configuration.
	 * de la configuration. Si la photo par défaut est également manquante, une exception est levée.
	 * L'URL renvoyée comprend une chaîne de requête unique pour éviter la mise en cache.

	 *
	 * @param string|null $photo The filename of the user's profile photo.
	 * @param bool $cacheBusting Ajout ou non d’un paramètre de requête de violation du cache.
	 * @return string The absolute URL to the profile photo, with a cache-busting query parameter if enabled.
	 * @throws \Exception If neither the specified nor the default profile photo file exists.
	 */
	public static function getImageSrc(?string $image, string $pathConf, string $defaultImg = "", bool $cacheBusting = true): ?string
	{
		/** @var \Joomla\CMS\Application\SiteApplication $app */
		$app = Factory::getApplication();
		$path = (string) ConfHelper::getValue($pathConf);
		$imageSource  = (string) $path . $image;

		if (empty($image)  or \is_file(JPATH_ROOT . $imageSource) === false) {
			$defaultValue = ConfHelper::getValue($defaultImg);
			if (!is_null($defaultImg) && $defaultValue !== false) {
				$imageSource = ConfHelper::getValue('ImagesPath') . $defaultValue;
			} else {
				$imageSource = $defaultValue ?: "";
			}
		}
		if (\is_file(JPATH_ROOT . $imageSource) === false) {
			//throw new \Exception('Fichier inexistant : ' .  $imageSource);
			//$app->enqueueMessage('Erreur: Fichier inexistant : ' .  $imageSource, 'error');
			return "";
		}
		
		if (!$cacheBusting) {
			return Uri::root() . $imageSource;
		}
		return Uri::root() . $imageSource . "?id=" . ToolsHelper::getUniqStr(4);
	}

	/**
	 * URL du logo HelloAsso hébergé localement (media/com_gdadhesions/images/), pour ne plus
	 * dépendre de la disponibilité de https://api.helloasso.com/v5/img/logo-ha.svg à l'affichage.
	 *
	 * @return string
	 */
	public static function getHelloAssoLogoSrc(): string
	{
		return Uri::root() . 'media/com_gdadhesions/images/logo-helloasso.svg';
	}



/**
 * Redimensionne une image en conservant le ratio et en coupant les parties qui dépassent.
 * @param string $Source Chemin de l'image source
 * @param string $Destination Chemin de l'image redimensionnée
 * @param int $width_final Largeur finale de l'image redimensionnée
 * @param int $height_final Hauteur finale de l'image redimensionnée
 * @param int $Quality Qualité de l'image redimensionnée (0-100)
 */

	private static function  reTaillerImage($Source, $Destination, $width_final, $height_final, $Quality)
	{
		$info = getimagesize($Source);
		if ($info === false) {
			return false;
		}
		list($width_orig, $height_orig) = $info;
		$mime = $info['mime'] ?? '';

		// Create source image resource depending on mime
		switch ($mime) {
			case 'image/jpeg':
			case 'image/jpg':
				$img_src_resource = imagecreatefromjpeg($Source);
				$outputType = 'jpeg';
				break;
			case 'image/png':
				$img_src_resource = imagecreatefrompng($Source);
				$outputType = 'png';
				break;
			case 'image/webp':
				$img_src_resource = imagecreatefromwebp($Source);
				$outputType = 'webp';
				break;
			default:
				// Unsupported type
				return false;
		}

		// Cacul des nouvelles dimensions
		$ratio_orig = $width_orig / $height_orig;
		$ratio_final = $width_final / $height_final;

		// if ($ratio_final > $ratio_orig) {
		// $width_final = $height_final*$ratio_orig;
		// } else {
		// $height_final = $width_final/$ratio_orig;
		// }
		// Redimensionnement
		// $img_dst_resource = imagecreatetruecolor($width_final,$height_final);
		// imagecopyresampled($img_dst_resource, $img_src_resource, 0, 0, 0, 0, $width_final, $height_final, $width_orig, $height_orig);
		// imagejpeg( $img_dst_resource, $Destination,$Quality);

		//Cas n°1 : L'image est plus petite en hauteur et en largeur : on la recopie telle quelle

		if (($width_orig <= $width_final) and ($height_orig <= $height_final)) {
			// $this->File['msg'] =  Text::_('Cas n°1');
			$img_dst_resource = imagecreatetruecolor($width_orig, $height_orig);
			// Preserve transparency for PNG/WebP
			if ($outputType !== 'jpeg') {
				imagealphablending($img_dst_resource, false);
				imagesavealpha($img_dst_resource, true);
			}
			imagecopyresampled($img_dst_resource, $img_src_resource, 0, 0, 0, 0, $width_orig, $height_orig, $width_orig, $height_orig);
			switch ($outputType) {
				case 'jpeg':
					imagejpeg($img_dst_resource, $Destination, $Quality);
					break;
				case 'png':
					// Convert quality 0-100 to PNG compression 0-9 (invert)
					$pngLevel = (int) round((100 - $Quality) / 11.111111);
					imagepng($img_dst_resource, $Destination, $pngLevel);
					break;
				case 'webp':
					imagewebp($img_dst_resource, $Destination, $Quality);
					break;
			}
		}

		//Cas n°2 : L'image dépasse en hauteur mais est trop petite en largeur :
		// On coupe ce qui dépasse en hauteur

		if (($width_orig <= $width_final) and ($height_orig > $height_final)) {
			// $this->File['msg'] =  JText::_('Cas n°2');
			$img_dst_resource = imagecreatetruecolor($width_orig, $height_final);
			if ($outputType !== 'jpeg') {
				imagealphablending($img_dst_resource, false);
				imagesavealpha($img_dst_resource, true);
			}
			imagecopyresampled($img_dst_resource, $img_src_resource, 0, 0, 0, 0, $width_orig, $height_final, $width_orig, $height_final);
			switch ($outputType) {
				case 'jpeg':
					imagejpeg($img_dst_resource, $Destination, $Quality);
					break;
				case 'png':
					$pngLevel = (int) round((100 - $Quality) / 11.111111);
					imagepng($img_dst_resource, $Destination, $pngLevel);
					break;
				case 'webp':
					imagewebp($img_dst_resource, $Destination, $Quality);
					break;
			}
		}

		//Cas n°3 : L'image dépasse en largeur mais est trop petite en hauteur :
		// On coupe ce qui dépasse en largeur

		if (($width_orig > $width_final) and ($height_orig <= $height_final)) {
			// $this->File['msg'] =  JText::_('Cas n°3');
			$img_dst_resource = imagecreatetruecolor($width_final, $height_orig);
			if ($outputType !== 'jpeg') {
				imagealphablending($img_dst_resource, false);
				imagesavealpha($img_dst_resource, true);
			}
			imagecopyresampled($img_dst_resource, $img_src_resource, 0, 0, 0, 0, $width_final, $height_orig, $width_final, $height_orig);
			switch ($outputType) {
				case 'jpeg':
					imagejpeg($img_dst_resource, $Destination, $Quality);
					break;
				case 'png':
					$pngLevel = (int) round((100 - $Quality) / 11.111111);
					imagepng($img_dst_resource, $Destination, $pngLevel);
					break;
				case 'webp':
					imagewebp($img_dst_resource, $Destination, $Quality);
					break;
			}
		}

		//Cas n°4 : L'image dépasse en largeur et en hauteur et est proportionnement trop grande en largeur
		// On redimentionne pour avoir la bonne hauteur et on coupe ce qui dépasse en largeur

		if (($width_orig > $width_final) and ($height_orig > $height_final) and (($ratio_orig) > ($ratio_final))) {

			$img_dst_resource = imagecreatetruecolor($width_final, $height_final);
			if ($outputType !== 'jpeg') {
				imagealphablending($img_dst_resource, false);
				imagesavealpha($img_dst_resource, true);
			}
			$srcX = (int) round(($width_orig - ($height_orig * $ratio_final)) / 2);
			$srcW = (int) round($height_orig * $ratio_final);
			if (!imagecopyresampled($img_dst_resource, $img_src_resource, 0, 0, $srcX, 0, $width_final, $height_final, $srcW, $height_orig)) {
				return false;
			}
			switch ($outputType) {
				case 'jpeg':
					imagejpeg($img_dst_resource, $Destination, $Quality);
					break;
				case 'png':
					$pngLevel = (int) round((100 - $Quality) / 11.111111);
					imagepng($img_dst_resource, $Destination, $pngLevel);
					break;
				case 'webp':
					imagewebp($img_dst_resource, $Destination, $Quality);
					break;
			}

			// $this->File['msg'] =  JText::_('OK : Cas n°4');
			return true;
		}

		//Cas n°5 : L'image dépasse en largeur et en hauteur et est proportionnement trop grande en hauteur
		// On redimentionne pour avoir la bonne largeur et on coupe ce qui dépasse en hauteur

		if (($width_orig > $width_final) and ($height_orig > $height_final) and (($ratio_orig) <= ($ratio_final))) {
			// $this->File['msg'] =  JText::_('Cas n°5');
			$img_dst_resource = imagecreatetruecolor($width_final, $height_final);
			if ($outputType !== 'jpeg') {
				imagealphablending($img_dst_resource, false);
				imagesavealpha($img_dst_resource, true);
			}
			imagecopyresampled($img_dst_resource, $img_src_resource, 0, 0, 0, 0, $width_final, $height_final, $width_orig, $width_orig * $height_final / $width_final);
			switch ($outputType) {
				case 'jpeg':
					imagejpeg($img_dst_resource, $Destination, $Quality);
					break;
				case 'png':
					$pngLevel = (int) round((100 - $Quality) / 11.111111);
					imagepng($img_dst_resource, $Destination, $pngLevel);
					break;
				case 'webp':
					imagewebp($img_dst_resource, $Destination, $Quality);
					break;
			}
		}
	}
}
