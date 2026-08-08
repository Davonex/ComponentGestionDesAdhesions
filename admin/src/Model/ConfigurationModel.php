<?php

namespace NCB\Component\Gda\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use NCB\Component\Gda\Site\Helper\ConfHelper;
use NCB\Component\Gda\Site\Helper\CryptoHelper;

class ConfigurationModel extends FormModel
{
    private const LOG_TAIL_LINES = 300;

    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm(
            'com_gdadhesions.configuration',
            'configuration',
            [
                'control' => 'jform',
                'load_data' => $loadData,
            ]
        );
    }

    protected function loadFormData(): array
    {
        return [
            'helloasso_client_id' => (string) (ConfHelper::getValue('HelloAssoClientId') ?: ''),
            'helloasso_client_secret' => '',
            'helloasso_base_url' => (string) (ConfHelper::getValue('HelloAssoBaseUrl') ?: 'https://api.helloasso.com'),
            'helloasso_organization_slug' => (string) (ConfHelper::getValue('HelloAssoOrganizationSlug') ?: ''),
            'devmailoverride' => (string) (ConfHelper::getValue('DevMailOverride') ?: ''),
        ];
    }

    public function isSecretConfigured(): bool
    {
        return (string) (ConfHelper::getValue('HelloAssoClientSecret') ?: '') !== '';
    }

    public function getReleaseNotes(): string
    {
        $path = JPATH_ADMINISTRATOR . '/components/com_gdadhesions/RELEASESNOTES.md';

        if (!is_file($path)) {
            return '';
        }

        return (string) file_get_contents($path);
    }

    public function getLogContent(): string
    {
        $path = JPATH_ADMINISTRATOR . '/logs/com_gdadhesions.php';

        if (!is_file($path)) {
            return '';
        }

        $lines = explode("\n", $this->tailFile($path, self::LOG_TAIL_LINES));

        // Retire les lignes d'en-tete Joomla (protection contre l'acces direct, metadonnees).
        $lines = array_filter($lines, static fn (string $line): bool => !str_starts_with(ltrim($line), '#'));

        return trim(implode("\n", $lines));
    }

    /**
     * Lit les N dernieres lignes d'un fichier sans le charger entierement en memoire.
     */
    private function tailFile(string $path, int $maxLines): string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        $chunkSize = 4096;
        $buffer = '';

        fseek($handle, 0, SEEK_END);
        $readPos = ftell($handle);

        while ($readPos > 0 && substr_count($buffer, "\n") <= $maxLines) {
            $seekSize = min($chunkSize, $readPos);
            $readPos -= $seekSize;
            fseek($handle, $readPos);
            $buffer = fread($handle, $seekSize) . $buffer;
        }

        fclose($handle);

        return implode("\n", array_slice(explode("\n", $buffer), -$maxLines));
    }

    public function saveConfiguration(array $data): bool
    {
        $db = $this->getDatabase();

        $clientId = trim((string) ($data['helloasso_client_id'] ?? ''));
        $clientSecret = (string) ($data['helloasso_client_secret'] ?? '');
        $baseUrl = trim((string) ($data['helloasso_base_url'] ?? ''));
        $organizationSlug = trim((string) ($data['helloasso_organization_slug'] ?? ''));
        $devMailOverride = trim((string) ($data['devmailoverride'] ?? ''));

        if ($clientId === '' || $baseUrl === '' || $organizationSlug === '') {
            $this->setError(Text::_('COM_GDA_HELLOASSO_VALIDATION_ERROR'));
            return false;
        }

        $valuesToSave = [
            'HelloAssoClientId' => $clientId,
            'HelloAssoBaseUrl' => $baseUrl,
            'HelloAssoOrganizationSlug' => $organizationSlug,
            'DevMailOverride' => $devMailOverride,
        ];

        // Secret vide => on conserve la valeur deja stockee.
        if (trim($clientSecret) !== '') {
            $valuesToSave['HelloAssoClientSecret'] = CryptoHelper::encrypt(trim($clientSecret));
        }

        try {
            foreach ($valuesToSave as $key => $value) {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__gda_conf'))
                    ->where($db->quoteName('key') . ' = :key')
                    ->bind(':key', $key);

                $db->setQuery($query);
                $id = (int) $db->loadResult();

                if ($id > 0) {
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__gda_conf'))
                        ->set($db->quoteName('value') . ' = :value')
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':value', $value)
                        ->bind(':id', $id);
                } else {
                    $columns = [$db->quoteName('key'), $db->quoteName('value')];
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__gda_conf'))
                        ->columns($columns)
                        ->values(':key, :value')
                        ->bind(':key', $key)
                        ->bind(':value', $value);
                }

                $db->setQuery($query);
                $db->execute();
            }
        } catch (\RuntimeException $e) {
            $this->setError($e->getMessage());
            return false;
        }

        return true;
    }
}
