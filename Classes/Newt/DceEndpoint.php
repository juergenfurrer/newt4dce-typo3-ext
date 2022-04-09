<?php

declare(strict_types=1);

namespace Infonique\Newt4Dce\Newt;

use Infonique\Newt\NewtApi\EndpointInterface;
use Infonique\Newt\NewtApi\Field;
use Infonique\Newt\NewtApi\FieldItem;
use Infonique\Newt\NewtApi\FieldType;
use Infonique\Newt\NewtApi\FieldValidation;
use Infonique\Newt\NewtApi\Item;
use Infonique\Newt\NewtApi\MethodCreateModel;
use Infonique\Newt\NewtApi\MethodDeleteModel;
use Infonique\Newt\NewtApi\MethodListModel;
use Infonique\Newt\NewtApi\MethodReadModel;
use Infonique\Newt\NewtApi\MethodType;
use Infonique\Newt\NewtApi\MethodUpdateModel;
use T3\Dce\Domain\Model\Dce;
use T3\Dce\Domain\Model\DceField;
use T3\Dce\Domain\Repository\DceFieldRepository;
use T3\Dce\Domain\Repository\DceRepository;
use T3\Dce\Utility\TypoScript;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class DceEndpoint implements EndpointInterface
{
    private array $settings;
    private array $settingsDce;

    // TODO: From where should we take this?
    private string $pluginName = 'test';

    private DceRepository $dceRepository;
    private DceFieldRepository $dceFieldRepository;
    private TypoScript $typoScriptUtility;
    private PersistenceManager $persistenceManager;

    public function __construct(ConfigurationManager $configurationManager, PersistenceManager $persistenceManager)
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->dceRepository = $objectManager->get(DceRepository::class);
        $this->dceFieldRepository = $objectManager->get(DceFieldRepository::class);
        $this->typoScriptUtility = $objectManager->get(TypoScript::class);
        $this->persistenceManager = $persistenceManager;

        $conf = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $this->settings = $conf['plugin.']['tx_newt4dce.']['settings.'] ?? [];

        try {
            /** @var ExtensionConfiguration */
            $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
            $this->settingsDce = $extensionConfiguration->get('dce');
        } catch (\Exception $exception) {
            // do nothing
        }
    }

    /**
     * Creats a new news-item
     *
     * @param MethodCreateModel $model
     * @return Item
     */
    public function methodCreate(MethodCreateModel $model): Item
    {
        $item = new Item();
        if (!$model || count($model->getParams()) == 0) {
            return $item;
        }

        $params = $model->getParams();

        return $item;
    }

    /**
     * Read a news-item by readId
     *
     * @param MethodReadModel $model
     * @return Item
     */
    public function methodRead(MethodReadModel $model): Item
    {
        $id = $model->getReadId();

        $item = new Item();
        $item->setId($id);

        return $item;
    }

    /**
     * Update the news-entry with the new data
     *
     * @param MethodUpdateModel $model
     * @return Item
     */
    public function methodUpdate(MethodUpdateModel $model): Item
    {
        $item = new Item();
        if (!$model || count($model->getParams()) == 0) {
            return $item;
        }

        $params = $model->getParams();
        $id = intval($model->getUpdateId());

        return $item;
    }

    /**
     * Deletes a news-item by deleteId
     *
     * @param MethodDeleteModel $model
     * @return boolean
     */
    public function methodDelete(MethodDeleteModel $model): bool
    {
        try {
            $id = intval($model->getDeleteId());
            // $news = $this->newsRepository->findByUid($id);
            // $this->newsRepository->remove($news);

            // persist the item
            $this->persistenceManager->persistAll();
        } catch (\Throwable $th) {
            return false;
        }

        return true;
    }

    /**
     * Returns a list of list-items
     *
     * @param MethodListModel $model
     * @return array
     */
    public function methodList(MethodListModel $model): array
    {
        $items = [];
        return $items;
    }

    /**
     * Returns the available methods
     *
     * @return array
     */
    public function getAvailableMethodTypes(): array
    {
        return [
            MethodType::CREATE,
            MethodType::READ,
            MethodType::UPDATE,
            MethodType::DELETE,
            MethodType::LIST,
        ];
    }

    /**
     * Returns the available fields
     *
     * @return array
     */
    public function getAvailableFields(): array
    {
        $ret = [];
        $uid = $this->dceRepository::extractUidFromCTypeOrIdentifier('dce_' . $this->pluginName);

        /** @var Dce $dce */
        $dce = $this->dceRepository->findByUid($uid);
        if ($dce) {
            /** @var DceField $field */
            foreach ($dce->getFields() as $dceField) {
                $field = $this->getNewtFieldFromDceField($dceField);
                if ($field) {
                    $ret[] = $field;
                }
            }
        }

        return $ret;
    }

    /**
     * Returns a Newt-Field based on the Dce-Field
     *
     * @param DceField $dceField
     * @return Field|null
     */
    private function getNewtFieldFromDceField(DceField $dceField): ?Field
    {
        if ($dceField->getType() == DceField::TYPE_ELEMENT) {
            $variabl = $dceField->getVariable();
            $label = $dceField->getTitle();
            $type = null;
            $evals = [];
            $default = null;
            $configArray = $dceField->getConfigurationAsArray();
            $config = isset($configArray['config']) ? $configArray['config'] : [];
            if (isset($config['type'])) {
                $type = $config['type'];
            }
            if (isset($config['eval'])) {
                $evals = GeneralUtility::trimExplode(',', $config['eval'], true);
            }
            if (isset($config['default'])) {
                $default = $config['default'];
            }

            $fieldType = FieldType::UNKNOWN;
            switch ($type) {
                case 'input':
                    $fieldType = FieldType::TEXT;
                    break;
                case 'text':
                    if (isset($config['richtextConfiguration'])) {
                        $fieldType = FieldType::HTML;
                    } else {
                        $fieldType = FieldType::TEXTAREA;
                    }
                    break;
                case 'select':
                    $fieldType = FieldType::SELECT;
                    break;
                case 'check':
                    $fieldType = FieldType::CHECKBOX;
                    break;
                default:
                    echo $type;
                    exit;
            }

            if ($fieldType != FieldType::UNKNOWN) {
                $newtField = new Field();
                $newtField->setType($fieldType);
                $newtField->setLabel($label);
                $newtField->setName($variabl);

                if ($default != null) {
                    $newtField->setValue($default);
                }

                if ($fieldType == FieldType::SELECT) {
                    // Add items
                    if (isset($config['items'])) {
                        foreach ($config['items'] as $item) {
                            $fieldItem = new FieldItem($item[1], $item[0]);
                            $newtField->addItem($fieldItem);
                        }
                    }

                    if (isset($config['foreign_table'])) {
                        // TODO: what about foreign_table?
                        // Not supported at the moment...
                    }

                    if (isset($config['maxitems']) && intval($config['maxitems']) > 0) {
                        $newtField->setCount(intval($config['maxitems']));
                    }
                    if (isset($config['minitems']) && intval($config['minitems']) > 0) {
                        $evals[] = 'required';
                    }
                }

                if (in_array('required', $evals)) {
                    $validation = new FieldValidation();
                    $validation->setRequired(true);
                    $newtField->setValidation($validation);
                }

                return $newtField;
            }
        }

        return null;
    }

    /**
     * Return the settings of this plugin
     */
    private function getSetting(string $key, string $type)
    {
        if ($this->settings && isset($this->settings[$type . "."]) && isset($this->settings[$type . "."][$key])) {
            return $this->settings[$type . "."][$key];
        }
        return '';
    }

    /**
     * Return the settings of EXT:dce
     */
    private function getDceSetting(string $key)
    {
        if ($this->settings && isset($this->settingsDce[$key])) {
            return $this->settingsDce[$key];
        }
        return '';
    }
}
