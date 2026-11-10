<?php

class QuestionnaireController extends AuthenticatedController
{
    protected $allow_nobody = true; //nobody is allowed

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
        if ($action !== 'courseoverview' && Navigation::hasItem('/contents/questionnaire')) {
            Navigation::activateItem('/contents/questionnaire');
        }
        PageLayout::setTitle(_('Fragebögen'));

        //trigger autoloading:
        class_exists('Vote');
        class_exists('Freetext');
        class_exists('LikertScale');
        class_exists('RangeScale');
        class_exists('QuestionnaireInfo');
        class_exists('QuestionnaireAutomatedData');
        PageLayout::setHelpKeyword('Basis/Votings');
    }

    public function overview_action()
    {
        if (Navigation::hasItem('/contents/questionnaire/overview')) {
            Navigation::activateItem('/contents/questionnaire/overview');
        }
        $this->params = [];
        $this->range_id = null;
        $this->range_type = null;
        if (!$GLOBALS['perm']->have_perm('autor')) {
            throw new AccessDeniedException('Only for logged in users.');
        }
        $this->questionnaires = Questionnaire::findBySQL("user_id = ? ORDER BY chdate DESC", [$GLOBALS['user']->id]);
        foreach ($this->questionnaires as $questionnaire) {
            if (!$questionnaire['visible'] && $questionnaire->isRunning()) {
                $questionnaire->start();
            }
            if ($questionnaire['visible'] && $questionnaire->isStopped()) {
                $questionnaire->stop();
            }
        }
    }

    public function courseoverview_action()
    {
        $this->range_id = Context::getId();

        if (!$this->range_id) {
            throw new CheckObjectException(_('Sie haben kein Objekt gewählt.'));
        }
        $this->range_type = Context::getType();
        if (!$GLOBALS['perm']->have_studip_perm("tutor", $this->range_id)) {
            throw new AccessDeniedException("Only for logged in users.");
        }

        Navigation::activateItem("/course/admin/questionnaires");
        if ($GLOBALS['perm']->have_studip_perm('admin', $this->range_id)) {
            // Ensure the select widget is added last
            NotificationCenter::on('SidebarWillRender', function () {
                $widget = new CourseManagementSelectWidget();
                Sidebar::get()->addWidget($widget);
            });
        }

        $this->statusgruppen = Statusgruppen::findByRange_id($this->range_id);
        $this->questionnaires = Questionnaire::findBySQL(
            "INNER JOIN questionnaire_assignments USING (questionnaire_id) WHERE (questionnaire_assignments.range_id = ? AND questionnaire_assignments.range_type = ?) OR (questionnaire_assignments.range_id IN (?) AND questionnaire_assignments.range_type = 'statusgruppe') ORDER BY questionnaires.chdate DESC",
            [$this->range_id, $this->range_type, array_map(function ($g) { return $g->getId(); }, $this->statusgruppen)]
        );
        foreach ($this->questionnaires as $questionnaire) {
            if (!$questionnaire['visible'] && $questionnaire->isRunning()) {
                $questionnaire->start();
            }
            if ($questionnaire['visible'] && $questionnaire->isStopped()) {
                $questionnaire->stop();
            }
        }
        $this->params = ['redirect' => 'questionnaire/courseoverview'];
        $this->render_action("overview");
    }

    public function thank_you_action()
    {

    }

    public function add_to_context_action()
    {
        $this->statusgruppen = Statusgruppen::findByRange_id(Context::get()->id);
        PageLayout::setTitle(_("Kontext auswählen"));
    }

    public function edit_action($questionnaire_id = null)
    {
        if (!$GLOBALS['perm']->have_perm("autor")) {
            throw new AccessDeniedException("Only for authors.");
        }
        $this->questionnaire = new Questionnaire($questionnaire_id);
        if ($this->questionnaire->isNew()) {
            PageLayout::setTitle(_("Neuer Fragebogen"));
        } else {
            PageLayout::setTitle(_("Fragebogen bearbeiten: ").$this->questionnaire['title']);
        }
        if (!$this->questionnaire->isEditable()) {
            throw new AccessDeniedException(_('Fragebogen ist nicht bearbeitbar.'));
        }
        if ($this->questionnaire->isRunning() && $this->questionnaire->countAnswers() > 0) {
            $this->render_text(
                MessageBox::error(_("Fragebogen ist gestartet worden und kann jetzt nicht mehr bearbeitet werden. Stoppen oder löschen Sie den Fragebogen stattdessen."))
            );
            return;
        }

        $statement = DBManager::get()->prepare("
            SELECT question_id
            FROM questionnaire_questions
            WHERE questionnaire_questions.questionnaire_id = ?
            ORDER BY position ASC
        ");
        $statement->execute(array($this->questionnaire->getId()));
        $this->order = $statement->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    public function store_action($questionnaire_id = null)
    {
        if ($questionnaire_id) {
            $this->questionnaire = Questionnaire::find($questionnaire_id);
        } else {
            $this->questionnaire = new Questionnaire();
        }
        if (!$this->questionnaire || !$this->questionnaire->isEditable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht bearbeitbar.'));
        }
        if ($this->questionnaire->isRunning() && $this->questionnaire->countAnswers() > 0) {
            $this->response->set_status('409', 'Conflict');
            $this->render_json([
                'error' => 'alreadystarted',
                'message' => _("Der Fragebogen ist gestartet worden und kann jetzt nicht mehr bearbeitet werden. Stoppen oder löschen Sie den Fragebogen stattdessen.")
            ]);
            return;
        }
        if (!Request::isPost()) {
            throw new MethodNotAllowedException();
        }
        $questionnaire_data = Request::getArray("questionnaire");
        $this->questionnaire['title'] = $questionnaire_data['title'] ?? '';
        $this->questionnaire['visible'] = $questionnaire_data['visible'] ?? 1;
        $this->questionnaire['anonymous'] = $questionnaire_data['anonymous'] ?? 0;
        $this->questionnaire['resultvisibility'] = $questionnaire_data['resultvisibility'] ?? 'always';
        $this->questionnaire['editanswers'] = $questionnaire_data['editanswers'] ?? 1;
        $this->questionnaire['copyable'] = $questionnaire_data['copyable'] ?? 1;
        $this->questionnaire['startdate'] = is_numeric($questionnaire_data['startdate'])
            ? $questionnaire_data['startdate']
            : ($questionnaire_data['startdate'] ? time() : null);
        $this->questionnaire['stopdate'] = is_numeric($questionnaire_data['stopdate'])
            ? $questionnaire_data['stopdate']
            : null;

        $this->questionnaire['user_id'] = User::findCurrent()->id;
        $questions_data = json_decode(Request::get('questions_data'), true);
        $questions = [];
        foreach ($questions_data as $index => $question_data) {
            $class = $question_data['questiontype'];
            if (!class_exists($class) || !is_subclass_of($class, 'QuestionType')) {
                continue;
            }
            $question = $class::find($question_data['id']);
            if (!$question) {
                $question = new $class();
                $question->setId($question_data['id']);
            } elseif ($question['questionnaire_id'] !== $this->questionnaire->getId()) {
                $question = new $class();
                $question->setId($question->getNewId());
            }
            $question_data['questiondata'] = $question->beforeStoringQuestiondata($question_data['questiondata']);
            unset($question_data['id']);
            $question->setData($question_data);
            $question['position'] = $index;
            $questions[] = $question;
        }
        $this->questionnaire->questions = $questions;
        $this->questionnaire->store();

        //assignments:
        if (Request::get("range_id") && Request::get("range_type")) {
            if (Request::get("range_id") === "start" && !$GLOBALS['perm']->have_perm("root")) {
                throw new AccessDeniedException();
            }
            if (Request::get("range_type") === "course" && !$GLOBALS['perm']->have_studip_perm("tutor", Request::get("range_id"))) {
                throw new AccessDeniedException();
            }
            if (Request::get("range_type") === "user" && Request::get("range_id") !== $GLOBALS['user']->id) {
                throw new AccessDeniedException();
            }
            $assignment = new QuestionnaireAssignment();
            $assignment['questionnaire_id'] = $this->questionnaire->getId();
            $assignment['range_id'] = Request::option("range_id");
            $assignment['range_type'] = Request::get("range_type");
            $assignment['user_id'] = $GLOBALS['user']->id;
            $assignment->store();
        }

        PageLayout::postSuccess(_('Die Daten wurden erfolgreich gespeichert.'));
        $this->render_nothing();
    }


    public function copy_action($from)
    {
        $this->old_questionnaire = Questionnaire::find($from);
        if (!$this->old_questionnaire->isCopyable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht kopierbar.'));
        }
        $this->questionnaire = new Questionnaire();
        $this->questionnaire->setData($this->old_questionnaire->toArray());
        $this->questionnaire->title .= sprintf(' [%s]', _('Kopie'));
        $this->questionnaire->setId($this->questionnaire->getNewId());
        $this->questionnaire['user_id'] = $GLOBALS['user']->id;
        $this->questionnaire['startdate'] = null;
        $this->questionnaire['stopdate'] = null;
        $this->questionnaire['mkdate'] = time();
        $this->questionnaire['chdate'] = time();
        $this->questionnaire->store();
        foreach ($this->old_questionnaire->questions as $question) {
            $new_question = QuestionnaireQuestion::build($question->toArray());
            $new_question->setId($new_question->getNewId());
            $new_question['questionnaire_id'] = $this->questionnaire->getid();
            $new_question['questiondata'] = $question['questiondata'];
            $new_question['mkdate'] = time();
            $new_question->store();
        }
        PageLayout::postSuccess(_('Der Fragebogen wurde kopiert. Wo soll er angezeigt werden?'));
        $this->redirect("questionnaire/context/".$this->questionnaire->getId());
    }

    public function delete_action($questionnaire_id)
    {
        $this->questionnaire = new Questionnaire($questionnaire_id);
        if (!$this->questionnaire->isEditable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht bearbeitbar.'));
        }
        $this->questionnaire->delete();
        PageLayout::postSuccess(_('Der Fragebogen wurde gelöscht.'));


        $redirect = Request::get('redirect');
        if ($redirect && is_internal_url($redirect)) {
            $this->redirect($redirect);
        } else {
            $this->redirect("questionnaire/overview");
        }
    }

    public function bulkdelete_action()
    {
        if (!Request::isPost()) {
            throw new MethodNotAllowedException();
        }
        foreach (Request::getArray("q") as $questionnaire_id) {
            $questionnaire = new Questionnaire($questionnaire_id);
            if ($questionnaire->isEditable()) {
                $questionnaire->delete();
            }
        }
        PageLayout::postSuccess(_('Fragebögen wurden gelöscht.'));
        if (Request::get("range_type") === "user") {
            $this->redirect("questionnaire/overview");
        } elseif (Request::get("range_type") === "course") {
            $this->redirect("questionnaire/courseoverview");
        } elseif (Request::get("range_id") === "start") {
            $this->redirect("start");
        } else {
            $this->redirect("questionnaire/overview");
        }
    }

    public function answer_action($questionnaire_id)
    {
        $this->questionnaire = new Questionnaire($questionnaire_id);
        if (!$this->questionnaire->isViewable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht einsehbar.'));
        }
        object_set_visit($questionnaire_id, 'vote');
        $this->range_type = Request::get("range_type");
        $this->range_id = Request::get("range_id");
        PageLayout::setTitle(sprintf(_("Fragebogen beantworten: %s"), $this->questionnaire->title));
    }

    public function evaluate_action($questionnaire_id)
    {
        $this->questionnaire = new Questionnaire($questionnaire_id);
        if (!$this->questionnaire->isViewable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht einsehbar.'));
        }
        object_set_visit($questionnaire_id, 'vote');
        PageLayout::setTitle(sprintf(_("Fragebogen: %s"), $this->questionnaire->title));

        $this->filtered = [];
        if (Request::submitted('filtered')) {
            $this->filtered[$questionnaire_id] = Request::getArray('filtered');
        }

        if (Request::isAjax() && empty($_SERVER['HTTP_X_DIALOG'])) {
            PageLayout::clearMessages();
        }
    }

    public function stop_action($questionnaire_id)
    {
        $this->questionnaire = new Questionnaire($questionnaire_id);
        if (!$this->questionnaire->isEditable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht bearbeitbar.'));
        }
        $this->questionnaire->stop();

        PageLayout::postSuccess(_('Die Befragung wurde beendet.'));
        if (Request::get("redirect")) {
            $this->redirect(Request::get("redirect"));
        } else {
            $this->redirect("questionnaire/overview");
        }
    }

    public function start_action($questionnaire_id)
    {
        $this->questionnaire = new Questionnaire($questionnaire_id);
        if (!$this->questionnaire->isEditable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht bearbeitbar.'));
        }
        $this->questionnaire->start();

        PageLayout::postSuccess(_("Die Befragung wurde gestartet."));
        if (Request::get("redirect")) {
            $this->redirect(Request::get("redirect"));
        } else {
            $this->redirect("questionnaire/overview");
        }
    }

    public function export_action($questionnaire_id)
    {
        $this->questionnaire = new Questionnaire($questionnaire_id);
        if (!$this->questionnaire->isEditable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht exportierbar.'));
        }
        if (!$this->questionnaire->anonymous) {
            $csv = [[_("Nummer"), _("Benutzername"), _("Nachname"), _("Vorname"), _("E-Mail"), _("Zeitpunkt")]];
        } else {
            $csv = [[_("Nummer"), _("Benutzername"), _("Nachname"), _("Vorname"), _("E-Mail")]];
        }

        $results = [];
        $user_ids = [];
        $timestamps = [];


        foreach ($this->questionnaire->questions as $question) {
            $result = (array) $question->getResultArray();
            foreach ($result as $frage => $r) {
                $csv[0][] = $frage;
                $user_ids = array_merge($user_ids, array_keys($r));
                $user_ids = array_unique($user_ids);
            }
            $results[] = $result;
            if (!$this->questionnaire->anonymous) {
                foreach ($question->answers as $answer) {
                    $timestamps[$answer['user_id']] = $answer['chdate'];
                }
            }
        }

        foreach ($user_ids as $key => $user_id) {
            $user = User::find($user_id);
            if ($user) {
                $csv_line = [$key + 1, $user['username'], $user['Nachname'], $user['Vorname'], $user['Email'], date("d.m.Y H:i:s", $timestamps[$user_id])];
            } elseif ($this->questionnaire->anonymous) {
                $csv_line = [$key + 1, $user_id, '', '', ''];
            } else {
                $csv_line = [$key + 1, $user_id, '', '', '', ''];
            }

            foreach ($results as $result) {
                foreach ($result as $frage => $value) {
                    $csv_line[] = $value[$user_id];
                }
            }
            $csv[] = $csv_line;
        }
        $this->response->add_header('Content-Type', "text/csv");
        $this->response->add_header('Content-Disposition', "attachment; " . encode_header_parameter('filename', $this->questionnaire['title'].'.csv'));
        $this->render_text(array_to_csv($csv));
    }

    public function reset_action(Questionnaire $questionnaire)
    {
        CSRFProtection::verifyUnsafeRequest();

        if (!$questionnaire->isEditable()) {
            throw new AccessDeniedException();
        }

        foreach ($questionnaire->anonymousanswers as $anonymous) {
            $anonymous->delete();
        }
        foreach ($questionnaire->questions as $question) {
            foreach ($question->answers as $answer) {
                $answer->delete();
            }
        }
        PageLayout::postSuccess(_('Antworten wurden zurückgesetzt.'));
        if (Request::get("range_type") === "user") {
            $this->redirect("profile");
        } elseif (Request::get("range_type") === "course") {
            $this->redirect("course/overview");
        } elseif (Request::get("range_id") === "start") {
            $this->redirect("start");
        } else {
            $this->redirect("questionnaire/overview");
        }
    }

    public function context_action($questionnaire_id)
    {
        $this->questionnaire = new Questionnaire($questionnaire_id);
        if (!$this->questionnaire->isEditable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht bearbeitbar.'));
        }
        $this->profile = null;
        $this->public = null;
        $this->start = null;
        foreach ($this->questionnaire->assignments as $relation) {
            if ($relation['range_type'] === "user") {
                $this->profile = $relation;
            }
            if ($relation['range_id'] === "public") {
                $this->public = $relation;
            }
            if ($relation['range_id'] === "start") {
                $this->start = $relation;
            }
        }
        if (Request::isPost()) {
            if (Request::get("user")) {
                if (!$this->profile) {
                    $this->profile = new QuestionnaireAssignment();
                    $this->profile['questionnaire_id'] = $this->questionnaire->getId();
                    $this->profile['range_id'] = $GLOBALS['user']->id;
                    $this->profile['range_type'] = "user";
                    $this->profile['user_id'] = $GLOBALS['user']->id;
                    $this->profile->store();
                }
            } else {
                if ($this->profile) {
                    $this->profile->delete();
                }
            }
            if (Request::get("public")) {
                if (!$this->public) {
                    $this->public = new QuestionnaireAssignment();
                    $this->public['questionnaire_id'] = $this->questionnaire->getId();
                    $this->public['range_id'] = "public";
                    $this->public['range_type'] = "static";
                    $this->public['user_id'] = $GLOBALS['user']->id;
                    $this->public->store();
                }
            } else {
                if ($this->public) {
                    $this->public->delete();
                }
            }
            if ($GLOBALS['perm']->have_perm("root")) {
                if (Request::get("start")) {
                    if (!$this->start) {
                        $this->start = new QuestionnaireAssignment();
                        $this->start['questionnaire_id'] = $this->questionnaire->getId();
                        $this->start['range_id'] = "start";
                        $this->start['range_type'] = "static";
                        $this->start['user_id'] = $GLOBALS['user']->id;
                        $this->start->store();
                    }
                } else {
                    if ($this->start) {
                        $this->start->delete();
                    }
                }
            }
            if (Request::option("add_seminar_id") && $GLOBALS['perm']->have_studip_perm("tutor", Request::option("add_seminar_id"))) {
                $course_assignment = new QuestionnaireAssignment();
                $course_assignment['questionnaire_id'] = $this->questionnaire->getId();
                $course_assignment['range_id'] = Request::option("add_seminar_id");
                $course_assignment['range_type'] = "course";
                $course_assignment['user_id'] = $GLOBALS['user']->id;
                $course_assignment->store();
            }
            if (Request::option("add_institut_id") && $GLOBALS['perm']->have_studip_perm("admin", Request::option("add_institut_id"))) {
                $course_assignment = new QuestionnaireAssignment();
                $course_assignment['questionnaire_id'] = $this->questionnaire->getId();
                $course_assignment['range_id'] = Request::option("add_institut_id");
                $course_assignment['range_type'] = "institute";
                $course_assignment['user_id'] = $GLOBALS['user']->id;
                $course_assignment->store();
            }
            if (Request::option("add_statusgruppe_id") && $GLOBALS['perm']->have_studip_perm("tutor", Statusgruppen::find(Request::option("add_statusgruppe_id"))->range_id)) {
                $course_assignment = new QuestionnaireAssignment();
                $course_assignment['questionnaire_id'] = $this->questionnaire->getId();
                $course_assignment['range_id'] = Request::option("add_statusgruppe_id");
                $course_assignment['range_type'] = "statusgruppe";
                $course_assignment['user_id'] = $GLOBALS['user']->id;
                $course_assignment->store();
            }
            foreach (PluginManager::getInstance()->getPlugins(QuestionnaireAssignmentPlugin::class) as $plugin) {
                $plugin->storeQuestionnaireAssignments($this->questionnaire);
            }

            foreach (Request::getArray('remove_sem') as $seminar_id) {
                if ($GLOBALS['perm']->have_studip_perm('tutor', $seminar_id)) {
                    $course_assignment = QuestionnaireAssignment::findBySeminarAndQuestionnaire($seminar_id, $this->questionnaire->getId());
                    if ($course_assignment) {
                        $course_assignment->delete();
                    }
                }
            }

            foreach (Request::optionArray('remove_inst') as $institute_id) {
                if ($GLOBALS['perm']->have_studip_perm('admin', $institute_id)) {
                    $inst_assignment = QuestionnaireAssignment::findByInstituteAndQuestionnaire($institute_id, $this->questionnaire->id);
                    if ($inst_assignment) {
                        $inst_assignment->delete();
                    }
                }
            }

            foreach (Request::optionArray('remove_statusgruppe') as $statusgruppe_id) {
                if ($GLOBALS['perm']->have_studip_perm("tutor", Statusgruppen::find($statusgruppe_id)->range_id)) {
                    $inst_assignment = QuestionnaireAssignment::findByStatusgruppeAndQuestionnaire($statusgruppe_id, $this->questionnaire->id);
                    if ($inst_assignment) {
                        $inst_assignment->delete();
                    }
                }
            }

            PageLayout::postSuccess(_('Die Bereichszuweisungen wurden gespeichert.'));
            $this->questionnaire->restore();
            $this->questionnaire->resetRelation("assignments");
            $this->response->add_header("X-Dialog-Close", 1);
        }
        PageLayout::setTitle(sprintf(_("Bereiche für Fragebogen: %s"), $this->questionnaire->title));
        // Prepare context for MyCoursesSearch...
        if ($GLOBALS['perm']->have_perm('root')) {
            $parameters = [
                'exclude'   => ['']
            ];
        } elseif ($GLOBALS['perm']->have_perm('admin')) {
            $parameters = [
                'institutes' => array_map(function ($i) {
                    return $i['Institut_id'];
                }, Institute::getMyInstitutes()),
                'exclude'    => ['']
            ];
        } else {
            $parameters = [
                'userid'    => $GLOBALS['user']->id,
                'exclude'   => ['']
            ];
        }
        $this->seminarsearch = MyCoursesSearch::get('Seminar_id', $GLOBALS['perm']->get_perm(), $parameters);

        if ($GLOBALS['perm']->have_perm("root")) {
            $this->statusgruppesearch = new SQLSearch(
                "SELECT statusgruppen.statusgruppe_id, CONCAT(seminare.name, ': ', statusgruppen.name) AS search_name
                    FROM statusgruppen
                        INNER JOIN seminare ON (seminare.Seminar_id = statusgruppen.range_id)
                    WHERE CONCAT(seminare.name, ': ', statusgruppen.name) LIKE :input
                    ORDER BY statusgruppen.name ASC ",
                _("Teilnehmergruppe suchen")
            );
        } elseif ($GLOBALS['perm']->have_perm("admin")) {
            $this->statusgruppesearch = new SQLSearch(
                "SELECT statusgruppen.statusgruppe_id, CONCAT(seminare.name, ': ', statusgruppen.name) AS search_name
                FROM statusgruppen
                    INNER JOIN seminare ON (seminare.Seminar_id = statusgruppen.range_id)
                    INNER JOIN seminar_inst ON (seminar_inst.seminar_id = seminare.Seminar_id)
                    INNER JOIN user_inst ON (seminar_inst.institut_id = user_inst.Institut_id AND inst_perms = 'admin')
                WHERE CONCAT(seminare.name, ': ', statusgruppen.name) LIKE :input
                    AND user_inst.user_id = " . DBManager::get()->quote($GLOBALS['user']->id) . "
                ORDER BY statusgruppen.name ASC ",
                _("Teilnehmergruppe suchen")
            );
        } else {
            $this->statusgruppesearch = new SQLSearch(
                "SELECT statusgruppen.statusgruppe_id, CONCAT(seminare.name, ': ', statusgruppen.name) AS search_name
                    FROM statusgruppen
                        LEFT JOIN seminar_user ON (statusgruppen.range_id = seminar_user.Seminar_id AND seminar_user.status IN ('tutor', 'dozent'))
                        LEFT JOIN seminare ON (seminare.Seminar_id = statusgruppen.range_id)
                    WHERE seminar_user.user_id = " . DBManager::get()->quote($GLOBALS['user']->id) . "
                        AND CONCAT(seminare.name, ': ', statusgruppen.name) LIKE :input
                    ORDER BY statusgruppen.name ASC ",
                _("Teilnehmergruppe suchen")
            );
        }
    }

    public function widget_action($range_id, $range_type = "course")
    {
        if (get_class($this->parent_controller) === __CLASS__) {
            throw new RuntimeException('widget_action must be relayed');
        }
        $this->range_id = $range_id;
        $this->range_type = $range_type;
        if (in_array($this->range_id, ["public", "start"])) {
            $this->range_type = "static";
        }
        $this->statusgruppen_ids = [];
        if (in_array($this->range_type, ["course", "institute"])) {
            if ($this->range_id && $GLOBALS['perm']->have_studip_perm("tutor", $this->range_id)) {
                $statusgruppen = Statusgruppen::findByRange_id($this->range_id);
            } else {
                $statusgruppen = Statusgruppen::findBySQL("INNER JOIN statusgruppe_user USING (statusgruppe_id) WHERE statusgruppen.range_id = ? AND statusgruppe_user.user_id = ? ", [
                    Context::get()->id,
                    $GLOBALS['user']->id
                ]);
            }
            $this->statusgruppen_ids = array_map(function ($g) { return $g->getId(); }, $statusgruppen);
        }
        $statement = DBManager::get()->prepare("
            SELECT questionnaires.*
            FROM questionnaires
                INNER JOIN questionnaire_assignments ON (questionnaires.questionnaire_id = questionnaire_assignments.questionnaire_id)
            WHERE (
                    questionnaire_assignments.range_id = :range_id
                    AND questionnaire_assignments.range_type = :range_type
                ) OR (
                    questionnaire_assignments.range_id IN (:statusgruppe_id)
                    AND questionnaire_assignments.range_type = 'statusgruppe'
                )
                AND startdate <= UNIX_TIMESTAMP()
            ORDER BY questionnaires.mkdate DESC
        ");
        $statement->execute([
            'range_id' => $this->range_id,
            'range_type' => $this->range_type,
            'statusgruppe_id' => $this->statusgruppen_ids
        ]);
        $this->questionnaire_data = $statement->fetchAll(PDO::FETCH_ASSOC);
        $stopped_visible = 0;
        foreach ($this->questionnaire_data as $i => $questionnaire) {
            $one = Questionnaire::buildExisting($questionnaire);
            if (!$questionnaire['visible'] && $one->isRunning()) {
                $one->start();
            }
            if ($questionnaire['visible'] && $one->isStopped()) {
                $one->stop();
            }
            if ($one->isStopped() && $one->resultsVisible()) {
                $stopped_visible++;
            }
            if (($one->isStopped() || !$one->isViewable()) && (!$one->resultsVisible() || !Request::get("questionnaire_showall"))) {
                unset($this->questionnaire_data[$i]);
                continue;
            }

            object_set_visit($questionnaire['questionnaire_id'], 'vote');
        }
        if (in_array($this->range_type, ["course", "institute"])
                && $this->range_id
                && !$GLOBALS['perm']->have_studip_perm("tutor", $this->range_id)
                && !($stopped_visible || count($this->questionnaire_data))) {
            $this->render_nothing();
        }
    }


    /**
     * The assign action allows assigning multiple questionnaires
     * to multiple courses.
     */
    public function assign_action()
    {
        PageLayout::setTitle(_('Fragebögen zuordnen'));

        if (Navigation::hasItem('/contents/questionnaire/assign')) {
            Navigation::activateItem('/contents/questionnaire/assign');
        }

        if (!$GLOBALS['perm']->have_perm('admin')) {
            throw new AccessDeniedException();
        }

        $user = User::findCurrent();

        $this->available_semesters = Semester::findBySql(
            'TRUE ORDER BY beginn DESC'
        );
        $this->available_institutes = Institute::getMyInstitutes($user->id);
        $this->available_course_types = SemType::getTypes();
        $this->selected_questionnaires = [];

        $this->step = 0;

        //We accept only forms which have been sent via POST!
        if (Request::isPost()) {
            CSRFProtection::verifyUnsafeRequest();

            if (Request::submitted('search_courses')) {
                $this->step = 1;
            } elseif (Request::submitted('select_courses')) {
                $this->step = 2;
            } elseif (Request::submitted('copy') || Request::submitted('assign')) {
                $this->step = 3;
            }

            if ($this->step >= 1) {
                //Step 1: Search for courses.
                $this->semester_id = Request::get('semester_id');
                $this->institute_id = Request::get('institute_id');
                $this->course_type_id = Request::get('course_type_id');

                if ($this->institute_id) {
                    //Check if the user has at least admin permissions on the selected
                    //institute. If not, then don't process the submitted data
                    //any further:
                    if (!$GLOBALS['perm']->have_studip_perm('admin', $this->institute_id, $user->id)) {
                        PageLayout::postError(
                            _('Sie haben unzureichende Berechtigungen an der gewählten Einrichtung! Bitte wählen Sie eine andere Einrichtung!')
                        );
                        $this->step = 0;
                        return;
                    }
                }

                $this->semester = Semester::find($this->semester_id);
                if ($this->institute_id) {
                    $this->institute = Institute::find($this->institute_id);
                }

                $this->error_message = '';

                if (!$this->semester) {
                    PageLayout::postError(_('Es wurde kein gültiges Semester ausgewählt!'));
                    $this->step = 0;
                    return;
                }

                //Search courses matching the search criteria:

                $sql = 'LEFT JOIN semester_courses ON (semester_courses.course_id = seminare.Seminar_id) WHERE (semester_courses.semester_id = :semester_id OR semester_courses.semester_id IS NULL) ';
                $sql_array = [
                    'semester_id' => $this->semester->id
                ];

                if ($this->institute) {
                    $sql .= 'AND institut_id = :institute_id ';
                    $sql_array['institute_id'] = $this->institute->id;
                }
                if ($this->course_type_id) {
                    $sql .= 'AND status = :course_type_id ';
                    $sql_array['course_type_id'] = $this->course_type_id;
                }

                $sql .= 'ORDER BY name ASC';

                $courses = Course::findBySql($sql, $sql_array);

                $this->found_courses = [];

                //We can only add those courses where the current user
                //has at least admin permissions:
                foreach ($courses as $course) {
                    if ($GLOBALS['perm']->have_studip_perm('admin', $course->id)) {
                        $this->found_courses[] = $course;
                    }
                }
            }

            if ($this->step >= 2) {
                //Step 2: Courses have been selected. Search for questionnaires.
                $this->course_id_list = Request::getArray('course_id_list');
                $this->selected_courses = Course::findMany($this->course_id_list);
                if (!$this->selected_courses) {
                    PageLayout::postError(
                        _('Es wurde keine Veranstaltung ausgewählt! Bitte mindestens eine Veranstaltung auswählen!')
                    );
                    $this->step = 1;
                    return;
                }
                $courses_without_perms = [];
                foreach ($this->selected_courses as $course) {
                    if (!$GLOBALS['perm']->have_studip_perm('admin', $course->id)) {
                        $courses_without_perms[] = $course->getFullName();
                    }
                }

                if ($courses_without_perms) {
                    PageLayout::postError(
                        ngettext(
                            'Ihre Berechtigungen reichen nicht, um Fragebögen zu der folgenden Veranstaltung zuweisen zu können:',
                            'Ihre Berechtigungen reichen nicht, um Fragebögen zu den folgenden Veranstaltungen zuweisen zu können:',
                            count($courses_without_perms)
                        ),
                        $courses_without_perms
                    );
                    $this->step = 1;
                    return;
                }

                //Get only the questionnaires of the current user:
                $this->questionnaires = Questionnaire::findBySql(
                    'user_id = :user_id ORDER BY mkdate DESC',
                    [
                        'user_id' => $user->id
                    ]
                );
            }

            if ($this->step >= 3) {
                //Step 3: Questionnaires have been selected. Assign them
                //to the found courses.
                $this->selected_questionnaire_ids = Request::getArray('selected_questionnaire_ids');
                $this->delete_dates = Request::get('delete_dates');
                $this->copy_questionnaires = Request::submitted('copy');

                //Get only the questionnaires of the current user:
                $this->selected_questionnaires = Questionnaire::findBySql(
                    'user_id = :user_id AND questionnaire_id IN ( :questionnaire_ids )',
                    [
                        'user_id' => $user->id,
                        'questionnaire_ids' => $this->selected_questionnaire_ids
                    ]
                );
                if (!$this->selected_questionnaires) {
                    PageLayout::postError(
                        _('Es wurde kein Fragebogen ausgewählt! Bitte mindestens einen Fragebogen auswählen!')
                    );
                    $this->step = 2;
                    return;
                }

                $errors = [];
                foreach ($this->selected_courses as $course) {
                    foreach ($this->selected_questionnaires as $questionnaire) {
                        if ($this->copy_questionnaires) {
                            //The questionnaire shall be copied and only the copy
                            //shall be placed inside the course.

                            //The following code to copy a questionnaire was copied
                            //from the questionnaire controller's copy_action method.
                            //If that method changes please keep this code "in sync"
                            //with the code from the questionnaire controller to avoid
                            //misbehavior of this plugin.
                            $new_questionnaire = new Questionnaire();
                            $new_questionnaire->setData($questionnaire->toArray());
                            $new_questionnaire->setId($new_questionnaire->getNewId());
                            $new_questionnaire->user_id = $user->id;
                            //Contrary to the original code, copied questionnaires
                            //may still contain the start date and end date of the
                            //original questionnaire.
                            if ($this->delete_dates) {
                                $new_questionnaire->startdate = null;
                                $new_questionnaire->stopdate = null;
                            }

                            $new_questionnaire->mkdate = time();
                            $new_questionnaire->chdate = time();
                            $new_questionnaire->store();
                            foreach ($questionnaire->questions as $question) {
                                $new_question = QuestionnaireQuestion::build($question->toArray());
                                $new_question->setId($new_question->getNewId());
                                $new_question->questionnaire_id = $new_questionnaire->id;
                                $new_question->mkdate = time();
                                $new_question->store();
                            }

                            $assignment = new QuestionnaireAssignment();
                            $assignment->questionnaire_id = $new_questionnaire->id;
                            $assignment->range_id = $course->id;
                            $assignment->range_type = 'course';
                            $assignment->user_id = $user->id;
                            if (!$assignment->store()) {
                                $errors[] = sprintf(
                                    _('Fragebogen "%1$s" konnte nicht in Veranstaltung "%2$s" kopiert werden!'),
                                    $new_questionnaire->title,
                                    $course->name
                                );
                            }
                        } else {
                            //The questionnaire shall be assigned to the course.
                            //We must check if the association already exists.
                            $assignment = QuestionnaireAssignment::findBySeminarAndQuestionnaire(
                                $course->id,
                                $questionnaire->id
                            );

                            if (!$assignment) {
                                //The assignment doesn't exist: create it:
                                $assignment = new QuestionnaireAssignment();
                                $assignment->questionnaire_id = $questionnaire->id;
                                $assignment->range_id = $course->id;
                                $assignment->range_type = 'course';
                                $assignment->user_id = $user->id;
                                if (!$assignment->store()) {
                                    $errors[] = sprintf(
                                        _('Fragebogen "%1$s" konnte nicht zu Veranstaltung "%2$s" zugeordnet werden!'),
                                        htmlReady($questionnaire->title),
                                        htmlReady($course->name)
                                    );
                                }
                            }
                        }
                    }
                }

                if ($errors) {
                    PageLayout::postError(
                        _('Beim Zuordnen traten Fehler auf:'),
                        $errors
                    );
                } else {
                    PageLayout::postSuccess(
                        _('Alle gewählten Fragebögen wurden den gewählten Veranstaltungen zugeordnet!')
                    );
                }
            }
        }
    }

    public function submit_action($questionnaire_id)
    {
        $this->questionnaire = new Questionnaire($questionnaire_id);
        if (!$this->questionnaire->isViewable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht einsehbar.'));
        }
        $answered_before = $this->questionnaire->isAnswered();
        if ($this->questionnaire->isAnswerable()) {
            $pseudonomous_id = 'q'.substr(md5(uniqid()), 1);
            foreach ($this->questionnaire->questions as $question) {
                $answer = $question->createAnswer();
                if (!$answer['question_id']) {
                    $answer['question_id'] = $question->getId();
                }
                $answer['user_id'] = $GLOBALS['user']->id !== "nobody" ? $GLOBALS['user']->id : $pseudonomous_id;
                if (!$answer['answerdata']) {
                    $answer['answerdata'] = [];
                }
                if ($this->questionnaire['anonymous']) {
                    $answer['user_id'] = $pseudonomous_id;
                    $answer['chdate'] = 1;
                    $answer['mkdate'] = 1;
                    $this->anonAnswers[] = $answer->toArray();
                }
                $answer->store();
            }
            if ($this->questionnaire['anonymous'] && ($GLOBALS['user']->id !== "nobody")) {
                $anonymous_answer = new QuestionnaireAnonymousAnswer();
                $anonymous_answer['questionnaire_id'] = $this->questionnaire->getId();
                $anonymous_answer['user_id'] = $GLOBALS['user']->id;
                $anonymous_answer->store();
            }
            if (!$answered_before && !$this->questionnaire['anonymous'] && ($this->questionnaire['user_id'] !== $GLOBALS['user']->id)) {
                setTempLanguage($this->questionnaire->user_id);

                PersonalNotifications::add(
                    $this->questionnaire->user_id,
                    URLHelper::getURL('dispatch.php/questionnaire/evaluate/' . $this->questionnaire->id, [], true),
                    sprintf(_("%s hat an der Befragung '%s' teilgenommen."), $GLOBALS['user']->getFullName(), $this->questionnaire->title),
                    "questionnaire_" . $this->questionnaire->id,
                    Icon::create('vote'),
                    true
                );

                restoreLanguage();
            }
        }

        if (Request::isAjax()) {
            $this->response->add_header("X-Dialog-Close", "1");
            $this->response->add_header("X-Dialog-Execute", "STUDIP.Questionnaire.updateWidgetQuestionnaire");
            $this->render_template("questionnaire/evaluate");
        } elseif (Request::get("range_type") === "user") {
            PageLayout::postMessage(MessageBox::success(_("Danke für die Teilnahme!")));
            $this->redirect("profile?username=".get_username(Request::option("range_id")));
        } elseif (Request::get("range_type") === "course") {
            PageLayout::postMessage(MessageBox::success(_("Danke für die Teilnahme!")));
            $this->redirect("course/overview?cid=".Request::option("range_id"));
        } elseif (Request::get("range_id") === "start") {
            PageLayout::postMessage(MessageBox::success(_("Danke für die Teilnahme!")));
            $this->redirect("start");
        } else {
            PageLayout::postMessage(MessageBox::success(_("Danke für die Teilnahme!")));
            if ($GLOBALS['perm']->have_perm("autor")) {
                $this->redirect("questionnaire/overview");
            } else {
                $this->redirect("questionnaire/thank_you");
            }
        }
    }

    public function export_file_action(Questionnaire $questionnaire)
    {
        if (!$questionnaire->isCopyable()) {
            throw new AccessDeniedException(_('Der Fragebogen ist nicht kopierbar.'));
        }
        $this->response->add_header('Content-Disposition', 'attachment; ' . encode_header_parameter('filename', $questionnaire['title'].".json"));

        $rawdata = $questionnaire->exportAsFile();
        $file_data = json_encode($rawdata);
        $this->response->add_header('Content-Length', strlen($file_data));
        $this->render_json($rawdata);
    }
}
