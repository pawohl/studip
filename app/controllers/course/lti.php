<?php

use OAT\Library\Lti1p3Core\Message\Launch\Builder\LtiResourceLinkLaunchRequestBuilder;
use OAT\Library\Lti1p3Core\Message\Launch\Validator\Platform\PlatformLaunchValidator;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\AgsClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\ContextClaim;
use OAT\Library\Lti1p3Core\Message\Payload\Claim\LaunchPresentationClaim;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLink;
use OAT\Library\Lti1p3Core\Resource\LtiResourceLink\LtiResourceLinkInterface;
use OAT\Library\Lti1p3Core\Security\Nonce\NonceRepository;
use OAT\Library\Lti1p3DeepLinking\Factory\ResourceCollectionFactory;
use OAT\Library\Lti1p3DeepLinking\Message\Launch\Builder\DeepLinkingLaunchRequestBuilder;
use Studip\LTI13a\PlatformManager;
use Studip\LTI13a\Registration;
use Studip\LTI13a\RegistrationManager;
use OAT\Library\Lti1p3Core\Message\Payload\MessagePayloadInterface\MessagePayloadInterface;
use LtiResourceLink as LtiResourceLinkModel;
use Studip\LTI13a\RoleMapper;

/**
 * course/lti.php - LTI consumer API for Stud.IP
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Elmar Ludwig
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 */

class Course_LtiController extends StudipController
{
    use Studip\OAuth2\NegotiatesWithPsr7;

    public function __construct(\Trails\Dispatcher $dispatcher)
    {
        // these actions do not require session authentication
        $action = basename(get_route());
        if (!in_array($action, ['profile', 'outcome'])) {
            $this->with_session = true;
            $this->allow_nobody = false;
        }
        parent::__construct($dispatcher);
    }
    /**
     * Callback function being called before an action is executed.
     */
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
        //The profile and outcome actions do not require all the other
        //stuff that is going on in this method:
        if (in_array($action, ['profile', 'outcome'])) {
            return;
        }
        $this->course_id = Context::getId();
        $this->course = Course::find($this->course_id);

        if (in_array($action, ['select_tool', 'add_link']) && !$this->course) {
            throw new AccessDeniedException();
        }

        $this->edit_perm = $GLOBALS['perm']->have_studip_perm('tutor', $this->course_id);
        if (!in_array($action, ['index', 'iframe', 'grades', 'consent']) && !$this->edit_perm) {
            throw new AccessDeniedException();
        }

        if (
            !in_array($action, ['admin', 'grades'])
            && Navigation::hasItem('/course/lti/index')
        ) {
            Navigation::activateItem('/course/lti/index');
        }
    }

    /**
     * Display the list of LTI content blocks.
     */
    public function index_action()
    {
        $this->links = [];
        if ($this->edit_perm) {
            $this->links = \LtiResourceLink::findByCourse_id($this->course_id, 'ORDER BY `position`');
        } else {
            //Only load those LTI resource links that are fully configured:
            $this->links = \LtiResourceLink::findBySQL(
                "JOIN `lti_deployments`
                ON `lti_deployments`.`id` = `lti_resource_links`.`deployment_id`
                WHERE `lti_resource_links`.`course_id` = :course_id
                AND (`lti_resource_links`.`options` IS NULL OR `lti_resource_links`.`options` NOT LIKE '%unfinished_deep_linking%')
                ORDER BY `lti_resource_links`.`position`",
                ['course_id' => $this->course_id]
            );
        }

        if ($this->edit_perm) {
            $widget = Sidebar::get()->addWidget(new ActionsWidget());
            $widget->addLink(
                _('Einstellungen'),
                $this->url_for('course/lti/config'),
                Icon::create('admin')
            )->asDialog('size=auto');
            $global_tools_available = LtiTool::countBySQL("`range_id` = 'global'") > 0;
            if (Config::get()->LTI_ALLOW_TOOL_CONFIG_IN_COURSE || $global_tools_available) {
                $widget->addLink(
                    _('LTI-Tool hinzufügen'),
                    $this->url_for('course/lti/select_tool'),
                    Icon::create('add')
                )->asDialog('size=auto');
            }

            $global_deep_linking_tools_exist = LtiTool::countBySQL("`deep_linking` = 1 AND `range_id` = 'global'") > 0;
            if ($global_deep_linking_tools_exist) {
                $widget->addLink(
                    _('Tool mittels LTI Deep Linking hinzufügen'),
                    $this->url_for('course/lti/add_link'),
                    Icon::create('network2')
                )->asDialog('size=auto');
            }
        }

        Helpbar::get()->addPlainText('', _('Auf dieser Seite können Sie externe Anwendungen einbinden, sofern diese den LTI-Standard (Version 1.x) unterstützen.'));

        //Check for error messages:
        if (Request::get('deployment_id') && (Request::submitted('lti_msg') || Request::submitted('lti_errormsg'))) {
            $deployment = LtiDeployment::find(Request::get('deployment_id'));
            if ($deployment) {
                //Get the resource link for the deployment and display the messages:
                $link = \LtiResourceLink::findOneBySQL(
                    "`deployment_id` = :deployment_id AND `course_id` = :course_id",
                    ['deployment_id' => $deployment->id, 'course_id' => $this->course_id]
                );
                if ($link) {
                    if (Request::get('lti_msg')) {
                        PageLayout::postInfo(htmlReady($link->title . ': ' . Request::get('lti_msg')));
                    }
                    if (Request::get('lti_errormsg')) {
                        PageLayout::postError(htmlReady($link->title . ': ' . Request::get('lti_errormsg')));
                    }
                }
            }
        }
    }

    public function select_tool_action()
    {
        //The permission check is done in the before filter.
        $this->global_tool_deployments = LtiDeployment::findBySQL(
            "JOIN `lti_tools`
            ON `lti_deployments`.`tool_id` = `lti_tools`.`id`
            WHERE `lti_deployments`.`purpose` = 'general'
            AND `lti_tools`.`range_id` IN (:range_ids)
            ORDER BY `lti_tools`.`name` ASC",
            [
                'range_ids' => ['global', $this->course_id]
            ]
        );

        if (!$this->global_tool_deployments) {
            if (!Config::get()->LTI_ALLOW_TOOL_CONFIG_IN_COURSE) {
                PageLayout::postError(_('Es sind keine globalen LTI-Tools konfiguriert, die in dieser Veranstaltung eingebunden werden können.'));
                return;
            }
            //Redirect to the page to configure an LTI tool for the course:
            $this->redirect('lti/tool/add/' . $this->course->id);
        }

        $this->selected_deployment_id = '';
        if (count($this->global_tool_deployments) >= 1) {
            //Preselect the first tool:
            $this->selected_deployment_id = $this->global_tool_deployments[0]->id;
        }
    }

    public function select_tool_redirect_action()
    {
        if (Request::isPost()) {
            CSRFProtection::verifyUnsafeRequest();
            $selected_deployment_id = Request::get('selected_deployment_id');
            if ($selected_deployment_id === 'new') {
                //Redirect to the page to configure an LTI tool for the course:
                $this->redirect('lti/tool/add/' . $this->course->id);
            } else {
                //Load the selected deployment and check if it can be used in the course.
                $selected_deployment = LtiDeployment::find($selected_deployment_id);
                if (!$selected_deployment || $selected_deployment->tool->range_id !== 'global') {
                    PageLayout::postError(_('Das ausgewählte LTI-Tool kann nicht genutzt werden.'));
                    $this->redirect('course/lti/select_tool');
                    return;
                }
                //Link the tool in the course:
                $link                = new \LtiResourceLink();
                $link->deployment_id = $selected_deployment->id;
                $link->course_id     = $this->course->id;
                if ($link->store()) {
                    PageLayout::postSuccess(_('Das LTI-Tool wurde eingebunden.'));
                } else {
                    PageLayout::postError(_('Das LTI-Tool konnte nicht eingebunden werden.'));
                }
                $this->relocate('course/lti', ['cid' => $this->course->id]);
            }
        } else {
            $this->redirect('course/lti/select_tool');
        }
    }

    public function consent_action(string $link_id)
    {
        $this->resource_link = \LtiResourceLink::find($link_id);
        if (!$this->resource_link) {
            PageLayout::postError(_('Die Einbindung eines LTI-Tools ist ungültig.'));
            return;
        }
        $tool_id = $this->resource_link->deployment->tool_id;

        $this->privacy_settings = LtiToolPrivacySettings::findOneBySQL(
            'tool_id = :tool_id AND user_id = :user_id',
            ['tool_id' => $tool_id, 'user_id' => $GLOBALS['user']->id]
        );
        if (!$this->privacy_settings) {
            $this->privacy_settings = new LtiToolPrivacySettings();
            $this->privacy_settings->tool_id = $tool_id;
            $this->privacy_settings->user_id = $GLOBALS['user']->id;
        }

        if (Request::isPost()) {
            CSRFProtection::verifyUnsafeRequest();
            if (Request::submitted('save')) {
                if (!Request::get('confirmed')) {
                    PageLayout::postError(_('Ohne die aktive Zustimmung zur Weitergabe Ihrer personenbezogenen Daten können Sie das LTI-Tool nicht nutzen!'));
                    return;
                }
                //Save the privacy settings and redirect to the tool:
                $this->privacy_settings->accepted = '1';

                //Check which optional fields are allowed to be transmitted to the tool:
                $optional_field_list = Request::getArray('submit_optional_field', []);
                $optional_fields = [];
                if (array_key_exists('lang', $optional_field_list)) {
                    $optional_fields[] = 'lang';
                }
                if (array_key_exists('avatar_url', $optional_field_list)) {
                    $optional_fields[] = 'avatar_url';
                }
                $this->privacy_settings->allowed_optional_fields = implode(',', $optional_fields);
                //Store the privacy settings:
                $this->privacy_settings->store();
            }
            if (Request::isDialog()) {
                //Close the dialog:
                $this->response->add_header('X-Dialog-Close', '1');
            } elseif (Request::submitted('redirect_to_tool') && Request::submitted('save')) {
                //Redirect to the tool launch action, but only after the privacy settings have been saved:
                $this->redirect('course/lti/iframe/' . $this->resource_link->id);
            } else {
                //Redirect to the LTI tool page of the course:
                $this->redirect('course/lti/index');
            }
        }
    }

    /**
     * Display the launch form for a tool as an iframe.
     */
    public function iframe_action(string $link_id)
    {
        $this->resource_link = \LtiResourceLink::find($link_id);
        $this->show_data_protection_info = !LtiToolPrivacySettings::countBySQL(
            "`tool_id` = :tool_id AND `user_id` = :user_id AND `accepted` = 1",
            ['tool_id' => $this->resource_link->deployment->tool_id, 'user_id' => $GLOBALS['user']->id]
        );
        if ($this->show_data_protection_info) {
            $this->redirect('course/lti/consent/' . $this->resource_link->deployment_id, ['redirect_to_tool' => '1']);
            return;
        }

        if (!$this->show_data_protection_info) {
            //Redirect to the tool.
            $this->version = $this->resource_link->deployment->getToolLtiVersion();

            //LTI 1.3a
            if ($this->version === '1.3a') {
                $return_url = URLHelper::getURL($GLOBALS['ABSOLUTE_URI_STUDIP'] . 'dispatch.php/course/lti', ['deployment_id' => $this->resource_link->deployment_id]);
                $document_target = 'window';
                if (!empty($this->resource_link->options['document_target'])) {
                    $return_url = $this->resource_link->options['document_target'];
                    $document_target = 'iframe';
                }

                $locale = str_replace('_', '-', $_SESSION['_language']);
                $registration = new Registration($this->resource_link->deployment->tool, $this->resource_link);
                $builder = new LtiResourceLinkLaunchRequestBuilder();

                //The AGS URLs need several parameters:
                $ags_url_parameters = [
                    'cid'           => $this->course_id,
                    'tool_id'       => $this->resource_link->deployment->tool_id,
                    'deployment_id' => $this->resource_link->deployment_id,
                    'cancel_login'  => '1'
                ];

                //Build the message:
                $this->message = $builder->buildLtiResourceLinkLaunchRequest(
                    $this->resource_link,
                    $registration,
                    $GLOBALS['user']->id,
                    $this->resource_link->deployment_id,
                    RoleMapper::fromLocal($GLOBALS['perm']->get_studip_perm($this->course_id)),
                    array_merge(
                        [
                            new ContextClaim(
                                $this->course_id,
                                ['http://purl.imsglobal.org/vocab/lis/v2/course#CourseOffering'],
                                $this->course->veranstaltungsnummer ?? '',
                                $this->course?->getFullName() ?? ''
                            ),
                            new LaunchPresentationClaim(
                                $document_target,
                                null,
                                null,
                                $return_url,
                                $locale
                            ),
                            new AgsClaim(
                                [
                                    'https://purl.imsglobal.org/spec/lti-ags/scope/lineitem',
                                    'https://purl.imsglobal.org/spec/lti-ags/scope/result.readonly',
                                    'https://purl.imsglobal.org/spec/lti-ags/scope/score'
                                ],
                                $this->url_for('lti/ags/line_items', $ags_url_parameters),
                                $this->url_for('lti/ags/line_item', $ags_url_parameters)
                            )
                        ],
                        $this->resource_link->getCustomLtiParameterArray(),
                    )
                );
            }

            //LTI 1.0/1.1
            if ($this->version === '1.1') {
                $this->deployment = $this->resource_link->deployment;
                $lti_link = $this->getLtiLink($this->deployment, $this->resource_link);
                $this->launch_url = $this->deployment->getLaunchURL();
                $this->launch_data = $lti_link->getBasicLaunchData();
                $this->signature = $lti_link->getLaunchSignature($this->launch_data);
            }

            $this->set_layout(null);
        }
    }

    /**
     * Edit the course settings.
     */
    public function config_action()
    {
        $course_config = CourseConfig::get($this->course_id);
        $this->personal_data_warning = $course_config->LTI_DATA_PROTECTION_COURSE_WARNING;
        if (empty($this->personal_data_warning)) {
            $this->personal_data_warning = Config::get()->LTI_DATA_PROTECTION_DEFAULT_WARNING;
        }
    }

    /**
     * Save the course settings.
     */
    public function save_config_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $course_config = CourseConfig::get($this->course_id);

        if (Request::bool('reset_warning')) {
            $course_config->delete('LTI_DATA_PROTECTION_COURSE_WARNING');
        } else {
            $course_config->store(
                'LTI_DATA_PROTECTION_COURSE_WARNING',
                trim(Request::get('personal_data_warning'))
            );
        }

        PageLayout::postSuccess(_('Die Einstellungen wurden gespeichert.'));
        $this->redirect('course/lti');
    }

    /**
     * Moves an LTI resource link up or down in a course.
     *
     * @param string $link_id The ID of the resource link to be moved.
     *
     * @param string $direction 'up' for moving the deployment upwards or 'down' for downwards.
     */
    public function move_action(string $link_id, string $direction)
    {
        CSRFProtection::verifyUnsafeRequest();

        $link = \LtiResourceLink::find($link_id);
        if (!$link) {
            //Redirect and do nothing:
            $this->redirect('course/lti');
            return;
        }

        $new_position = 0;

        if ($direction === 'up') {
            $new_position = $link->position - 1;
        } else {
            $new_position = $link->position + 1;
        }

        //Find the deployment with the new position:
        $other_link = \LtiResourceLink::findByCourseAndPosition($this->course_id, $new_position);
        if ($other_link) {
            $other_link->position = $link->position;
            $other_link->store();
        }
        $link->position = $new_position;
        $link->store();

        $this->redirect('course/lti');
    }

    /**
     * Delete an LTI content block.
     *
     * @param   int $position   block position
     */
    public function delete_action($position)
    {
        CSRFProtection::verifyUnsafeRequest();

        $link = \LtiResourceLink::findByCourseAndPosition($this->course_id, $position);
        $link->delete();

        PageLayout::postSuccess(_('Der Abschnitt wurde gelöscht.'));
        $this->redirect('course/lti');
    }

    /**
     * Offers tool selection for LTI deep linking.
     */
    public function add_link_action()
    {
        //The permission check is done in the before filter.

        $this->tools = LtiTool::findBySQL("`deep_linking` = '1' AND `range_id` = 'global' ORDER BY `name` ASC");
        if (!$this->tools) {
            PageLayout::postError(_('Es sind keine globalen LTI-Tools konfiguriert.'));
            return;
        }
    }

    /**
     * Prepares the tool selected in the add_link action for being included in the course
     * and displays the platform configuration that must be added in the LTI tool.
     */
    public function select_link_action()
    {
        $this->tool = LtiTool::find(Request::int('tool_id'));
        if (!$this->tool) {
            PageLayout::postError(_('Das ausgewählte LTI-Tool wurde nicht gefunden.'));
            $this->relocate('course/lti/add_link');
            return;
        }
        if (!$this->tool->deep_linking) {
            PageLayout::postError(_('Das ausgewählte LTI-Tool unterstützt kein Deep Linking.'));
            $this->relocate('course/lti/add_link');
            return;
        }

        //Create a deployment for deep linking:
        $this->deployment = new LtiDeployment();
        $this->deployment->tool_id = $this->tool->id;
        $this->deployment->purpose = 'deep_linking';
        if ($this->deployment->store()) {
            //Create an LTI resource link for the course:
            $this->link = new \LtiResourceLink();
            $this->link->deployment_id = $this->deployment->id;
            $this->link->course_id     = $this->course_id;
            $this->link->options       = ['unfinished_deep_linking' => 'true'];
            if (!$this->link->store()) {
                PageLayout::postError(_('Die Einbindung des LTI-Tools in die Veranstaltung ist fehlgeschlagen.'));
                $this->relocate('course/lti/add_link');
            }
        } else {
            PageLayout::postError(_('Es konnte kein LTI-Deployment für LTI Deep Linking erstellt werden.'));
            $this->relocate('course/lti/add_link');
        }
    }

    /**
     * Proceeds after the select_link action by switching to the LTI tool for
     * selecting the items from the deep linked tool that shall be available in the Stud.IP course.
     */
    public function process_select_link_action($link_id = '')
    {
        CSRFProtection::verifyUnsafeRequest();

        $this->link = \LtiResourceLink::find($link_id);
        if (!$this->link) {
            PageLayout::postError(_('Die Einbindung des LTI-Tools wurde nicht gefunden.'));
            $this->relocate('course/lti/add_link');
            return;
        }
        if ($this->link->course_id !== $this->course_id) {
            PageLayout::postError(_('Die Einbindung des LTI-Tools ist nicht für diese Veranstaltung bestimmt.'));
            $this->relocate('course/lti/add_link');
            return;
        }
        if (empty($this->link->options['unfinished_deep_linking'])) {
            PageLayout::postError(_('Die Einbindung des LTI-Tools ist bereits abgeschlossen.'));
            $this->relocate('course/lti/add_link');
            return;
        }

        $this->tool = $this->link->deployment->tool ?? null;
        if (!$this->tool) {
            PageLayout::postError(_('Das ausgewählte LTI-Tool wurde nicht gefunden.'));
            $this->redirect('course/lti/add_link');
            return;
        }
        if (!$this->tool->deep_linking) {
            PageLayout::postError(_('Das ausgewählte LTI-Tool unterstützt kein Deep Linking.'));
            $this->redirect('course/lti/add_link');
            return;
        }

        if ($this->tool->lti_version === '1.3a') {
            //LTI 1.3a
            $builder = new DeepLinkingLaunchRequestBuilder();
            $message = $builder->buildDeepLinkingLaunchRequest(
                PlatformManager::getDeepLinkingConfiguration($this->link->id, $this->course_id),
                new Registration($this->tool, $this->link),
                $GLOBALS['user']->id,
                null,
                $this->link->deployment_id,
                RoleMapper::fromLocal($GLOBALS['perm']->get_studip_perm($this->course_id))
            );
            $this->render_text($message->toHtmlRedirectForm());
        } else {
            //LTI 1.0/1.1
            $custom_parameters = explode("\n", $this->tool->custom_parameters);
            $content_item_return_url = $this->url_for('course/lti/save_link/' . $this->link->id);

            // set up ContentItemSelectionRequest
            $lti_link = new LtiLink($this->tool->launch_url, $this->tool->consumer_key, $this->tool->consumer_secret, $this->tool->oauth_signature_method);
            $lti_link->setUser(User::findCurrent(), 'Instructor', $this->tool->send_lis_person);
            $lti_link->setCourse($this->course_id);
            $lti_link->addLaunchParameters([
                'lti_message_type' => 'ContentItemSelectionRequest',
                'accept_media_types' => 'application/vnd.ims.lti.v1.ltilink',
                'accept_presentation_document_targets' => 'iframe,window',
                'content_item_return_url' => $content_item_return_url,
                'launch_presentation_locale' => str_replace('_', '-', $_SESSION['_language']),
                'launch_presentation_document_target' => 'window'
            ]);

            foreach ($custom_parameters as $param) {
                if (strpos($param, '=') !== false) {
                    [$key, $value] = explode('=', $param, 2);
                    $lti_link->addCustomParameter(trim($key), trim($value));
                }
            }

            $this->launch_url = $lti_link->getLaunchURL();
            $this->launch_data = $lti_link->getBasicLaunchData();
            $this->signature = $lti_link->getLaunchSignature($this->launch_data);

            $this->set_layout(null);
            $this->render_action('iframe');
        }
    }

    /**
     * Handles the jump back from the LTI tool into Stud.IP and finishes the integration
     * of a deep linked LTI tool into a Stud.IP course.
     *
     * @param   int $link_id    tool id
     */
    public function save_link_action($link_id)
    {
        $this->link = \LtiResourceLink::find($link_id);
        if (!$this->link) {
            PageLayout::postError(_('Die Einbindung des LTI-Tools wurde nicht gefunden!'));
            $this->relocate('course/lti/add_link');
            return;
        }
        if ($this->link->course_id !== $this->course_id) {
            PageLayout::postError(_('Die Einbindung des LTI-Tools ist nicht für diese Veranstaltung bestimmt.'));
            $this->relocate('course/lti/add_link');
            return;
        }
        if (empty($this->link->options['unfinished_deep_linking'])) {
            PageLayout::postError(_('Die Einbindung des LTI-Tools ist bereits abgeschlossen.'));
            $this->relocate('course/lti/add_link');
            return;
        }

        $tool = $this->link->deployment->tool ?? null;
        if (!$tool) {
            PageLayout::postError(_('Das ausgewählte LTI-Tool wurde nicht gefunden.'));
            $this->redirect('course/lti/add_link');
            return;
        }
        if (!$tool->deep_linking) {
            PageLayout::postError(_('Das ausgewählte LTI-Tool unterstützt kein Deep Linking.'));
            $this->redirect('course/lti/add_link');
            return;
        }

        if ($tool->lti_version === '1.3a') {
            //LTI 1.3a

            $reg_man = new RegistrationManager();
            $reg_man->setResourceLink($this->link);

            $validator = new PlatformLaunchValidator(
                $reg_man,
                new NonceRepository(Studip\Cache\Factory::getCache())
            );
            $result = $validator->validateToolOriginatingLaunch($this->getPsrRequest());
            if ($result->hasError()) {
                PageLayout::postError($result->getError());
                $this->redirect('course/lti/index');
                return;
            }
            $all_lti_resources = (new ResourceCollectionFactory())->createFromClaim(
                $result->getPayload()->getDeepLinkingContentItems()
            );

            $lti_resource_links = $all_lti_resources->getByType(LtiResourceLinkInterface::TYPE);
            if (count($lti_resource_links) > 0) {
                foreach ($lti_resource_links as $lti_resource_link) {
                    $this->link->launch_url = $lti_resource_link->getUrl();
                    if (!empty($this->link->options['unfinished_deep_linking'])) {
                        unset($this->link->options['unfinished_deep_linking']);
                    }
                    $this->link->store();
                }
            }
        } else {
            $lti_msg = Request::get('lti_msg');
            $lti_errormsg = Request::get('lti_errormsg');
            $content_items = Request::get('content_items');
            $content_items = json_decode($content_items, true);

            if (!Studip\OAuth1::verifyRequest($this->getPsrRequest(), $tool->consumer_secret, '')) {
                throw new Exception('Could not verify request.');
            }

            if (is_array($content_items) && count($content_items['@graph'])) {
                // we only support selecting a single content item
                $item = $content_items['@graph'][0];

                $lti_data = new LtiDeployment();
                $lti_data->title = (string) $item['title'];
                $lti_data->description = Studip\Markup::purifyHtml(Studip\Markup::markAsHtml($item['text']));
                $lti_data->tool_id = $tool->id;
                $lti_data->launch_url = (string) ($item['url'] ?? '');
                $options = [];
                if (is_array($item['custom'])) {
                    $custom_parameters = '';
                    foreach ($item['custom'] as $key => $value) {
                        $custom_parameters .= $key . '=' . $value . "\n";
                    }
                    $options['custom_parameters'] = $custom_parameters;
                }

                if (isset($item['placementAdvice']['presentationDocumentTarget'])) {
                    $options['document_target'] = $item['placementAdvice']['presentationDocumentTarget'];
                }

                $lti_data->options = $options;
                $lti_data->store();
                $link = new \LtiResourceLink();
                $link->deployment_id = $lti_data->id;
                $link->course_id     = $this->course_id;
                $link->position      = \LtiResourceLink::countBySQL('course_id = ?', [$this->course_id]);
                $link->store();
                PageLayout::postSuccess($lti_msg ?: _('Der Link wurde als neuer Abschnitt hinzugefügt.'));
            }
        }

        if (!empty($lti_errormsg)) {
            PageLayout::postError($lti_errormsg);
        }

        $this->redirect('course/lti');
    }

    /**
     * Return an LtiLink object for the configured LTI content block.
     *
     * @param LtiDeployment $ltiDeployment data of LTI content block
     * @param LtiResourceLinkModel $resourceLink
     *
     * @return LtiLink  LTI link representation
     */
    public function getLtiLink(LtiDeployment $ltiDeployment, LtiResourceLinkModel $resourceLink): LtiLink
    {
        $authUser = User::findCurrent();
        $ltiRole = $this->edit_perm ? 'Instructor' : 'Learner';

        $customParameters = explode("\n", $ltiDeployment->getCustomParameters());
        $lisOutcomeServiceUrl = $this->url_for('course/lti/outcome/' . $ltiDeployment->id, ['cid' => null]);
        $tcProfileUrl = $this->url_for('course/lti/profile/' . $ltiDeployment->id, ['cid' => null]);

        // set up launch request
        $ltiLink = new LtiLink(
            $ltiDeployment->getLaunchURL(),
            $ltiDeployment->getConsumerKey(),
            $ltiDeployment->getConsumerSecret(),
            $ltiDeployment->getOauthSignatureMethod()
        );

        $ltiLink->setResource($ltiDeployment->id, $resourceLink->title, kill_format($resourceLink->description));
        $ltiLink->setUser($authUser, $ltiRole, $ltiDeployment->getSendLisPerson());
        $ltiLink->setCourse($resourceLink->course_id);
        $ltiLink->addVariable('ToolConsumerProfile.url', $tcProfileUrl);
        $ltiLink->addLaunchParameters([
            'launch_presentation_locale' => str_replace('_', '-', $_SESSION['_language']),
            'launch_presentation_document_target' => $resourceLink->options['document_target'],
            'lis_outcome_service_url' => $lisOutcomeServiceUrl,
            'lis_result_sourcedid' => $authUser->id
        ]);

        foreach ($customParameters as $param) {
            if (strpos($param, '=') !== false) {
                [$key, $value] = explode('=', $param, 2);
                $ltiLink->addCustomParameter(trim($key), trim($value));
            }
        }

        return $ltiLink;
    }

    /**
     * Return the LTI consumer profile in standard JSON format.
     *
     * @param   int $id    link id
     */
    public function profile_action($id)
    {
        $profile = [
            '@context' => ['http://purl.imsglobal.org/ctx/lti/v2/ToolConsumerProfile'],
            '@type' => 'ToolConsumerProfile',
            'lti_version' => 'LTI-1p0',
            'guid' => md5(Config::get()->STUDIP_INSTALLATION_ID),
            'product_instance' => [
                'guid' => Config::get()->STUDIP_INSTALLATION_ID,
                'product_info' => [
                    'product_name' => ['default_value' => 'Stud.IP'],
                    'product_version' => $GLOBALS['SOFTWARE_VERSION'],
                    'product_family' => [
                        'code' => 'studip',
                        'vendor' => [
                            'code' => 'studip.de',
                            'vendor_name' => ['default_value' => 'Stud.IP e.V.'],
                            'website' => 'https://www.studip.de/',
                            'timestamp' => date('c')
                        ]
                    ]
                ],
                'service_owner' => [
                    'service_owner_name' => ['default_value' => Config::get()->UNI_NAME_CLEAN],
                    'description' => ['default_value' => $GLOBALS['UNI_INFO']],
                    'support' => ['email' => $GLOBALS['UNI_CONTACT']],
                    'timestamp' => date('c')
                ]
            ],
            'capability_offered' => [
                'basic-lti-launch-request',
                'ContentItemSelectionRequest',
                'Context.id',
                'Context.label',
                'Context.title',
                'Context.type',
                'CourseSection.courseNumber',
                'CourseSection.credits',
                'CourseSection.dept',
                'CourseSection.label',
                'CourseSection.longDescription',
                'CourseSection.maxNumberofStudents',
                'CourseSection.numberofStudents',
                'CourseSection.shortDescription',
                'CourseSection.sourcedId',
                'CourseSection.title',
                'Person.email.primary',
                'Person.name.family',
                'Person.name.full',
                'Person.name.given',
                'Person.name.prefix',
                'Person.name.suffix',
                'Person.sourcedId',
                'Person.webaddress',
                'ResourceLink.description',
                'ResourceLink.id',
                'ResourceLink.title',
                'ToolConsumerProfile.url',
                'User.id',
                'User.image',
                'User.username'
            ],
            'service_offered' => [
                '@type' => 'RestService',
                '@id' => 'tcp:Outcomes.LTI1',
                'endpoint' => $this->url_for('course/lti/outcome/' . $id),
                'format' => ['application/vnd.ims.lti.v1.outcome+xml'],
                'action' => ['POST']
            ]
        ];

        $this->set_content_type('application/vnd.ims.lti.v2.toolconsumerprofile+json');
        $this->render_text(json_encode($profile));
    }

    /**
     * Handle outcome service callback request by the LTI tool.
     *
     * @param   int $id    link id
     */
    public function outcome_action($id)
    {
        $lti_data = \LtiResourceLink::find($id);

        if (!Studip\OAuth1::verifyRequest($this->getPsrRequest(), $lti_data->deployment->getConsumerSecret(), '')) {
            throw new Exception('Could not verify request.');
        }

        // fetch and parse POST data
        $message = file_get_contents('php://input');
        $envelope = new SimpleXMLElement($message);
        $header = current($envelope->imsx_POXHeader->children());
        $body = current($envelope->imsx_POXBody->children());

        $message_id = trim($header->imsx_messageIdentifier);
        $operation = $body->getName();
        $user_id = trim($body->resultRecord->sourcedGUID->sourcedId);
        $grade = new LtiGrade([$id, $user_id]);

        $this->message_id = uniqid();
        $this->message_ref = $message_id;
        $this->status_severity = 'status';
        $this->status_code = 'success';
        $this->operation = $operation;

        if (!CourseMember::exists([$lti_data->course_id, $user_id])) {
            $this->status_severity = 'error';
            $this->status_code = 'failure';
            $this->description = 'incorrect sourcedId: ' . $user_id;
        } else if ($operation === 'readResultRequest') {
            if ($grade->isNew()) {
                $this->status_severity = 'error';
                $this->status_code = 'failure';
                $this->description = 'no score found for: ' . $user_id;
            } else {
                $this->score = $grade->score;
                $this->description = 'score has been read';
            }
        } else if ($operation === 'replaceResultRequest') {
            $grade->score = (float) $body->resultRecord->result->resultScore->textString;
            $grade->store();
            $this->description = 'score has been updated';
        } else if ($operation === 'deleteResultRequest') {
            $grade->delete();
            $this->description = 'score has been deleted';
        } else {
            $this->status_severity = 'error';
            $this->status_code = 'unsupported';
            $this->description = 'operation not supported: ' . $operation;
        }

        $this->set_content_type('text/xml; charset=UTF-8');
        $this->set_layout(null);
    }

    /**
     * Display the (simple) LTI grade book.
     */
    public function grades_action()
    {
        Navigation::activateItem('/course/lti/grades');

        if ($this->edit_perm) {
            $this->lti_data_array = \LtiResourceLink::findBySQL(
                "`course_id` = :course_id
                ORDER BY `position`",
                ['course_id' => $this->course_id]
            );
        } else {
            //Only load those deployments that are fully configured:
            $this->lti_data_array = \LtiResourceLink::findBySQL(
                "`course_id` = :course_id
                AND (`options` IS NULL OR `options` NOT LIKE '%unfinished_deep_linking%')
                ORDER BY `position`",
                ['course_id' => $this->course_id]
            );
        }

        if ($this->edit_perm) {
            $this->desc = Request::int('desc');
            $this->members = CourseMember::findByCourseAndStatus($this->course_id, 'autor');

            if ($this->desc) {
                $this->members = array_reverse($this->members);
            }

            $widget = Sidebar::get()->addWidget(new ExportWidget());
            $widget->addLink(
                _('Ergebnisse exportieren'),
                $this->url_for('course/lti/export_grades'),
                Icon::create('download')
            );
        } else {
            $this->render_action('grades_user');
        }

        Helpbar::get()->addPlainText('', _('Auf dieser Seite können Sie die Ergebnisse sehen, die von LTI-Tools zurückgemeldet wurden.'));
    }

    /**
     * Export grades from the gradebook in CSV format.
     */
    public function export_grades_action()
    {
        if ($this->edit_perm) {
            $lti_data_array = \LtiResourceLink::findByCourse_id($this->course_id, 'ORDER BY position');
        } else {
            //Only load those deployments that are fully configured:
            $lti_data_array = \LtiResourceLink::findBySQL(
                "`course_id` = :course_id AND (`options` IS NULL OR `options` NOT LIKE '%unfinished_deep_linking%')
                ORDER BY `position`",
                ['course_id' => $this->course_id]
            );
        }

        $columns = [_('Nachname'), _('Vorname')];

        // add one column for each LTI tool block
        foreach ($lti_data_array as $lti_data) {
            $columns[] = $lti_data->title;
        }

        $data = [$columns];

        foreach (CourseMember::findByCourseAndStatus($this->course_id, 'autor') as $member) {
            $row = [$member->nachname, $member->vorname];

            foreach ($lti_data_array as $lti_data) {
                if ($grade = $lti_data->grades->findOneBy('user_id', $member->user_id)) {
                    $row[] = (float) $grade->score;
                } else {
                    $row[] = '';
                }
            }

            $data[] = $row;
        }

        $filename = Context::get()->name . ' - ' . _('Ergebnisse') . '.csv';
        $this->render_csv($data, $filename);
    }
}
