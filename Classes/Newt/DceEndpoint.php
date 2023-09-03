<?php

declare(strict_types=1);

namespace Swisscode\Newt4Dce\Newt;

use Swisscode\Newt\NewtApi\EndpointInterface;
use Swisscode\Newt\NewtApi\EndpointOptionsInterface;
use Swisscode\Newt\NewtApi\Field;
use Swisscode\Newt\NewtApi\FieldItem;
use Swisscode\Newt\NewtApi\FieldType;
use Swisscode\Newt\NewtApi\FieldValidation;
use Swisscode\Newt\NewtApi\Item;
use Swisscode\Newt\NewtApi\ItemValue;
use Swisscode\Newt\NewtApi\MethodCreateModel;
use Swisscode\Newt\NewtApi\MethodDeleteModel;
use Swisscode\Newt\NewtApi\MethodListModel;
use Swisscode\Newt\NewtApi\MethodReadModel;
use Swisscode\Newt\NewtApi\MethodType;
use Swisscode\Newt\NewtApi\MethodUpdateModel;
use T3\Dce\Domain\Model\Dce;
use T3\Dce\Domain\Model\DceField;
use T3\Dce\Domain\Repository\DceRepository;
use T3\Dce\Utility\DatabaseUtility;
use T3\Dce\Utility\FlexformService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\Exception\NotImplementedMethodException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Service\CacheService;

class DceEndpoint implements EndpointInterface, EndpointOptionsInterface
{
    private array $settings;
    private array $settingsDce;
    private int $maxImageCount = 6;

    // These options will be filled by EndpointOptions
    private string $pluginName = '';
    private string $fieldTitle = '';
    private string $fieldDescription = '';

    private DceRepository $dceRepository;
    private PersistenceManager $persistenceManager;

    public function __construct(ConfigurationManager $configurationManager, PersistenceManager $persistenceManager)
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->dceRepository = $objectManager->get(DceRepository::class);
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
     * Pass one EndpointOption to the class
     *
     * @param string $optionName
     * @param string $optionValue
     * @return void
     */
    public function addEndpointOption(string $optionName, string $optionValue): void
    {
        switch ($optionName) {
            case "pluginName" :
                $this->pluginName = $optionValue;
                break;
            case "fieldTitle" :
                $this->fieldTitle = $optionValue;
                break;
            case "fieldDescription" :
                $this->fieldDescription = $optionValue;
                break;
        }
    }

    /**
     * Returns the array with needed options as an assoc-array ["key" => "label"]
     *
     * @return array
     */
    public function getNeededOptions(): array
    {
        $languageFile = 'LLL:EXT:newt4dce/Resources/Private/Language/locallang_db.xlf:';
        return [
            $GLOBALS['LANG']->sL($languageFile . 'tx_newt4dce.options.pluginName')       => "pluginName",
            $GLOBALS['LANG']->sL($languageFile . 'tx_newt4dce.options.fieldTitle')       => "fieldTitle",
            $GLOBALS['LANG']->sL($languageFile . 'tx_newt4dce.options.fieldDescription') => "fieldDescription",
        ];
    }

    /**
     * Returns the hint for EndpointOptions
     *
     * @return string|null
     */
    public function getEndpointOptionsHint(): ?string
    {
        $languageFile = 'LLL:EXT:newt4dce/Resources/Private/Language/locallang_db.xlf:';
        return $GLOBALS['LANG']->sL($languageFile . 'tx_newt4dce.options.hint');
    }

    /**
     * Creats a new dce baset tt_content
     * TODO: Not implemented by now...
     *
     * @param MethodCreateModel $model
     * @return Item
     */
    public function methodCreate(MethodCreateModel $model): Item
    {
        throw new NotImplementedMethodException();
        /*
        $item = new Item();
        if (!$model || count($model->getParams()) == 0) {
            return $item;
        }

        $pid = $model->getPageUid();
        $flexForm = $this->getFlexformFromParams($model->getParams());

        $data = [
            'pid' => $pid,
            'CType' => 'dce_' . $this->pluginName,
            'tstamp' => time(),
            'crdate' => time(),
            'pi_flexform' => $flexForm,
        ];
        if ($model->getBackendUserUid() > 0) {
            $data['cruser_id'] = $model->getBackendUserUid();
        }

        $tableName = 'tt_content';
        $connection = DatabaseUtility::getConnectionPool()->getConnectionForTable($tableName);
        $id = $connection->insert(
            $tableName,
            $data
        );

        // Clear the cache
        $this->clearCacheForPage($pid);

        return $this->getItemByFlexform($id, $flexForm);
        */
    }

    /**
     * Read a tt_content by readId
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
            ->select('uid', 'pi_flexform')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('dce_' . $this->pluginName, \PDO::PARAM_STR)),
            )
            ->execute()
            ->fetchAll();

        if (count($records) > 0) {
            $record = $records[0];
            $item->setId(strval($record['uid']));
            $flexformData = FlexformService::get()->convertFlexFormContentToArray($record['pi_flexform'], 'lDEF', 'vDEF');
            if (isset($flexformData['settings']) && is_countable($flexformData['settings'])) {
                $dce = $this->getCurrentDce();
                foreach ($flexformData['settings'] as $key => $val) {
                    if ($dce) {
                        $dceField = $this->getDceFieldByVariable($dce, $key);
                        if ($dceField) {
                            $variable = $dceField->getVariable();
                            $configArray = $dceField->getConfigurationAsArray();
                            $config = isset($configArray['config']) ? $configArray['config'] : [];
                            $maxItems = 1;
                            if (isset($config['maxitems'])) {
                                $maxItems = $config['maxitems'];
                            }
        
                            if ($dceField->isFal()) {
                                // Image
                                $imgCount = min($this->maxImageCount, intval($maxItems));
                                if ($imgCount > 0) {
                                    /** @var FileRepository */
                                    $fileRepository = GeneralUtility::makeInstance(
                                        FileRepository::class
                                    );
                                    $fileReferences = $fileRepository->findByRelation(
                                        'tt_content',
                                        $variable,
                                        $record['uid']
                                    );

                                    for ($i = 0; $i < $imgCount; $i++) {
                                        $imgNum = $i > 0 ? $i : "";
                                        $paramImage = "{$variable}{$imgNum}";
                                        $paramImageUid = "{$variable}{$imgNum}Uid";

                                        $falMedia = null;
                                        if ($fileReferences) {
                                            $k = 0;
                                            foreach ($fileReferences as $mediaItem) {
                                                if (!$falMedia && $k == $i) {
                                                    /** @var FileReference */
                                                    $falMedia = $mediaItem;
                                                }
                                                $k++;
                                            }
                                        }
                                        if ($falMedia) {
                                            $storageId = $falMedia->getOriginalFile()->getProperty('storage');
                                            $identifier = $falMedia->getOriginalFile()->getProperty('identifier');
                                            /** @var StorageRepository */
                                            $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
                                            if ($storageRepository) {
                                                /** @var ResourceStorage */
                                                $storage = $storageRepository->findByUid($storageId);
                                                $file = $storage->getFile($identifier);
                                                if ($file) {
                                                    $fileContent = $file->getContents();
                                                    $item->addValue(new ItemValue($paramImageUid, strval($falMedia->getUid())));
                                                    $item->addValue(new ItemValue($paramImage, base64_encode($fileContent)));
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                $item->addValue(new ItemValue($key, $val));
                            }
                        }
                    }
                }
            }
        }

        return $item;
    }

    /**
     * Update the tt_content with the new data
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
        $flexForm = $this->getFlexformFromParams($model->getUpdateId(), $model->getParams());

        $tableName = 'tt_content';
        $connection = DatabaseUtility::getConnectionPool()->getConnectionForTable($tableName);
        $connection->update(
            $tableName,
            [
                'tstamp' => time(),
                'pi_flexform' => $flexForm,
            ],
            [
                'uid' => $id,
                'CType' => 'dce_' . $this->pluginName,
            ]
        );

        // Clear the cache
        $this->clearCacheForContent($id);

        return $this->getItemByFlexform($id, $flexForm);
    }

    /**
     * Deletes a tt_content by deleteId
     *
     * @param MethodDeleteModel $model
     * @return boolean
     */
    public function methodDelete(MethodDeleteModel $model): bool
    {
        try {
            $tableName = 'tt_content';
            $id = intval($model->getDeleteId());
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);
            $connectionPool->update(
                $tableName,
                ['deleted' => '1'],
                [
                    'uid' => $id,
                    'CType' => 'dce_' . $this->pluginName,
                ]
            );

            // Clear the cache
            $this->clearCacheForContent($id);
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
                    $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('dce_' . $this->pluginName, \PDO::PARAM_STR)),
                )
                ->execute();

            foreach ($recordRows as $row) {
                if (!empty($row['pi_flexform'])) {
                    $items[] = $this->getItemByFlexform($row['uid'], $row['pi_flexform']);
                }
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
        $dce = $this->getCurrentDce();
        if ($dce) {
            $validation = new FieldValidation();
            $validation->setRequired(true);

            /** @var DceField $field */
            foreach ($dce->getFields() as $dceField) {
                if ($dceField->isElement()) {
                    $variable = $dceField->getVariable();
                    $label = $dceField->getTitle();
                    $type = null;
                    $evals = [];
                    $default = null;
                    $renderType = null;
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
                    if (isset($config['renderType'])) {
                        $renderType = $config['renderType'];
                    }
                    if (isset($config['minitems'])) {
                        $minitems = $config['minitems'];
                    }
                    if (isset($config['maxitems'])) {
                        $maxItems = $config['maxitems'];
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
                            // print_r($type);exit;
                            break;
                    }

                    if ('inline' === $config['type'] && 'sys_file_reference' === $config['foreign_table']) {
                        $fieldType = FieldType::IMAGE;
                    } else if ($renderType == 'colorpicker') {
                        $fieldType = FieldType::COLOR;
                    }

                    if ($fieldType == FieldType::IMAGE) {
                        $imgCount = min($this->maxImageCount, intval($maxItems));
                        if ($imgCount > 0) {
                            $divider = new Field();
                            $divider->setType(FieldType::DIVIDER);

                            if ($imgCount > 1) {
                                // Add the divider
                                $ret[] = $divider;
                            }

                            for ($i = 0; $i < $imgCount; $i++) {
                                $imgNum = $i > 0 ? $i : "";
                                $paramImage = "{$variable}{$imgNum}";
                                $paramImageUid = "{$variable}{$imgNum}Uid";
                                $imageUid = new Field();
                                $imageUid->setName($paramImageUid);
                                $imageUid->setType(FieldType::HIDDEN);
                                $ret[] = $imageUid;

                                $image = new Field();
                                $image->setName($paramImage);
                                $image->setLabel($label ?? '');
                                $image->setType(FieldType::IMAGE);
                                if ($minitems > $i) {
                                    $image->setValidation($validation);
                                }
                                $ret[] = $image;
                            }

                            if ($imgCount > 1) {
                                // Add the divider
                                $ret[] = $divider;
                            }
                        }
                    } else if ($fieldType != FieldType::UNKNOWN) {
                        $newtField = new Field();
                        $newtField->setType($fieldType);
                        $newtField->setLabel($label ?? '');
                        $newtField->setName($variable);

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
                        }

                        if (in_array('required', $evals) || $minitems > 0) {
                            $newtField->setValidation($validation);
                        }
                        $ret[] = $newtField;
                    }
                }
            }
        }

        return $ret;
    }

    /**
     * Returns the current Dce
     *
     * @return Dce|null
     */
    private function getCurrentDce(): ?Dce
    {
        $uid = $this->dceRepository::extractUidFromCTypeOrIdentifier('dce_' . $this->pluginName);
        return $this->dceRepository->findByUid($uid);
    }

    /**
     * Returns the DceField matching the variable
     *
     * @param Dce $dce
     * @param string $variable
     * @return DceField|null
     */
    private function getDceFieldByVariable(Dce $dce, string $variable): ?DceField
    {
        /** @var DceField $field */
        foreach ($dce->getFields() as $dceField) {
            if ($dceField->getVariable() == $variable) {
                return $dceField;
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
     * @param mixed $uid
     * @param array $params
     * @return string
     */
    private function getFlexformFromParams($uid, array $params): string
    {
        $dce = $this->getCurrentDce();
        $data = [
            'data' => []
        ];
        if ($dce) {
            $tabVariable = 'tabGeneral';
            /** @var DceField $dceField */
            foreach ($dce->getFields() as $dceField) {
                $variable = $dceField->getVariable();
                if ($dceField->isTab()) {
                    $tabVariable = $variable;
                } else if ($dceField->isElement()) {
                    $val = isset($params[$variable]) ? strval($params[$variable]) : '';
                    $configArray = $dceField->getConfigurationAsArray();
                    $config = isset($configArray['config']) ? $configArray['config'] : [];
                    if (isset($config['maxitems'])) {
                        $maxItems = $config['maxitems'];
                    }
                    if ($dceField->isFal()) {
                        // Image
                        $imgCount = min($this->maxImageCount, intval($maxItems));
                        if ($imgCount > 0) {
                            /** @var FileRepository */
                            $fileRepository = GeneralUtility::makeInstance(
                                FileRepository::class
                            );
                            $fileReferences = $fileRepository->findByRelation(
                                'tt_content',
                                $variable,
                                $uid
                            );

                            for ($i = 0; $i < $imgCount; $i++) {
                                $imgNum = $i > 0 ? $i : "";
                                $paramImage = "{$variable}{$imgNum}";
                                $paramImageUid = "{$variable}{$imgNum}Uid";

                                /** @var FileReference */
                                $usedMedia = null;
                                $isNew = true;
                                if ($fileReferences) {
                                    foreach ($fileReferences as $mediaItem) {
                                        if ($mediaItem->getUid() == intval($params[$paramImageUid])) {
                                            /** @var FileReference */
                                            $usedMedia = $mediaItem;
                                            $isNew = false;
                                            continue;
                                        }
                                    }
                                }

                                if (isset($params[$paramImage]) && $params[$paramImage] instanceof \Swisscode\Newt\Domain\Model\FileReference) {
                                    $imageRef = $params[$paramImage];

                                    if ($isNew) {
                                        $this->addFileReference(intval($uid), $imageRef->getUidLocal(), $variable);
                                    } else {
                                        $this->updateFileReference($usedMedia->getUid(), $imageRef->getUidLocal());
                                    }
                                } else if (isset($params[$paramImageUid]) && intval($params[$paramImageUid]) > 0 && !$isNew) {
                                    // Remove image
                                    $this->removeFileReference($usedMedia->getUid());
                                }
                            }
                        }
                    } else {
                        // DCE does not support multiple selects
                        $json = json_decode($val);
                        if (is_countable($json)) {
                            $val = $json[0];
                        }
                    }
                }
                $data['data']['sheet.' . $tabVariable]['lDEF']['settings.' . $variable] = [
                    'vDEF' => $val
                ];
            }
        }

        $flexFormTools = new FlexFormTools();
        return $flexFormTools->flexArray2Xml($data, true);
    }

    /**
     * Set new Reference for a file
     *
     * @param int $uid
     * @param int $newRefId
     * @return void
     */
    private function updateFileReference(int $uid, int $newRefId)
    {
        $tableName = 'sys_file_reference';
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);
        $connectionPool->update(
            $tableName,
            ['uid_local' => $newRefId],
            [
                'uid' => $uid
            ]
        );
    }

    /**
     * Add new filereference for this
     *
     * @param integer $uid
     * @param integer $refId
     * @param string $fieldname
     * @return integer
     */
    private function addFileReference(int $uid, int $refId, string $fieldname): int
    {
        $tableName = 'sys_file_reference';
        /** @var Connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);
        $connection->insert(
            $tableName,
            [
                // 'pid' => $pid,
                'tstamp' => time(),
                'crdate' => time(),
                'tablenames' => 'tt_content',
                'table_local' => 'sys_file',
                'uid_foreign' => $uid,
                'uid_local' => $refId,
                'fieldname' => $fieldname,
            ]
        );

        return (int)$connection->lastInsertId($tableName);
    }

    /**
     * Remove a file-reference
     *
     * @param integer $uid
     * @return void
     */
    private function removeFileReference(int $uid)
    {
        $tableName = 'sys_file_reference';
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($tableName);
        $connectionPool->update(
            $tableName,
            ['deleted' => 1],
            [
                'uid' => $uid
            ]
        );
    }

    /**
     * Clear the cache for this page
     *
     * @param int $pid
     * @return void
     */
    private function clearCacheForPage($pid)
    {
        /** @var CacheService */
        $cacheManager = GeneralUtility::makeInstance(CacheService::class);
        $cacheManager->clearPageCache($pid);
    }

    /**
     * Clear the cache for the page of this uid
     *
     * @param int $uid
     * @return void
     */
    private function clearCacheForContent($uid)
    {
        $tableName = 'tt_content';
        /** @var QueryBuilder */
        $queryBuilder = DatabaseUtility::getConnectionPool()->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();
        $records = $queryBuilder
            ->select('pid')
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('dce_' . $this->pluginName, \PDO::PARAM_STR)),
            )
            ->execute()
            ->fetchAll();

        foreach ($records as $record) {
            if (intval($record['pid']) > 0) {
                // Clear the cache
                $this->clearCacheForPage(intval($record['pid']));
            }
        }
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
