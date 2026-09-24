<?php

use Studip\Markup;

class Lti_ToolController extends AuthenticatedController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $this->tool       = null;
        $this->deployment = null;
        $this->tool_id    = '';
        $this->range_id   = '';

        if (in_array($action, ['index', 'add', 'edit', 'delete'])) {
            $this->range_id = $args[0];
            $this->tool_id  = $args[1] ?? '';

            if ($action === 'add' && !$this->tool_id) {
                $this->tool = new LtiTool();
                $this->tool->range_id = $this->range_id;
            } else {
                if (!$this->tool_id) {
                    PageLayout::postError(_('Es wurde kein LTI-Tool angegeben.'));
                    return;
                }
                $this->tool = LtiTool::find($this->tool_id);
                if (!$this->tool) {
                    throw new \Studip\Exception(_('Das angegebene LTI-Tool wurde nicht gefunden.'));
                }
            }
        }
    }

    public function index_action($range_id, $tool_id): void
    {
        //$this->range_id and $this->tool are created in the before-filter.
        if ($this->range_id !== 'global') {
            $link_id = Request::get('link_id');
            $this->link = \LtiResourceLink::findOneBySQL(
                'JOIN `lti_deployments`
                ON `lti_deployments`.`id` = `lti_resource_links`.`deployment_id`
                WHERE
                `lti_deployments`.`tool_id` = :tool_id AND `lti_resource_links`.`id` = :link_id',
                ['tool_id' => $this->tool->id, 'link_id' => $link_id]
            );
        }
    }

    public function add_action($range_id, $tool_id = ''): void
    {
        //NOTE: The parameters are checked and processed in the before_filter.
        $this->addEditHandler();
    }

    public function edit_action($range_id, $tool_id): void
    {
        //NOTE: The parameters are checked and processed in the before_filter.
        $this->addEditHandler();
    }

    protected function addEditHandler(): void
    {
        if (!$this->tool) {
            return;
        }
        if ($this->tool->isNew()) {
            if (!Config::get()->LTI_ALLOW_TOOL_CONFIG_IN_COURSE && $this->range_id !== 'global') {
                throw new AccessDeniedException(
                    _('Die Einrichtung von LTI-Tools in Veranstaltungen ist ausgeschaltet.')
                );
            }
            if ($this->tool->range_id === 'global' && !$this->tool->isEditableByUser()) {
                throw new AccessDeniedException();
            }
            PageLayout::postWarning(_('Bitte beachten Sie das geltende europäische Datenschutzrecht (DSGVO)!'));
        } elseif (!$this->tool->isEditableByUser()) {
            throw new AccessDeniedException();
        } elseif (Request::get('link_id')) {
            //The tool is old and editable by the user. Check if a link exists and load it.
            $this->link = \LtiResourceLink::find(Request::get('link_id'));
        }

        if (Request::isPost()) {
            $this->saveTool();
        }
    }

    /**
     * Handles the saving of a tool.
     */
    protected function saveTool(): void
    {
        CSRFProtection::verifyUnsafeRequest();
        $this->link = null;
        if (Request::get('link_id')) {
            $this->link = \LtiResourceLink::find(Request::get('link_id'));
        }
        //Note: $this->tool is created in the before_filter.
        $new_tool                          = $this->tool->isNew();
        $this->tool->name                  = trim(Request::get('name'));
        $this->tool->description           = Markup::purifyHtml(Markup::markAsHtml(Request::get('description')));
        $this->tool->launch_url            = trim(Request::get('launch_url'));
        $this->tool->terms_of_use_url      = trim(Request::get('terms_of_use_url'));
        $this->tool->privacy_policy_url    = trim(Request::get('privacy_policy_url'));
        $this->tool->data_protection_notes = trim(Request::get('data_protection_notes'));
        $this->tool->lti_version           = Request::get('lti_version', '1.3a');
        if ($this->tool->lti_version === '1.3a') {
            $this->tool->oauth_signature_method = 'sha256';
            $this->tool->oidc_init_url          = trim(Request::get('oidc_init_url'));
            $this->tool->jwks_url               = trim(Request::get('jwks_url'));
            $this->tool->jwks_key_id            = trim(Request::get('jwks_key_id'));
            $this->tool->deep_linking_url       = trim(Request::get('deep_linking_url'));
            $this->tool->deep_linking           = (bool) $this->tool->deep_linking_url;
        } else {
            //LTI 1.0/1.1:
            $this->tool->oauth_signature_method = 'sha1';
            $this->tool->consumer_key           = trim(Request::get('consumer_key'));
            $this->tool->consumer_secret        = trim(Request::get('consumer_secret'));
        }
        $this->tool->send_lis_person   = Request::int('send_lis_person', 0);
        $this->tool->custom_parameters = trim(Request::get('custom_parameters'));
        $tool_public_key               = trim(Request::get('tool_public_key'));

        //Check if the tool has a general deployment. If so, use it. Otherwise, create a new deployment.
        if (!$new_tool) {
            $this->deployment = LtiDeployment::findOneBySQL(
                "`tool_id` = :tool_id AND `purpose` = 'general' ORDER BY `mkdate` ASC",
                ['tool_id' => $this->tool->id]
            );
        }
        if (!$this->deployment) {
            $this->deployment = new LtiDeployment();
            if (!$new_tool) {
                $this->deployment->tool_id = $this->tool->id;
            }
        }

        $errors = $this->tool->validate();
        if ($errors) {
            PageLayout::postError(
                _('Die folgenden Daten zum LTI-Tool sind fehlerhaft:'),
                array_map('htmlReady', $errors)
            );
            return;
        }
        if ($this->tool->lti_version === '1.3a' && !$tool_public_key && !$this->tool->jwks_url) {
            PageLayout::postError(
                _('Es wurde weder ein öffentlicher Schlüssel noch eine JWKS-URL zum Tool angegeben.')
            );
            return;
        }
        if ($this->tool->store() !== false) {
            $this->deployment->tool_id = $this->tool->id;
            $this->deployment->store();
        } else {
            PageLayout::postError(_('Das LTI-Tool konnte nicht gespeichert werden.'));
            return;
        }
        if ($this->range_id !== 'global') {
            $resource_link = null;
            if (!$new_tool) {
                //Create an LTI resource link, if it doesn't exist yet:
                $resource_link = \LtiResourceLink::findOneBySQL(
                        "`deployment_id` = :deployment_id AND `course_id` = :course_id",
                        ['deployment_id' => $this->deployment->id, 'course_id' => $this->range_id]
                    );
            }
            if (!$resource_link) {
                //Either the tool has just been created or the tool existed and it wasn't yet
                //linked to the course. In those both cases, we have to create a new LTI resource link.
                $resource_link = new \LtiResourceLink();
                $resource_link->deployment_id = $this->deployment->id;
                $resource_link->course_id     = $this->range_id;
            }
            $resource_link->description = trim(Request::get('description'));
            $resource_link->title       = $this->tool->name;
            $resource_link->launch_url  = $this->tool->launch_url;
            $document_target = trim(Request::get('document_target'));
            if ($document_target === 'iframe') {
                if (!is_array($resource_link->options)) {
                    $resource_link->options = [];
                }
                $resource_link->options['document_target'] = $document_target;
            } elseif (isset($resource_link->options['document_target'])) {
                unset($resource_link->options['document_target']);
            }
            $resource_link->store();
        }
        if ($this->tool->lti_version === '1.3a' && $tool_public_key) {
            if (!$this->tool->updatePublicKey($tool_public_key)) {
                PageLayout::postError(
                    _('Der öffentliche Schlüssel des LTI-Tools konnte nicht gespeichert werden.')
                );
            }
        } else {
            Keyring::deleteBySQL("`range_type` = 'lti_tool' AND `range_id` = :tool_id", ['tool_id' => $this->tool->id]);
        }

        PageLayout::postSuccess(_('Das LTI-Tool wurde gespeichert.'));
        if (Request::isDialog()) {
            $this->response->add_header('X-Dialog-Close', '1');
            $this->render_nothing();
        } elseif ($this->range_id === 'global') {
            $this->redirect('admin/lti');
        } else {
            $this->redirect('course/lti');
        }
    }

    public function delete_action($range_id, $tool_id): void
    {
        //NOTE: The parameters are checked and processed in the before_filter.
        CSRFProtection::verifyUnsafeRequest();
        $deleted = false;
        $tool_name = $this->tool->name;
        if ($this->tool->range_id === 'global') {
            if ($range_id === 'global') {
                //A global tool shall be deleted globally.
                if (!$this->tool->isEditableByUser()) {
                    throw new AccessDeniedException();
                }
                $deleted = $this->tool->delete();
            } else {
                //A tool shall be deleted from a course: Delete the resource link instead.
                $link = \LtiResourceLink::findOneBySQL(
                    "JOIN `lti_deployments`
                    ON `lti_deployments`.`id` = `lti_resource_links`.`deployment_id`
                    WHERE `lti_deployments`.`tool_id` = :tool_id AND `course_id` = :course_id",
                    ['tool_id' => $this->tool->id, 'course_id' => $range_id]
                );
                if ($link) {
                    $deleted = $link->delete();
                } else {
                    PageLayout::postError(sprintf(_('Das LTI-Tool „%s“ ist in dieser Veranstaltung nicht vorhanden.'), htmlReady($this->tool->name)));
                    return;
                }
            }
        } else {
            //A tool that is used inside a course shall be deleted.
            if (!$this->tool->isEditableByUser()) {
                throw new AccessDeniedException();
            }
            $deleted = $this->tool->delete();
        }
        if ($deleted !== false) {
            PageLayout::postSuccess(sprintf(_('Das LTI-Tool „%s“ wurde gelöscht.'), htmlReady($tool_name)));
        } else {
            PageLayout::postError(_('Das LTI-Tool „%s“ konnte nicht gelöscht werden.'), htmlReady($tool_name));
        }
        if ($range_id === 'global') {
            //Redirect to the admin overview page.
            $this->redirect('admin/lti');
        } elseif (Course::exists($range_id)) {
            //Redirect to the LTI module of the course:
            $this->redirect('course/lti', ['cid' => $range_id]);
        }
    }
}
