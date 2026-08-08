<?php

namespace NCB\Component\Gda\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;


class ToolsHelper
{
  /**
   * Table de translittération pour les caractères spéciaux
   * @var array
   */
  private static array $transliterationTable = [
    'ï' => 'i',    'î' => 'i',    'ì' => 'i',
    'í' => 'i',    'ö' => 'o',    'ô' => 'o',
    'ò' => 'o',    'ó' => 'o',
    'ü' => 'u',    'û' => 'u',    'ù' => 'u',
    'ú' => 'u',    'ä' => 'a',    'â' => 'a',
    'à' => 'a',    'á' => 'a',    'é' => 'e',
    'è' => 'e',    'ê' => 'e',    'ë' => 'e',
    'ç' => 'c',    'æ' => 'ae',    'œ' => 'oe',
    'Ï' => 'I',    'Î' => 'I',    'Ì' => 'I',
    'Í' => 'I',    'Ö' => 'O',    'Ô' => 'O',
    'Ò' => 'O',    'Ó' => 'O',    'Ü' => 'U',
    'Û' => 'U',    'Ù' => 'U',    'Ú' => 'U',
    'Ä' => 'A',    'Â' => 'A',    'À' => 'A',
    'Á' => 'A',    'É' => 'E',    'È' => 'E',
    'Ê' => 'E',    'Ë' => 'E',    'Ç' => 'C'
  ];

  // Convert Date 04/12/1971 to 1971-12-04 for Database
  /**
   * Convertit une date du format français (dd/mm/yyyy) au format SQL (yyyy-mm-dd).
   * Retourne null si la date est invalide ou si le format n'est pas respecté.
    * @param string $date Date au format dd/mm/yyyy
    * @return string|null Date au format yyyy-mm-dd ou null si invalide
   */
  public static function to_sqldate(string $date): ?string
  {
    $retour = $date;
    $tab = explode("/", $date);
    if (isset($tab[2])) {
      $retour = ($tab[2] . "-" . $tab[1] . "-" . $tab[0]);
    }
    if (!self::isValidSqlDate($retour)) {
      return null;
    } else {
      return $retour;
    }
  }

  /**
   * Convertit un numéro de téléphone en supprimant les espaces (ex: "06 12 34 56 78" devient "0612345678").
   * Retourne une chaîne vide si le numéro est nul ou vide.
   * @param string|null $tel Numéro de téléphone à convertir
   * @return string Numéro de téléphone sans espaces
   */
  public static function to_sqltel(string $tel): string
  {
    return (str_replace(" ", "", $tel));
  }


  // Convert Date 1971-12-04 to 04/12/1971 for Form
  /**
   * Convertit une date du format SQL (yyyy-mm-dd) au format français (dd/mm/yyyy).
   * Retourne null si la date est invalide, nulle ou égale à '0000-00-00'.
   * @param string|null $date Date au format yyyy-mm-dd
   * @return string|null Date au format dd/mm/yyyy ou null si invalide
   */
  public static function from_sqldate(string|null $date): ?string
  {
    if (is_null($date) || $date === '0000-00-00') {
      return "";
    }
    $tab = explode("-", $date);
    if (isset($tab[2])) {
      return ($tab[2] . "/" . $tab[1] . "/" . $tab[0]);
    }
    return ($date);
  }

  /**
   *  <summary> Extrait la première date au format dd/mm/yyyy d'une chaîne de caractères. Si l'option $iso est à true, convertit la date extraite en format SQL (yyyy-mm-dd) avant de la retourner. Retourne null si aucune date valide n'est trouvée.</summary>
   * @param string $str Chaîne de caractères à analyser
   * @param bool $iso Indique si la date extraite doit être convertie en format SQL
   * @return string|null Date extraite ou null si aucune date valide n'est trouvée
   */
  public static function extractDate(string $str, bool $iso = false): ?string
  {
    // Expression régulière pour extraire la date au format "dd/mm/yyyy"
    $pattern = '/\b\d{2}\/\d{2}\/\d{4}\b/';
    preg_match($pattern, $str, $matches);
    // Retourner la date si trouvée, sinon retourner une chaîne vide
    if ($iso && isset($matches[0])) {
      return self::to_sqldate($matches[0]);
    }
    return isset($matches[0]) ? $matches[0] : null;
  }

/**
   * Génère une chaîne aléatoire de caractères (lettres et chiffres) d'une longueur spécifiée.
   * @param int $longueur Longueur de la chaîne aléatoire (par défaut 12)
   * @return string Chaîne aléatoire générée
   */
  public static function getUniqStr(int $longueur = 12): string
  {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $longueurMax = strlen($caracteres);
    $chaineAleatoire = '';
    for ($i = 0; $i < $longueur; $i++) {
      $chaineAleatoire .= $caracteres[rand(0, $longueurMax - 1)];
    }
    return $chaineAleatoire;
  }


  /**
   * Formate un numéro de téléphone en ajoutant des espaces tous les deux chiffres (ex: "0612345678" devient "06 12 34 56 78").
   * Retourne une chaîne vide si le numéro est nul ou vide.
   * @param string|null $tel Numéro de téléphone à formater
   * @return string Numéro de téléphone formaté
   */
  public static function ShowTel(string|null $tel): string
  {
    if (is_null($tel) || empty($tel)) {
      return '';
    }
    return  $tel[0] . $tel[1] . ' ' . $tel[2] . $tel[3] . ' ' . $tel[4] . $tel[5] . ' ' . $tel[6] . $tel[7] . ' ' . $tel[8] . $tel[9];
  }

  /**<summary> Remove accents and convert to uppercase </summary>
   * @param string $text Chaine de caratères qui doit être modifiée
   * @return string Chaine de caractères modifiée
   */
  public static function removeAccentsAndUppercase(string $text): string
  {
    // Remplacer les caractères spéciaux
    $text = strtr($text, self::$transliterationTable);
    // Supprimer les accents restants
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    // Mettre en majuscules
    return strtoupper($text);
  }

  /**
   * **<summary> Supprimer les accents, convertir en première lettre minuscule et majuscule </summary>
   * 
   * @param string $text Chaine de caratères qui doit être modifiée
   * 
   * @return string
   * 
   */
  public static function removeAccentsAndUppercasefirst(string $text): string
  {
    // Remplacer les caractères spéciaux
    $text = strtr($text, self::$transliterationTable);
    // Supprimer les accents restants
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    // Mettre en minuscules
    $text = strtolower($text);
    // Mettre en majuscule la première lettre
    return ucfirst($text);
  }

  /**
   * **<summary> Supprimer les accents, convertir en première lettre minuscule et majuscule </summary>
   * 
   * @param string $text Chaine de caratères qui doit être modifiée
   * 
   * @return string
   * 
   */
  public static function removeAccents(string $text): string
  {
    // Remplacer les caractères spéciaux
    $text = strtr($text, self::$transliterationTable);
    // Supprimer les accents restants
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    return ($text);
  }




  public static function debug(string $message, $data): void
  {
    if (JDEBUG) {
      echo '<script type="text/javascript">';
      echo 'console.debug ("' . $message . '",' . json_encode($data) . ')';
      echo '</script>';
    }
  }


  public static function message(string $message): void
  {
    if (JDEBUG) {
      echo '<script type="text/javascript">';
      echo 'console.debug ("' . $message . '")';
      echo '</script>';
    }
  }

  /**<summary> Validate if date is in YYYY-MM-DD format and is a valid date </summary> */
  public static function isValidSqlDate(string $date): bool
  {
    // Vérifier le format YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return false;
    }

    // Extraire année, mois, jour
    $parts = explode('-', $date);
    $year = (int)$parts[0];
    $month = (int)$parts[1];
    $day = (int)$parts[2];

    // Vérifier que la date existe réellement
    return checkdate($month, $day, $year);
  }

  /**
   * Convertit une date ISO 8601 (ex: 2024-05-15T10:00:00+02:00) en dd/mm/yyyy
   * Retourne une chaîne vide si la date est invalide ou nulle.
   */
  public static function isoToFrDate(?string $isoString): string
  {
    if (empty($isoString)) {
      return '';
    }
    try {
      $d = new \DateTime($isoString);
      return $d->format('d/m/Y');
    } catch (\Exception $e) {
      return '';
    }
  }

  /**
   * Convertit une date ISO 8601 (ex: 2026-03-23T20:46:32.1873203+01:00) en UTC
   * puis la formate selon le format fourni (par défaut : jj/mm/AAAA hh:MM).
   *
   * @param  string|null $isoString  Date ISO 8601 en entrée
   * @param  string      $format     Format de sortie PHP (par défaut 'd/m/Y H:i')
   * @return string                  Date formatée ou chaîne vide si invalide
   */
  public static function isoToUtcFormatted(?string $isoString, string $format = 'd/m/Y H\hi'): string
  {
    if (empty($isoString)) {
      return '';
    }
    try {
      $d = new \DateTime($isoString);
      $d->setTimezone(new \DateTimeZone('Europe/Paris'));
      return $d->format($format);
    } catch (\Exception $e) {
      return '';
    }
  }

  /** <summary> Retourne la date et l'heure actuelles au format SQL (YYYY-MM-DD HH:MM:SS) ou selon le format fourni </summary> 
   * @param string $format Format de date PHP (par défaut 'Y-m-d H:i:s')
   * @return string Date et heure actuelles formatées
  */
  public static function now(string $format = 'Y-m-d H:i:s'): string
  {
    $d = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
    return $d->format($format);
  }

  /***
   * <summary> Génère un UID aléatoire de 16 caractères avec des tirets tous les 4 caractères (ex: ABCD-EFGH-IJKL-MNOP) </summary>
   * @return string
   */
  public static function generatorUID():string
  {
    $longueur = 16;
    $caracteres = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $longueurMax = strlen($caracteres);
    $chaineAleatoire = '';
    for ($i = 0; $i < $longueur; $i++) {
      $chaineAleatoire .= $caracteres[rand(0, $longueurMax - 1)];
      // Ajouter un tiret après chaque groupe de 4 caractères (sauf à la fin)
      if ((($i + 1) % 4) === 0 && $i < $longueur - 1) {
        $chaineAleatoire .= '-';
      }
    }
    return $chaineAleatoire;
  }
}
