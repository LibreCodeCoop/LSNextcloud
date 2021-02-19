<?php

use LimeSurvey\PluginManager\PluginManager;

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
            'enable' => [
                'type' => 'checkbox',
                'label' => gT('Enable globaly'),
                'default' => false,
                'help' => gT('When enabled, these settings will be used for all forms.')
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
        // throw new \InvalidArgumentException('config is not set');
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');
        $path = $this->get(
            'path',
            'Survey',
            $surveyId,
            $this->get('path') // Global
        );
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
