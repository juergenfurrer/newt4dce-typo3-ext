<?php

declare(strict_types=1);

namespace Infonique\Newt4Dce\Newt;

use Infonique\Newt\NewtApi\EndpointInterface;
use Infonique\Newt\NewtApi\Field;
use Infonique\Newt\NewtApi\FieldItem;
use Infonique\Newt\NewtApi\FieldType;
use Infonique\Newt\NewtApi\FieldValidation;
use Infonique\Newt\NewtApi\Item;
use Infonique\Newt\NewtApi\ItemValue;
use Infonique\Newt\NewtApi\MethodCreateModel;
use Infonique\Newt\NewtApi\MethodDeleteModel;
use Infonique\Newt\NewtApi\MethodListModel;
use Infonique\Newt\NewtApi\MethodReadModel;
use Infonique\Newt\NewtApi\MethodType;
use Infonique\Newt\NewtApi\MethodUpdateModel;
use T3\Dce\Components\ContentElementGenerator\InputDatabase;
use T3\Dce\Domain\Model\Dce;
use T3\Dce\Domain\Model\DceField;
use T3\Dce\Domain\Repository\DceFieldRepository;
use T3\Dce\Domain\Repository\DceRepository;
use T3\Dce\Utility\DatabaseUtility;
use T3\Dce\Utility\FlexformService;
use T3\Dce\Utility\TypoScript;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
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
    private string $fieldTitle = 'text';
    private string $fieldDescription = 'textarea';

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

        $pid = $model->getPageUid();
        $flexForm = $this->getFlexformFromParams($model->getParams());

        $tableName = 'tt_content';
        $connection = DatabaseUtility::getConnectionPool()->getConnectionForTable($tableName);
        $id = $connection->insert(
            $tableName,
            [
                'pid' => $pid,
                'CType' => 'dce_' . $this->pluginName,
                'tstamp' => time(),
                'crdate' => time(),
                'pi_flexform' => $flexForm,
            ]
        );

        return $this->getItemByFlexform($id, $flexForm);
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

        $tableName = 'tt_content';
        /** @var QueryBuilder */
        $queryBuilder = DatabaseUtility::getConnectionPool()->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();
        $records = $queryBuilder
            ->select('*')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAll();

        if (count($records) > 0) {
            $record = $records[0];
            $item->setId(strval($record['uid']));
            $flexformData = FlexformService::get()->convertFlexFormContentToArray($record['pi_flexform'], 'lDEF', 'vDEF');
            if (isset($flexformData['settings']) && is_countable($flexformData['settings'])) {
                foreach ($flexformData['settings'] as $key => $val) {
                    $item->addValue(new ItemValue($key, $val));
                }
            }
        }

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

        $id = intval($model->getUpdateId());
        $flexForm = $this->getFlexformFromParams($model->getParams());

        $tableName = 'tt_content';
        $connection = DatabaseUtility::getConnectionPool()->getConnectionForTable($tableName);
        $connection->update(
            $tableName,
            [
                'tstamp' => time(),
                'pi_flexform' => $flexForm,
            ],
            [
                'uid' => intval($id),
            ]
        );

        return $this->getItemByFlexform($id, $flexForm);
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
            $tableName = 'tt_content';
            $id = intval($model->getDeleteId());
            GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName)->update(
                $tableName,
                ['deleted' => '1'],
                ['uid' => $id]
            );
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
        $pid = $model->getPageUid();
        if ($pid > 0) {
            $tableName = 'tt_content';
            /** @var QueryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($tableName);
            $queryBuilder->getRestrictions()->removeAll();
            $recordRows = $queryBuilder
                ->select('uid', 'pi_flexform')
                ->from($tableName)
                ->where(
                    $queryBuilder->expr()->eq(
                        'pid',
                        $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)
                    )
                )
                ->execute();

            foreach ($recordRows as $row) {
                $items[] = $this->getItemByFlexform($row['uid'], $row['pi_flexform']);
            }
        }

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
                    // TODO: Add FAL for images
                    // print_r($type);
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

                    // DCE does not support multiple selects
                    $newtField->setCount(1);
                    /*
                    if (isset($config['maxitems']) && intval($config['maxitems']) > 0) {
                        $newtField->setCount(intval($config['maxitems']));
                    }
                    */

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
     * Returns an item based on id and flexform
     *
     * @param integer $id
     * @param string $flexform
     * @return Item
     */
    private function getItemByFlexform(int $id, string $flexform): Item
    {
        $item = new Item();
        $item->setId(strval($id));
        $flexformData = FlexformService::get()->convertFlexFormContentToArray($flexform, 'lDEF', 'vDEF');
        $title = 'ID: ' . strval($id);
        $description = null;
        if (isset($flexformData['settings'])) {
            if (isset($flexformData['settings'][$this->fieldTitle])) {
                $title = $flexformData['settings'][$this->fieldTitle];
            }
            if (isset($flexformData['settings'][$this->fieldDescription])) {
                $description = $flexformData['settings'][$this->fieldDescription];
            }
        }
        $item->setTitle($title);
        if ($description) {
            $item->setDescription($description);
        }

        return $item;
    }

    /**
     * Returns the Flexform with the values from params
     *
     * @param array $params
     * @return string
     */
    private function getFlexformFromParams(array $params): string
    {
        $inputDatabase = new InputDatabase();
        $dces = $inputDatabase->getDces();
        $usedDce = null;
        foreach ($dces as $dce) {
            if ($dce['identifier'] == 'dce_' . $this->pluginName) {
                $usedDce = $dce;
            }
        }
        if ($usedDce) {
            foreach ($usedDce['tabs'] as $tab) {
                foreach ($tab['fields'] as $field) {
                    $val = isset($params[$field['variable']]) ? strval($params[$field['variable']]) : '';
                    // DCE doas not support multiple selects
                    $json = json_decode($val);
                    if (is_countable($json)) {
                        $val = $json[0];
                    }
                    $data['data']['sheet.' . $tab['variable']]['lDEF']['settings.' . $field['variable']] = [
                        'vDEF' => $val
                    ];
                }
            }
        }

        $flexFormTools = new FlexFormTools();
        return $flexFormTools->flexArray2Xml($data, true);
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
