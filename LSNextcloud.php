<?php

require_once(__DIR__ . "/vendor/autoload.php");

use League\Flysystem\Filesystem;
use League\Flysystem\WebDAV\WebDAVAdapter;
use LimeSurvey\PluginManager\PluginManager;
use Sabre\DAV\Client;

/**
 * Class LSNextcloud
 */
class LSNextcloud extends PluginBase
{
    /**
     * @var string
     */
    static protected $description = 'Nextcloud integratoin plugin';

    /**
     * @var string
     */
    static protected $name = 'LSNextcloud';

    /**
     * @var string
     */
    protected $storage = 'DbStorage';

    /**
     * @var string[][]
     */
    protected $settings = [];

    public function __construct(PluginManager $manager, $id)
    {
        $this->settings = [
            'url' => [
                'type' => 'string',
                'label' => gT('Nextcloud URL'),
                'help' => gT('URL (with protocol) of Nextcloud inscance')
            ],
            'username' => [
                'type' => 'string',
                'label' => gT('Username'),
                'help' => gT('Username of user to receive all files')
            ],
            'password' => [
                'type' => 'password',
                'label' => gT('Password')
            ],
            'path' => [
                'type' => 'string',
                'label' => gT('Path to save'),
                'default' => 'LimeSurvey',
                'help' => gT('The files will be saved in this folder.')
            ],
            'save_attachments' => [
                'type' => 'checkbox',
                'label' => gT('Save attachments'),
                'default' => false,
                'help' => gT('When enabled all attachments will salves in Nextcloud')
            ]
        ];
        parent::__construct($manager, $id);
    }

    /**
     * @return void
     */
    public function init()
    {
        $this->subscribe('newSurveySettings');
        $this->subscribe('afterSurveyComplete');
        $this->subscribe('beforeSurveySettings');
    }

    /**
     * @return void
     */
    public function afterSurveyComplete()
    {
        $enable =
            $this->get(
                'enable',
                'Survey',
                $this->getEvent()->get('surveyId'),
                null
            );
        if (!$enable) {
            return;
        }
        $this->saveCsv();
        $this->saveAttachments();
    }

    private function saveCsv()
    {
        $filesystem = $this->getFilesystem($this->getEvent()->get('surveyId'));
        $this->rootPath =
            $this->get(
                'path',
                'Survey',
                $this->getEvent()->get('surveyId'),
                $this->get('path') // Global
            ) . '/' .
            $this->getEvent()->get('surveyId');
        if (!$filesystem->has($this->rootPath)) {
            $filesystem->createDir($this->rootPath);
        }
        $tempFile = $this->getCsv();
        $filesystem->put($this->rootPath . '/responses.csv', file_get_contents($tempFile));
    }

    private function saveAttachments()
    {
        $saveAttachments =
            $this->get(
                'save_attachments',
                'Survey',
                $this->getEvent()->get('surveyId'),
                $this->get('save_attachments') // Global
            );
        if (!$saveAttachments) {
            return;
        }

        $filesystem = $this->getFilesystem($this->getEvent()->get('surveyId'));
        $response = Response::model($this->getEvent()->get('surveyId'))
            ->findByAttributes([
                'id' => $this->getEvent()->get('responseId')
            ])
            ->decrypt();
        foreach ($response->getFiles() as $aFile) {
            $sFileRealName = Yii::app()->getConfig('uploaddir') . "/surveys/" . $this->getEvent()->get('surveyId') . "/files/" . $aFile['filename'];
            $filesystem->put(
                $this->rootPath . '/' . $this->getEvent()->get('responseId') . '_' . $aFile['filename'] . '_' . $aFile['name'],
                file_get_contents($sFileRealName)
            );
        }
    }

    /**
     * Save file to CSV
     *
     * @return string File path
     */
    private function getCsv(): string
    {
        Yii::import('application.helpers.admin.export.FormattingOptions', true);
        Yii::import('application.helpers.admin.exportresults_helper', true);
        $survey = Survey::model()->findByPk($this->getEvent()->get('surveyId'));
        if (!($maxId = SurveyDynamic::model($this->getEvent()->get('surveyId'))->getMaxId())) {
            throw new Exception('No Data, could not get max id.', 1);
        }
        $oFormattingOptions = new FormattingOptions();
        $oFormattingOptions->responseMinRecord = 1;
        $oFormattingOptions->responseMaxRecord = $maxId;
        $aFields = array_keys(createFieldMap($survey, 'full', true, false, $survey->language));
        $aTokenFields = array('tid','participant_id','firstname','lastname','email','emailstatus','language','blacklisted','sent','remindersent','remindercount','completed','usesleft','validfrom','validuntil','mpid');
        $oFormattingOptions->selectedColumns = array_merge($aFields,$aTokenFields, array_keys($survey->tokenAttributes));
        $oFormattingOptions->responseCompletionState = 'all';
        $oFormattingOptions->headingFormat = 'full';
        $oFormattingOptions->answerFormat = 'long';
        $oFormattingOptions->csvFieldSeparator = ',';
        $oFormattingOptions->output = 'file';
        $oExport = new ExportSurveyResultsService();
        $tempFile = $oExport->exportResponses($this->getEvent()->get('surveyId'), $survey->language, 'csv', $oFormattingOptions, '');
        return $tempFile;
    }

    /**
     * Return Dav Filesystem
     * 
     * @param integer $surveyId
     * @return Filesystem
     */
    private function getFilesystem(int $surveyId): Filesystem {
        if (!$this->fileSystem) {
            $config = [
                'baseUri' => $this->get(
                    'url',
                    'Survey',
                    $surveyId,
                    $this->get('url') // Global
                ),
                'userName' => $this->get(
                    'username',
                    'Survey',
                    $surveyId,
                    $this->get('username') // Global
                ),
                'password' => $this->get(
                    'password',
                    'Survey',
                    $surveyId,
                    $this->get('password') // Global
                )
            ];
            $client = new Client($config);
            $prefix = 'remote.php/dav/files/' . $config['userName'] . '/';
            $adapter = new WebDAVAdapter($client, $prefix);
            $this->fileSystem = new Filesystem($adapter);
        }
        return $this->fileSystem;
    }

    /**
     * @return void
     */
    public function beforeSurveySettings()
    {
        $event = $this->getEvent();
        $event->set(
            "surveysettings.{$this->id}",
            [
                'name' => get_class($this),
                'settings' => [
                    'SettingsInfo' => [
                        'type' => 'info',
                        'content' => '<legend><small>'.gT('Nextcloud Settings').'</small></legend>'
                    ],
                    'url' => [
                        'type' => 'string',
                        'label' => gT('Nextcloud URL'),
                        'current' => $this->get(
                            'url',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get('url') // Global
                        ),
                        'help' => gT('URL (with protocol) of Nextcloud inscance')
                    ],
                    'username' => [
                        'type' => 'string',
                        'label' => gT('Username'),
                        'current' => $this->get(
                            'username',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get('username') // Global
                        ),
                        'help' => gT('Username of user to receive all files')
                    ],
                    'password' => [
                        'type' => 'password',
                        'label' => gT('Password'),
                        'current' => $this->get(
                            'password',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get('password') // Global
                        ),
                    ],
                    'path' => [
                        'type' => 'string',
                        'label' => gT('Path to save'),
                        'current' => $this->get(
                            'path',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get('path') // Global
                        ),
                        'help' => gT('The files will be saved in this folder.')
                    ],
                    'save_attachments' => [
                        'type' => 'checkbox',
                        'label' => gT('Save attachments'),
                        'current' => $this->get(
                            'path',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get('save_attachments') // Global
                        ),
                        'help' => gT('When enabled all attachments will salves in Nextcloud')
                    ],
                    'enable' => [
                        'type' => 'checkbox',
                        'label' => gT('Eable plugin'),
                        'current' => $this->get(
                            'path',
                            'Survey',
                            $event->get('survey'), // Survey
                            null
                        ),
                        'help' => gT('When enabled the CSV will salve in Nextcloud')
                    ]
                ]
            ]
        );
    }

    /**
     * @return void
     */
    public function newSurveySettings()
    {
        $event = $this->getEvent();

        foreach ($event->get('settings') as $name => $value) {
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
    }
}
