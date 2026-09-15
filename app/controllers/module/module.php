<?php
/**
 * @author      Peter Thienel <thienel@data-quest.de>
 * @license     GPL2 or any later version
 * @since       3.5
 */

class Module_ModuleController extends MVVController
{
    public $filter = [];
    protected $show_sidebar_search = false;
    protected $show_sidebar_filter = false;

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
        // set navigation
        Navigation::getItem('mvv/module/module')
            ->setActive(static::class === Module_ModuleController::class);
        Navigation::getItem('mvv/module/institutes')
            ->setActive(static::class === Module_InstituteController::class);
        $this->filter = $this->getFilters();

        $this->action = $action;
        $this->modul_id = '';
        $this->details_url = '';
        $this->modulteil_id = '';

        PageLayout::addSqueezePackage('statusgroups');
    }

    public function index_action()
    {
        PageLayout::setTitle(_('Verwaltung der Module - Alle Module'));
        $this->initPageParams();
        $this->initSearchParams('module');

        $this->sortby = $this->sortby ?: 'code';
        $this->order  = $this->order ?: 'ASC';

        $filters = $this->getSearchFilters();

        //get data
        $this->module = Modul::getAllEnriched(
            $this->sortby,
            $this->order,
            self::$items_per_page,
            self::$items_per_page * (($this->page ?: 1) - 1),
            $filters
        );

        if (!empty($this->filter)) {
            $this->search_result['Modul'] = $this->module->pluck('id');
        }

        if (count($this->module) === 0) {
            if (count($this->filter) || $this->search_term) {
                PageLayout::postInfo(_('Es wurden keine Module gefunden.'));
            } else {
                PageLayout::postInfo(_('Es wurden noch keine Module angelegt.'));
            }
        }
        $this->count = Modul::getCount($filters);
        $this->show_sidebar_search = true;
        $this->show_sidebar_filter = true;
        $this->modul_id = Request::option('modul_id');
        $this->setSidebar();
    }

    public function select_module_language_action()
    {
        $this->content_languages = $GLOBALS['CONTENT_LANGUAGES'];
        $this->default_language = Config::get()->MVV_DEFAULT_LANGUAGE;
        PageLayout::setTitle(_('Ausgabeprache wählen'));
        $this->render_template('module/module/select_module_language');
    }

    public function modul_action($modul_id = null, $institut_id = null)
    {
        $own_institutes = MvvPerm::getOwnInstitutes();

        if (!isset($this->modul)) {
            $this->modul = Modul::find($modul_id);
            if (!$this->modul) {
                $this->modul = new Modul();
                $this->modul->setNewId();
            }
        }
        if ($institut_id) {
            $institut = new Institute($institut_id);
            if (!$institut->isNew()) {
                $this->institut_id = $institut->getId();
            }
        }

        $this->contacts = $this->modul->contact_assignments;
        $this->range_id = $modul_id;
        $this->range_type = 'Modul';

        $this->setSidebar();
        $sidebar = Sidebar::get();

        if ($this->modul->isNew()) {
            PageLayout::setTitle(_('Neues Modul anlegen'));
            $success_message = ('Das Modul "%s" wurde angelegt.');
            $language = Request::option('display_language');
            $content_languages = $GLOBALS['CONTENT_LANGUAGES'];
            if (!empty($content_languages[$language])) {
                $this->display_language = $language;
            } else {
                $this->display_language = Config::get()->MVV_DEFAULT_LANGUAGE;
            }
            $this->modul->original_language = $this->display_language;
            $this->deskriptor = $this->modul->getDeskriptor();
            $this->reset_search('Modul');
            if (!$modul_id) {
                PageLayout::postInfo(sprintf(
                    _('Sie legen ein neues Modul an. Das Modul wird zunächst in der Ausgabesprache <em>%s</em> angelegt (Originalsprache).'),
                    $GLOBALS['CONTENT_LANGUAGES'][$this->display_language]['name']
                ));
            }
            // set default language of instruction
            if ($GLOBALS['MVV_MODUL']['SPRACHE']['default']) {
                $this->modul->assignLanguagesOfInstruction([
                    $GLOBALS['MVV_MODUL']['SPRACHE']['default']
                ]);
            }
        } else {
            $this->display_language = Request::option(
                'display_language',
                $this->modul->original_language
            );

            $this->deskriptor = $this->modul->getDeskriptor();
            $this->translations = $this->deskriptor->getAvailableTranslations($this->modul->original_language);
            $content_languages = $GLOBALS['CONTENT_LANGUAGES'];
            if (!in_array($this->display_language, $this->translations)) {
                PageLayout::postInfo(
                    sprintf(
                        _('Modul: <em>%1$s</em> in der Ausgabesprache <em>%2$s</em> neu anlegen.'),
                        $this->modul->getDisplayName(),
                        $content_languages[$this->display_language]['name']
                    )
                );
            }

            $success_message = _('Das Modul "%s" wurde geändert.');
            $views_widget = new ViewsWidget();
            $views_widget->setTitle(_('Ausgabesprache'));
            $languages = $this->translations;
            $content_languages = array_merge(array_flip($languages), $GLOBALS['CONTENT_LANGUAGES']);
            foreach ($content_languages as $code => $language) {
                $views_widget->addLink($language['name']
                    .  ' (' . ($code === $this->modul->original_language ? _('Originalfassung') . ', ' : '')
                    . (in_array($code, $languages) ? _('bearbeiten') : _('neu anlegen')) . ')',
                    URLHelper::getLink($this->modulURL($this->modul->id, $this->institut_id),
                        ['display_language' => $code]
                    )
                )->setActive($code === $this->display_language);
            }
            $sidebar->insertWidget($views_widget, 'actions','language');

            /** @var ActionsWidget $action_widget */
            $action_widget = $sidebar->getWidget('actions');
            $action_widget->addLink(
                _('Download der Modulbeschreibung'),
                $this->url_for('module/download/details', $this->modul->id, $this->display_language),
                Icon::create('file-word')
            );
            $action_widget->addLink(
                _('Modulbeschreibung als PDF'),
                $this->url_for('module/download/details/' . $this->modul->id . '/' . $this->display_language, ['pdf' => '1']),
                Icon::create('file-pdf')
            );
            $action_widget->addLink(
                _('Vergleich mit anderem Modul'),
                $this->diff_selectURL($this->modul->id),
                Icon::create('learnmodule'),
                ['data-dialog' => 'size=auto']
            );

            if ($this->modul->stat === 'planung' && MvvPerm::haveFieldPermStat($this->modul)) {
                $action_widget->addLink(
                    _('Modul genehmigen'),
                    $this->approveURL($this->modul->id),
                    Icon::create('accept'),
                    ['data-dialog' => 'size=auto;buttons=false']
                );
            }

            $action_widget->addLink(
                _('Log-Einträge dieses Moduls'),
                $this->url_for('shared/log_event/show/Modul/' . $this->modul->id,
                    ['object2_type' => 'ModulDeskriptor', 'object2_id' => $this->deskriptor->id]
                ),
                Icon::create('log')
            )->asDialog();

            // list all variants
            $variants = $this->modul->getVariants();
            if (count($variants)) {
                $widget = new TemplateWidget(
                    _('Varianten'),
                    $this->get_template_factory()->open('shared/modul_variants'),
                    [
                        'variants' => $variants,
                        'link'     => $this->modulURL(),
                    ]
                );
                $sidebar->addWidget($widget, 'variants');
            }
        }

        $this->semester = array_reverse(Semester::getAll());
        $this->def_lang = $this->display_language === $this->modul->original_language;
        I18NString::setDefaultLanguage($this->modul->original_language);
        I18NString::setContentLanguage($this->display_language);
        if (!$this->def_lang) {
            $action_widget = $sidebar->getWidget('actions');
            $action_widget->addLink(
                _('Alle Texte der Originalfassung anzeigen'),
                '#', Icon::create('consultation'),
                ['class' => 'mvv-show-all-original']
            );
        }

        if (Request::isPost()) {
            CSRFProtection::verifyUnsafeRequest();
            $stored = false;
            if ($this->def_lang) {
                $this->modul->variante = Request::option('modul_item');
                $this->modul->flexnow_modul = trim(Request::get('flexnow_modul'));
                $this->modul->code = trim(Request::get('code'));
                $this->modul->start = Request::option('start');
                $this->modul->end = Request::option('end') ?: null;
                $this->modul->beschlussdatum = strtotime(trim(Request::get('beschlussdatum')));
                $this->modul->assignLanguagesOfInstruction(Request::optionArray('language_items'));
                $this->modul->dauer = trim(Request::get('dauer'));
                if (Request::get('kap_unbegrenzt')) {
                    $this->modul->kapazitaet = '';
                } else {
                    $kapazitaet = trim(Request::get('kapazitaet'));
                    $this->modul->kapazitaet = $kapazitaet === '' ? null : $kapazitaet;
                }
                $this->modul->kp = Request::float('kp');
                $this->modul->wl_selbst = Request::int('wl_selbst');
                $this->modul->wl_pruef = Request::int('wl_pruef');
                $this->modul->pruef_ebene = Request::option('pruef_ebene');
                $this->modul->faktor_note = StringToFloat(trim(Request::get('faktor_note')));
                $this->modul->stat = Request::option('status', $GLOBALS['MVV_MODUL']['STATUS']['default']);
                $this->modul->kommentar_status = trim(Request::get('kommentar_status'));
                $this->modul->assignInstitutes(Request::optionArray('institutes_items'));
                $this->modul->assignResponsibleInstitute(Request::option('responsible_item'));
                $this->modul->fassung_nr = Request::int('fassung_nr');
                $this->modul->fassung_typ = Request::option('fassung_typ');
                $this->modul->version = trim(Request::get('version'));
                $this->modul->verantwortlich = trim(Request::get('verantwortlich'));
            }

            $deskriptor_fields = ['bezeichnung', 'verantwortlich',
                'voraussetzung', 'kompetenzziele', 'inhalte', 'literatur',
                'links', 'kommentar', 'turnus', 'kommentar_kapazitaet',
                'kommentar_wl_selbst', 'kommentar_wl_pruef', 'kommentar_sws',
                'kommentar_note', 'pruef_vorleistung', 'pruef_leistung',
                'pruef_wiederholung', 'ersatztext'];

            foreach ($deskriptor_fields as $deskriptor_field) {
                if ($this->deskriptor->isI18nField($deskriptor_field)) {
                    if ($this->display_language === $this->modul->original_language) {
                        $this->deskriptor->$deskriptor_field->setOriginal(
                            trim(Request::get($deskriptor_field))
                        );
                    } else {
                        $this->deskriptor->$deskriptor_field->setLocalized(
                            trim(Request::get($deskriptor_field)),
                            $this->display_language
                        );
                    }
                } else {
                    $this->deskriptor->setValue(
                        $deskriptor_field,
                        trim(Request::get($deskriptor_field))
                    );
                }
            }

            // update datafields
            foreach (Request::getArray('datafields') as $df_key => $df_value) {
                $df = $this->deskriptor->datafields->findOneBy('datafield_id', $df_key);
                if ($df) {
                    $tdf = $df->getTypedDatafield();
                    $tdf->setContentLanguage($this->display_language);
                    $tdf->setValueFromSubmit($df_value);
                    $tdf->store();
                }
            }
            if (Request::submitted('store')) {
                try {
                    $this->modul->verifyPermission();
                    $stored = $this->modul->store();
                } catch (InvalidValuesException $e) {
                    PageLayout::postError(htmlReady($e->getMessage()));
                }
                if ($stored !== false) {
                    PageLayout::postSuccess(sprintf(
                        $success_message,
                        htmlReady($this->modul->getDisplayName())
                    ));
                    $this->redirect($this->indexURL(['modul_id' => $this->modul->id]) . "#modul_{$this->modul->id}");
                    return;
                }
            }
        }

        $query = "SELECT modul_id, CONCAT(mmd.bezeichnung, ' (', code, ')') as name FROM mvv_modul mm
                LEFT JOIN mvv_modul_deskriptor mmd USING(modul_id)
                WHERE (code LIKE :input OR mmd.bezeichnung LIKE :input)
                AND mm.modul_id <> " . DBManager::get()->quote($this->modul->id ?: ''). " ORDER BY name ASC";
        $sql_search_modul = new SQLSearch($query, _('Modul suchen'));
        $this->qs_id_module = md5(serialize($sql_search_modul));
        $this->search_modul =
                QuickSearch::get('modul', $sql_search_modul)
                ->fireJSFunctionOnSelect('STUDIP.MVV.Search.addSelected')
                ->noSelectbox();

        $sql_search_user = new PermissionSearch(
            'user',
            _('Person suchen'),
            'user_id',
            ['permission' => words('tutor dozent admin'), 'exclude_user' => '']
        );
        $this->qs_id_users = md5(serialize($sql_search_user));
        $qs_users = QuickSearch::get('users', $sql_search_user);
        $this->qs_frame_id_users = $qs_users->getId();
        $this->search_users = $qs_users
                ->fireJSFunctionOnSelect('STUDIP.MVV.Search.addSelected')
                ->noSelectbox();

        $query = 'SELECT DISTINCT Institute.Institut_id, Institute.Name AS name
                    FROM Institute
                    LEFT JOIN range_tree ON (range_tree.item_id = Institute.Institut_id)
                    WHERE (Institute.Name LIKE :input OR range_tree.name LIKE :input )';
        if (count($own_institutes)) {
            $query .= 'AND Institut_id IN('
                    . DBManager::get()->quote($own_institutes) . ') ';
        }
        $query .= ' ORDER BY Institute.Name';
        $sql_search_responsible = new SQLSearch($query, _('Einrichtung suchen'),
                'Institut_id');
        $this->qs_id_responsible = md5(serialize($sql_search_responsible));
        $qs_responsible = QuickSearch::get('responsible', $sql_search_responsible);
        $this->qs_frame_id_responsible = $qs_responsible->getId();
        $this->search_responsible = $qs_responsible
                ->fireJSFunctionOnSelect('STUDIP.MVV.Search.addSelected')
                ->noSelectbox();

        $query = 'SELECT DISTINCT Institute.Institut_id, Institute.Name AS name
                FROM Institute
                LEFT JOIN range_tree ON (range_tree.item_id = Institute.Institut_id)
                WHERE (Institute.Name LIKE :input
                OR range_tree.name LIKE :input )
                ORDER BY Institute.Name';
        $sql_search_institutes = new SQLSearch($query, _('Einrichtung suchen'),
                'Institut_id');
        $this->qs_id_institutes = md5(serialize($sql_search_institutes));
        $qs_institutes = QuickSearch::get('institutes', $sql_search_institutes);
        $this->qs_frame_id_institutes = $qs_institutes->getId();
        $this->search_institutes = $qs_institutes
                ->fireJSFunctionOnSelect('STUDIP.MVV.Search.addSelected')
                ->noSelectbox();

        $this->cancel_url = $this->indexURL();

        $this->addPermissionObjects($this->modul, $this->display_language);
        $this->render_template('module/module/modul', $this->layout);
    }

    /**
     * Deletes a descriptor from module
     *
     * @param string $deskriptor_id
     * @throws Trails\Exception
     */
    public function delete_modul_deskriptor_action($deskriptor_id, $language)
    {
        $deskriptor = ModulDeskriptor::find($deskriptor_id);
        if (is_null($deskriptor)) {
            throw new Trails\Exception(404, _('Unbekannter Deskriptor'));
        }
        $def_lang = $deskriptor->modul->getDefaultLanguage();
        if ($language === $def_lang) {
            throw new Trails\Exception(403, _('Ein Deskriptor in der Original-Sprache kann nicht gelöscht werden.'));
        }
        if (Request::submitted('delete')) {
            CSRFProtection::verifyUnsafeRequest();
            if ($deskriptor->deleteTranslation($language)) {
                PageLayout::postSuccess(sprintf(
                    _('Der Deskriptor "%s" des Moduls "%s" wurde gelöscht!'),
                    htmlReady($deskriptor->getDisplayName()),
                    htmlReady($deskriptor->modul->getDisplayName())
                ));
            }
        }
        $this->redirect($this->indexURL());
    }

    public function assignments_action($modul_id)
    {
        $this->modul = Modul::find($modul_id);
        PageLayout::setTitle(_('Zuordnungen'));
    }

    public function description_action($modul_id)
    {
        $response = $this->relay('shared/modul/description/' . $modul_id);
        if (Request::isXhr()) {
            $this->render_text($response->body);
        } else {
            $this->render_nothing();
        }
    }

    public function approve_action($modul_id)
    {
        $this->modul_id = $modul_id;
        $this->modul = Modul::find($modul_id);

        if (Request::submitted('approval')) {
            $modul = Modul::get($modul_id);
            $modul->stat = 'genehmigt';
            $stored = $modul->store(false);
            if ($stored) {
                PageLayout::postSuccess(sprintf(
                    _('Modul "%s" genehmigt!'),
                    htmlReady($this->modul->getDisplayName())
                ));
                $this->redirect($this->detailsURL($modul_id));
                return;
            }
        }
        PageLayout::setTitle(_('Modul genehmigen'));
        $this->setSidebar();
        $this->render_template('module/module/approve', $this->layout);
    }

    public function copy_form_action($modul_id)
    {
        // find the latest version of the given module
        $modul = Modul::find($modul_id);
        $this->modul = Modul::findOneBySql('LEFT JOIN semester_data '
            . 'ON start = semester_id '
            . "WHERE modul_id = ? OR quelle = ? AND stat = 'genehmigt' "
            . 'ORDER BY beginn DESC LIMIT 1',
            [$modul->id, $modul->quelle]);
        if (!$this->modul) {
            PageLayout::postError(_('Unbekanntes Modul'));
        } else {
            $this->perm = MvvPerm::get($this->modul);
            $this->submit_url = $this->copyURL($this->modul->id);
            $this->cancel_url = $this->indexURL();
        }
        PageLayout::setTitle(_('Modul kopieren'));
    }


    public function copy_action($modul_id)
    {
        $modul = Modul::find($modul_id);
        if (!$modul) {
            PageLayout::postError(_('Unbekanntes Modul'));
        } else {
            $perm = MvvPerm::get($modul);
            if (Request::submitted('copy')) {
                CSRFProtection::verifyUnsafeRequest();
                // Assign the copied module to the same versions of studycourses
                // as the original?
                $copy_assignments_to_versions = Request::get('copy_assignments');
                $copy = $modul->copy(true, $copy_assignments_to_versions);
                // Kopie kennzeichnen (eindeutiger Code und Titel des Moduls in
                // allen Deskriptoren
                $copy->code = $modul->code;
                $copy->beschlussdatum = null;
                $current = Semester::find($copy->end);
                $next = Semester::findNext($current->beginn);
                if (is_array($next) && !empty($next)) {
                    $next = array_pop($next);
                }
                $copy->start = $next->id;
                // get end semester from selection
                $end_sem = Semester::find(Request::option('end_sem'));
                $copy->end = $next->beginn > $end_sem->beginn ? '' : $end_sem->id;

                // quelle: Klammerung von (Gültigkeit-) Versionen desselben Moduls
                // Gießen: Modul-ID des ursprünglichen Moduls
                $copy->quelle = $modul->quelle ?: $modul->id;
                //UOL Version um 1 hochzählen
                $copy->version = $copy->version + 1;
                // don't show the new Modul
                $copy->stat = 'planung';

                $store = false;
                try {
                    $copy->verifyPermission();
                    // UOL: Don't validate
                    $copy->store(false);
                    PageLayout::postSuccess(sprintf(
                        _('Das Modul "%s" und alle zugehörigen Modulteile wurden kopiert!'),
                        htmlReady($modul->getDisplayName())
                    ));
                } catch (InvalidValuesException $e) {
                    PageLayout::postError(
                        _('Das Modul konnte nicht kopiert werden!') . ' ' . htmlReady($e->getMessage())
                    );
                } catch (Exception $e) {
                    PageLayout::postError(_('Beim Kopieren trat ein Fehler auf!'));
                }
            }
        }
        $this->redirect($this->indexURL());
    }

    /**
     * Retrieves all fields from descriptor in the original language and
     * returns them as a json object.
     */
    public function show_original_action()
    {
        if (Request::isXhr()) {
            if (Request::option('type') === 'modulteil') {
                $parent = Modulteil::find(Request::option('id'));
                $formatted_fields = [
                    'voraussetzung',
                    'kommentar',
                    'kommentar_kapazitaet',
                    'kommentar_wl_praesenz',
                    'kommentar_wl_bereitung',
                    'kommentar_wl_selbst',
                    'kommentar_wl_pruef',
                    'pruef_vorleistung pruef_leistung'];
            } else {
                $parent = Modul::find(Request::option('id'));
                $formatted_fields = [
                    'voraussetzung',
                    'kompetenzziele',
                    'inhalte',
                    'literatur',
                    'links',
                    'kommentar',
                    'kommentar_kapazitaet',
                    'kommentar_wl_selbst',
                    'kommentar_wl_pruef',
                    'pruef_vorleistung',
                    'pruef_leistung',
                    'pruef_wiederholung ersatztext'
                ];
            }
            if ($parent) {
                $deskriptor_array = [];
                $deskriptor = $parent->getDeskriptor();
                foreach ($deskriptor->toArray() as $key => $value) {
                    if ($deskriptor->isI18nField($key) && MVVController::trim($value->original()) !== '') {
                        if (in_array($key, $formatted_fields)) {
                            $deskriptor_array[$key]['value'] = formatReady($value->original());
                        } else {
                            $deskriptor_array[$key]['value'] = $value->original();
                        }
                        $deskriptor_array[$key]['empty'] = 0;
                    } else {
                        $deskriptor_array[$key]['value'] = _('Keine Angabe in Originalfassung.');
                        $deskriptor_array[$key]['empty'] = 1;
                    }
                }

                // datafields
                foreach ($deskriptor->datafields as $entry) {
                    if ($entry->lang === '') {
                        $df = $entry->getTypedDatafield();
                        $value = $df->getDisplayValue();
                        $df_id = 'datafields_' . $entry->datafield_id;
                        if (MVVController::trim($value)) {
                            $deskriptor_array[$df_id]['value'] = $value;
                            $deskriptor_array[$df_id]['empty'] = 0;
                        } else {
                            $deskriptor_array[$df_id]['value'] = _('Keine Angabe in Originalfassung.');
                            $deskriptor_array[$df_id]['empty'] = 1;
                        }
                    }
                }
                $this->render_json($deskriptor_array);
            } else {
                $this->render_nothing();
            }
        } else {
            $this->redirect($this->indexURL());
        }
    }

    public function modulteil_action($modulteil_id = null)
    {
        $this->modulteil = Modulteil::find($modulteil_id);
        if (!$this->modulteil) {
            $this->modul = Modul::find(Request::option('modul_id'));
            if (!$this->modul) {
                PageLayout::postError(_('Unbekanntes Modul'));
                $this->redirect($this->indexURL());
                return;
            }
            $this->modulteil = new Modulteil();
            $this->modulteil->setNewId();
            $this->modulteil->modul_id = $this->modul->id;
        } else {
            $this->modul = $this->modulteil->modul;
        }

        $this->formen = [];
        foreach ($GLOBALS['MVV_MODULTEIL']['LERNLEHRFORM']['values'] as $key => $form) {
            if ($form['parent'] === '') {
                if ($form['visible'] || is_null($form['visible'])) {
                    $this->formen[$key]['group'] = [
                        'key'  => $key,
                        'name' => $form['name']
                    ];
                }
            } else {
                if ($form['visible']) {
                    $this->formen[$form['parent']]['options'][] = [
                        'key'  => $key,
                        'name' => $form['name']
                    ];
                }
            }
        }
        $this->setSidebar();

        $this->display_language = Request::option(
            'display_language',
            $this->modul->original_language
        );
        if ($this->modulteil->isNew()) {
            PageLayout::setTitle(_('Neuen Modulteil anlegen'));
            $success_message = ('Der Modulteil "%s" wurde angelegt.');
            $this->deskriptor = $this->modulteil->getDeskriptor();
            PageLayout::postInfo(sprintf(
                _('Sie legen einen neuen Modulteil für das Modul <em>%1$s</em> an. Der Modulteil muss zunächst in der Ausgabesprache <em>%2$s</em> angelegt werden.'),
                htmlReady($this->modul->getDisplayName()),
                htmlReady($GLOBALS['CONTENT_LANGUAGES'][$this->display_language]['name'])
            ));
            // set default language of instruction
            if (!empty($GLOBALS['MVV_MODULTEIL']['SPRACHE']['default'])) {
                $this->modulteil->assignLanguagesOfInstruction([
                    $GLOBALS['MVV_MODULTEIL']['SPRACHE']['default']
                ]);
            }
        } else {
            $this->deskriptor = $this->modulteil->getDeskriptor();
            $this->translations = $this->deskriptor->getAvailableTranslations($this->modul->original_language);
            if (!in_array($this->display_language, $this->translations)) {
                PageLayout::setTitle(sprintf(
                    _('Modulteil: <em>%1$s</em> in der Ausgabesprache <em>%2$s</em> neu anlegen.'),
                    $this->modulteil->getDisplayName(),
                    $GLOBALS['CONTENT_LANGUAGES'][$this->display_language]['name']
                ));
            } else {
                PageLayout::setTitle(sprintf(_('Modulteil: %s'), htmlReady($this->modulteil->getDisplayName())));
            }
            $success_message = _('Der Modulteil <em>%s</em> wurde geändert.');

            $sidebar = Sidebar::get();
            $views_widget = new ViewsWidget();
            $views_widget->setTitle(_('Ausgabesprache'));
            $content_languages = array_merge(array_flip($this->translations), $GLOBALS['CONTENT_LANGUAGES']);
            foreach ($content_languages as $code => $language) {
                $views_widget->addLink($language['name']
                    .  ' (' . ($code === $this->modul->original_language ? _('Originalfassung') . ', ' : '')
                    . (in_array($code, $this->translations) ? _('bearbeiten') : _('neu anlegen')) . ')',
                    URLHelper::getURL($this->modulteilURL($this->modulteil->id),
                        ['display_language' => $code]
                    )
                )->setActive($code === $this->display_language);
            }
            $sidebar->insertWidget($views_widget, 'actions','language');
        }

        $this->def_lang = $this->display_language === $this->modul->original_language;
        I18NString::setDefaultLanguage($this->modul->original_language);
        I18NString::setContentLanguage($this->display_language);

        if (!$this->def_lang) {
            $action_widget = $sidebar->getWidget('actions');
            $action_widget->addLink(
                _('Alle Texte der Originalfassung anzeigen'),
                '#',
                Icon::create('consultation'),
                ['class' => 'mvv-show-all-original']
            );
        }

        if (Request::submitted('store')) {
            CSRFProtection::verifyUnsafeRequest();
            $stored = false;
            if ($this->def_lang) {
                $this->modulteil->modul_id = Request::option('modul_id');
                $this->modulteil->flexnow_modul = trim(Request::get('flexnow_modul'));
                $this->modulteil->nummer = Request::int('nummer');
                $this->modulteil->num_bezeichnung = Request::option('num_bezeichnung');
                $this->modulteil->lernlehrform = Request::option('lernlehrform');
                $this->modulteil->semester = Request::option('semester');
                if (Request::get('kap_unbegrenzt')) {
                    $this->modulteil->kapazitaet = '';
                } else {
                    $kapazitaet = trim(Request::get('kapazitaet'));
                    $this->modulteil->kapazitaet = $kapazitaet === '' ? null : $kapazitaet;
                }
                $this->modulteil->kp = Request::float('kp');
                $this->modulteil->sws = Request::int('sws', 0);
                $this->modulteil->wl_praesenz = Request::int('wl_praesenz', 0);
                $this->modulteil->wl_bereitung = Request::int('wl_bereitung', 0);
                $this->modulteil->wl_selbst = Request::int('wl_selbst', 0);
                $this->modulteil->wl_pruef = Request::int('wl_pruef', 0);
                $this->modulteil->anteil_note = Request::int('anteil_note', 0);
                $this->modulteil->ausgleichbar = Request::int('ausgleichbar', 0);
                $this->modulteil->pflicht = Request::int('pflicht', 0);
                $this->modulteil->assignLanguagesOfInstruction(Request::optionArray('language_items'));
            }

            $deskriptor_fields = ['bezeichnung', 'voraussetzung', 'kommentar',
                'kommentar_kapazitaet', 'kommentar_wl_praesenz',
                'kommentar_wl_bereitung', 'kommentar_wl_selbst',
                'kommentar_wl_pruef', 'pruef_vorleistung', 'pruef_leistung',
                'kommentar_pflicht'];

            foreach ($deskriptor_fields as $deskriptor_field) {
                if ($this->deskriptor->isI18nField($deskriptor_field)) {
                    if ($this->display_language === $this->modul->original_language) {
                        $this->deskriptor->$deskriptor_field->setOriginal(
                            trim(Request::get($deskriptor_field))
                        );
                    } else {
                        $this->deskriptor->$deskriptor_field->setLocalized(
                            trim(Request::get($deskriptor_field)),
                            $this->display_language
                        );
                    }
                } else {
                    $this->deskriptor->setValue(
                        $deskriptor_field,
                        trim(Request::get($deskriptor_field))
                    );
                }
            }

            // update datafields
            foreach (Request::getArray('datafields') as $df_key => $df_value) {
                $df = $this->deskriptor->datafields->findOneBy('datafield_id', $df_key);
                if ($df) {
                    $tdf = $df->getTypedDatafield();
                    $tdf->setContentLanguage($this->display_language);
                    $tdf->setValueFromSubmit($df_value);
                    $tdf->store();
                }
            }

            // check permission (add a Modulteil to Modul) if Modulteil is new
            if ($this->modulteil->isNew()) {
                $perm = MvvPerm::get($this->modul);
                if (!$perm->haveFieldPermModulteile(MvvPerm::PERM_CREATE)) {
                    throw new AccessDeniedException();
                }
            }

            try {
                $this->modulteil->verifyPermission();
                $stored = $this->modulteil->store();
            } catch (InvalidValuesException $e) {
                PageLayout::postError(htmlReady($e->getMessage()));
            }
            if ($stored !== false) {
                if ($stored) {
                    PageLayout::postSuccess(sprintf(
                        $success_message,
                        htmlReady($this->modulteil->getDisplayName())
                    ));
                } else {
                    PageLayout::postInfo(_('Es wurden keine Änderungen vorgenommen.'));
                }
                $this->redirect($this->detailsURL($this->modulteil->modul_id));
                return;
            }
        }
        if ($this->display_language !== $this->modul->original_language && $this->deskriptor->isNew()) {
            PageLayout::postInfo(sprintf(
                _('Neue Beschreibung zum Modulteil "%s" in der Ausgabesprache %s anlegen.'),
                htmlReady($this->modulteil->getDisplayName()),
                htmlReady($GLOBALS['CONTENT_LANGUAGES'][$this->display_language]['name'])
            ));
        }
        $this->cancel_url = $this->detailsURL($this->modulteil->modul_id);

        if (!$this->modulteil->isNew()) {
            $action_widget = Sidebar::get()->getWidget('actions');
            $action_widget->addLink(
                _('Log-Einträge dieses Modulteils'),
                $this->url_for('shared/log_event/show/Modulteil/' . $this->modulteil->id,
                    ['object2_type' => 'ModulteilDeskriptor', 'object2_id' => $this->deskriptor->id]
                ),
                Icon::create('log')
            )->asDialog();
        }

        $this->addPermissionObjects($this->modulteil, $this->display_language);
        $this->render_template('module/module/modulteil', $this->layout);
    }

    /**
     * Deletes a descriptor from Modulteil
     *
     * @param type $deskriptor_id
     * @throws Trails\Exception
     */
    public function delete_modulteil_deskriptor_action($deskriptor_id, $language)
    {
        $deskriptor = ModulteilDeskriptor::find($deskriptor_id);
        if (is_null($deskriptor)) {
            throw new Trails\Exception(404, _('Unbekannter Deskriptor'));
        }
        $def_lang = $deskriptor->modulteil->modul->original_language;
        if ($language === $def_lang) {
            throw new Trails\Exception(403, _('Ein Deskriptor in der Original-Sprache kann nicht gelöscht werden.'));
        }
        if (Request::submitted('delete')) {
            CSRFProtection::verifyUnsafeRequest();
            if ($deskriptor->deleteTranslation($language)) {
                PageLayout::postSuccess(sprintf(
                    _('Der Deskriptor "%s" des Modulteils "%s" wurde gelöscht!'),
                    htmlReady($deskriptor->getDisplayName()),
                    htmlReady($deskriptor->modulteil->getDisplayName())
                ));
            }
        }
        $this->redirect($this->detailsURL(Request::option('modul_id')));
    }

    public function copy_modulteil_action($modulteil_id)
    {
        $modulteil = Modulteil::find($modulteil_id);
        if (!$modulteil) {
            PageLayout::postError(_('Unbekannter Modulteil'));
            $this->redirect($this->indexURL());
            return;
        }
        $copy_modulteil = $modulteil->copy();
        $copy_modulteil->store();
        PageLayout::postInfo(sprintf(
            _('Der Modulteil "%s" wurde kopiert. Klicken Sie auf "übernehmen", um Änderungen an der Kopie zu speichern.'),
            htmlReady($copy_modulteil->getDisplayName())
        ));
        $this->redirect($this->modulteilURL($copy_modulteil->id));
    }

    public function modulteil_lvg_action($modulteil_id)
    {
        $this->modulteil = Modulteil::find($modulteil_id);
        if (is_null($this->modulteil)) {
            PageLayout::postError(_('Unbekannter Modulteil'));
            $this->redirect($this->indexURL());
            return;
        } else {
            $this->modulteil_id = $this->modulteil->getId();
            $this->search_lvgruppe($this->modulteil->getId());
            if (Request::isXhr()) {
                $this->render_template('module/module/modulteil_lvg');
            } else {
                $this->modul = Modul::get($this->modulteil->modul_id);
                $this->modul_id = $this->modul->getId();
                $this->perform_relayed('details/' . $this->modulteil->modul_id . '/' . $this->modulteil->id);
            }
        }
    }

    private function search_lvgruppe($modulteil_id)
    {
        //Quicksearch
        $query = 'SELECT lvgruppe_id, name
                FROM mvv_lvgruppe WHERE name LIKE :input
                AND lvgruppe_id NOT IN (
                    SELECT lvgruppe_id FROM mvv_lvgruppe_modulteil WHERE modulteil_id = ' . DBManager::get()->quote($modulteil_id) .
            ')';
        $search = new SQLSearch($query, _('LV-Gruppe suchen'));
        $this->qs_search_id = md5(serialize($search));
        $this->search = QuickSearch::get(
            'lvgruppe_id_' . $modulteil_id, $search
        )->setInputStyle('width: 240px')
            ->noSelectbox();
    }

    public function add_lvgruppe_action($modulteil_id)
    {
        $this->modulteil = Modulteil::find($modulteil_id);
        if (is_null($this->modulteil)) {
            PageLayout::postError(_('Unbekannter Modulteil'));
            $this->redirect($this->indexURL());
            return;
        } else {
            $this->lvgruppe = Lvgruppe::find(
                Request::option('lvgruppe_id_' . $this->modulteil->getId())
            );
            if (!$this->lvgruppe) {
                PageLayout::postError(_('Unbekannte Lehrveranstaltungsgruppe'));
            } else {
                $this->lvg_modulteil = LvgruppeModulteil::get(
                    [
                        $this->lvgruppe->getId(),
                        $this->modulteil->getId()
                    ]
                );
                if ($this->lvg_modulteil->isNew()) {
                    $this->lvg_modulteil->store();
                    PageLayout::postSuccess(sprintf(
                        _('Die Lehrveranstaltungsgruppe "%s" wurde dem Modulteil "%s" zugeordnet.'),
                        htmlReady($this->lvgruppe->getDisplayName()),
                        htmlReady($this->modulteil->getDisplayName())
                    ));
                } else {
                    PageLayout::postInfo(sprintf(
                        _('Die bestehende Zuordnung der Lehrveranstaltungsgruppe "%s" zum Modulteil "%s" wurde nicht geändert.'),
                        htmlReady($this->lvgruppe->getDisplayName()),
                        htmlReady($this->modulteil->getDisplayName())
                    ));
                }
            }
            $this->redirect($this->detailsURL($this->modulteil->modul_id, $this->modulteil->id));
        }
    }

    public function delete_lvgruppe_action($modulteil_id, $lvgruppe_id)
    {
        $lvg_modulteil = LvgruppeModulteil::find([$lvgruppe_id, $modulteil_id]);
        if (!$lvg_modulteil) {
            PageLayout::postError(_('Unbekannte Zuordnung'));
        } else {
            $lvgruppe = Lvgruppe::find($lvgruppe_id);
            $modulteil = Modulteil::find($modulteil_id);
            if (Request::submitted('delete')) {
                CSRFProtection::verifyUnsafeRequest();
                PageLayout::postSuccess(sprintf(
                    _('Die Lehrveranstaltungsgruppe "%s" wurde vom Modulteil "%s" entfernt.'),
                    htmlReady($lvgruppe->getDisplayName()),
                    htmlReady($modulteil->getDisplayName())
                ));
                $lvg_modulteil->delete();
            }
        }
        $this->redirect($this->detailsURL($modulteil->modul_id, $modulteil->id));
    }

    public function lvgruppe_action($modulteil_id, $lvgruppe_id = null)
    {
        $this->modulteil = Modulteil::find($modulteil_id);
        if (is_null($this->modulteil)) {
            PageLayout::postError(_('Unbekannter Modulteil'));
            $this->redirect($this->indexURL());
            return;
        } else {
            $this->lvgruppe = Lvgruppe::get($lvgruppe_id);
            if ($this->lvgruppe->isNew()) {
                $this->lvgruppe = new Lvgruppe();
                PageLayout::setTitle(_('Neue Lehrveranstaltungsgruppe anlegen'));
                $success_message = _('Die Lehrveranstaltungsgruppe "%s" wurde angelegt.');
                $this->headline = _('Neue Lehrveranstaltungsgruppe anlegen');
            } else {
                PageLayout::setTitle(_('Lehrveranstaltungsgruppe bearbeiten'));
                $success_message = _('Die Lehrveranstaltungsgruppe "%s" wurde geändert.');
                $this->headline = sprintf(_('Lehrveranstaltungsgruppe "%s" bearbeiten.'),
                    $this->lvgruppe->getDisplayName());
            }
            $this->cancel_url = $this->indexURL();
            $this->submit_url = $this->lvgruppeURL($this->modulteil->id, $this->lvgruppe->id);
            if (Request::submitted('store')) {
                CSRFProtection::verifyUnsafeRequest();
                $stored = false;
                $this->lvgruppe->name = trim(Request::get('name'));
                $this->lvgruppe->alttext = Request::i18n('alttext')->trim();

                try {
                    $stored = $this->lvgruppe->store();
                    if ($stored) {
                        $this->lvg_modulteil = LvgruppeModulteil::get(
                            [
                                $this->lvgruppe->getId(),
                                $this->modulteil->getId()
                            ]
                        );
                        $stored_modulteil = $this->lvg_modulteil->store();
                    }
                } catch (InvalidValuesException $e) {
                    PageLayout::postError($e->getMessage());
                }
                if ($stored !== false && $stored_modulteil !== false) {
                    if ($stored) {
                        PageLayout::postSuccess(sprintf(
                            $success_message,
                            htmlReady($this->lvgruppe->getDisplayName())
                        ));
                    } else {
                        PageLayout::postInfo(_('Es wurden keine Änderungen vorgenommen.'));
                    }
                    $this->relocate($this->detailsURL($this->modulteil->modul_id, $this->modulteil->id));
                    return;
                }
            }
        }
        $this->render_template('lvgruppen/lvgruppen/lvgruppe');
    }

    public function delete_action($modul_id)
    {
        $modul = Modul::get($modul_id);
        if ($modul->isNew()) {
             PageLayout::postError(_('Unbekanntes Modul'));
        } else {
            if (Request::submitted('delete')) {
                CSRFProtection::verifyUnsafeRequest();
                PageLayout::postSuccess(sprintf(
                    _('Modul "%s" gelöscht!'),
                    htmlReady($modul->getDisplayName())
                ));
                $modul->delete();
            }
        }
        $this->redirect($this->detailsURL($modul->id));
    }

    public function delete_modulteil_action($modulteil_id)
    {
        $modulteil = Modulteil::find($modulteil_id);
        $modul_id = $modulteil->modul_id;
        if (!$modulteil) {
             PageLayout::postError(_('Unbekannter Modulteil'));
        } else {
            if (Request::submitted('delete')) {
                CSRFProtection::verifyUnsafeRequest();
                PageLayout::postSuccess(sprintf(
                    _('Modulteil "%s" gelöscht!'),
                    htmlReady($modulteil->getDisplayName())
                ));
                $modulteil->delete();
            }
        }
        $this->redirect($this->detailsURL($modul_id));
    }

    public function details_action($modul_id, $modulteil_id = null)
    {
        $this->institut_id = null;
        $this->modul = Modul::get($modul_id);
        $this->modul_id = $this->modul->isNew() ? null : $this->modul->getId();
        if ($modulteil_id) {
            $modulteil = Modulteil::get($modulteil_id);
            $this->modulteil_id = $modulteil->isNew() ? null : $modulteil->getId();
            $this->search_lvgruppe($this->modulteil_id);
        }
        if (Request::isXhr()) {
            $this->details_action = 'modulteil_lvg';
        } else {
            $this->perform_relayed('index');
        }
    }

    public function diff_select_action($modul_id)
    {
        $this->modul = Modul::find($modul_id);
        if ($this->modul) {
            // Modulsuche
            $query = "SELECT
                    mm.modul_id,
                    CONCAT(mmd.bezeichnung, ', ', IF(ISNULL(mm.code), '', mm.code),
                    IF(ISNULL(sd1.name), '', CONCAT(', ', IF(ISNULL(sd2.name),
                    CONCAT('ab ', sd1.name),CONCAT(sd1.name, ' - ', sd2.name))))) AS modul_name
                    FROM mvv_modul mm LEFT JOIN mvv_modul_deskriptor mmd
                    ON mm.modul_id = mmd.modul_id
                    LEFT JOIN semester_data sd1 ON mm.start = sd1.semester_id
                    LEFT JOIN semester_data sd2 ON mm.end = sd2.semester_id
                    WHERE (mm.code LIKE :input
                    OR mmd.bezeichnung LIKE :input)
                    AND mm.modul_id <> "
                   . DBManager::get()->quote($this->modul->getId())
                   . " ORDER BY modul_name";
            $sql_search_modul = new SQLSearch($query, _('Modul suchen'), 'modul');
            $this->qs_id_module = md5(serialize($sql_search_modul));
            $this->search_modul = QuickSearch::get('old_module_id', $sql_search_modul)
                    ->setInputStyle('width: 240px');

            $this->quelle = $this->modul->modul_quelle;
            $this->variante = $this->modul->modul_variante;
        } else {
            PageLayout::postError(_('Unbekanntes Modul'));
            $this->relocate($this->indexURL());
        }
    }

    public function diff_action($new_id = null, $old_id = null)
    {
        $new_module = Modul::find(Request::option('new_id', $new_id));
        $old_module = Modul::find(Request::option('old_module_id', $old_id));

        if (!$new_module || !$old_module) {
            PageLayout::postError(_('Unbekanntes Modul'));
            if ($new_module) {
                $this->redirect($this->diff_selectURL($new_module->id));
            } else {
                $this->redirect($this->indexURL());
            }
        } else {
            if (Request::isXhr()) {
                $this->response->add_header(
                    'X-Location',
                    $this->diffURL($new_module->id, $old_module->id)
                );
            }
            $type_new = 1;
            $count_modulteile = count($new_module->modulteile);
            if ($count_modulteile === 0) {
                $type_new = 3;
            } else if ($count_modulteile === 1) {
                $type_new = 2;
            }

            $type_old = 1;
            $count_modulteile = count($old_module->modulteile);
            if ($count_modulteile === 0) {
                $type_old = 3;
            } else if ($count_modulteile === 1) {
                $type_old = 2;
            }

            $diff_config = new Caxy\HtmlDiff\HtmlDiffConfig();
            $diff_config->setPurifierCacheLocation($GLOBALS['TMP_PATH']);

            PageLayout::setTitle(_('Vergleichsansicht'));
            PageLayout::addStylesheet('print.css');
            $factory = $this->get_template_factory();
            $template = $factory->open('module/module/diff');
            $template->set_layout($GLOBALS['template_factory']->open('layouts/base.php'));
            $template->set_attributes([
                'new_module' => $new_module,
                'old_module' => $old_module,
                'type_new'   => $type_new,
                'type_old'   => $type_old,
                'plugin'   => $this->plugin,
                'diff_config' => $diff_config,
            ]);

            $this->render_text($template->render());
        }
    }

    public function change_language_action(string $module_id): void
    {
        $module = Modul::find($module_id);
        if (!$module) {
            throw new UnexpectedValueException('Unknown module.');
        }
        PageLayout::setTitle(_('Originalsprache ändern'));
        $this->translations = $module->deskriptoren->getAvailableTranslations($module->original_language);
        $this->content_languages = $GLOBALS['CONTENT_LANGUAGES'];
        $this->original_language = $module->original_language;
        $this->module_id = $module->id;
    }

    public function store_language_action(string $module_id): void
    {
        $module = Modul::find($module_id);
        if (!$module) {
            throw new UnexpectedValueException('Unknown module.');
        }
        CSRFProtection::verifyUnsafeRequest();
        $new_language = Request::option('new_language');
        if ($new_language !== $module->original_language) {
            $descriptor = $module->deskriptoren;
            if (Request::bool('swap_data')) {
                self::swap_translation($descriptor, $new_language, $module->original_language);
                $module->modulteile->each(
                    fn($component) => self::swap_translation(
                        $component->deskriptoren,
                        $new_language,
                        $module->original_language
                    )
                );
            } else {
                self::swap_language($module->deskriptoren, $new_language, $module->original_language);
                $module->modulteile->each(
                    fn($component) => self::swap_language(
                        $component->deskriptoren,
                        $new_language,
                        $module->original_language
                    )
                );
            }
            $module->original_language = $new_language;
            $module->store();
        } else {
            throw new UnexpectedValueException('New language has to be different from current language.');
        }
        $this->relocate('module/module/index');
    }

    /**
     * Swaps the language of the descriptor, but not the translated content.
     *
     * @param ModulDeskriptor|ModulteilDeskriptor $descriptor The descriptor of a module or a module component.
     * @param string $new_language The language to swap to as language code.
     * @param string $old_language The current languageas language code.
     * @return int|bool Number of swapped translations or false if no translation is available.
     */
    private static function swap_language(
        ModulDeskriptor|ModulteilDeskriptor $descriptor,
        string $new_language,
        string $old_language
    ): int|bool
    {
        // change language setting in i18n for this descriptor if a translation exists
        $translations = $descriptor->getAvailableTranslations($old_language);
        if (count($translations) > 1) {
            $metadata = $descriptor->getTableMetadata();
            foreach (array_keys($metadata['fields']) as $field) {
                if ($descriptor->isI18nField($field)) {
                    $i18n_field = $descriptor->$field;
                    $i18n_field->setDefaultLanguage($old_language);
                    $translation = $i18n_field->translation($new_language);
                    if (!empty($translation)) {
                        $translations = $i18n_field->toArray();
                        unset($translations[$new_language]);
                        $translations[$old_language] = $translation;
                        $i18n_field->setTranslations($translations);
                    }
                    $i18n_field->setDefaultLanguage($new_language);
                }
            }
            return $descriptor->store();
        }
        return false;
    }

    /**
     * Swaps the language of the descriptor, swaps the translated content also.
     *
     * @param ModulDeskriptor|ModulteilDeskriptor $descriptor The descriptor of a module or a module component.
     * @param string $new_language The language to swap to as language code.
     * @param string $old_language The current language as language code.
     * @return int|bool Number of swapped translations or false if no translation is available.
     */
    private static function swap_translation(
        ModulDeskriptor|ModulteilDeskriptor $descriptor,
        string $new_language,
        string $old_language
    ): int|bool
    {
        if (
            !array_key_exists($new_language, $GLOBALS['CONTENT_LANGUAGES'])
            || !array_key_exists($old_language, $GLOBALS['CONTENT_LANGUAGES'])
        ) {
            throw new UnexpectedValueException('Language code refers to no content language.');
        }
        $count = 0;
        $meta_data = $descriptor->getTableMetadata();
        $descriptor_class = $descriptor::class;
        foreach ($meta_data['fields'] as $field => $foo) {
            if ($descriptor->isI18nField($field)) {
                $i18n_field = $descriptor->$field;
                $i18n_field->setDefaultLanguage($old_language);
                $new_base_content = $i18n_field->translation($new_language);
                $old_base_content = $i18n_field->original();
                $i18n_field->setDefaultLanguage($new_language);
                if (!empty($new_base_content)) {
                    $i18n_field->setOriginal($new_base_content);
                    $i18n_field->setLocalized($old_base_content, $old_language);
                }
                $lang = $i18n_field->toArray();
                unset($lang[$new_language]);
                $i18n_field->setTranslations($lang);
            }
        }
        return $descriptor->store();
    }

    /**
     * do the search
     */
    public function search_action()
    {
        if (Request::get('reset-search')) {
            $this->reset_search('module');
            $this->reset_page();
        } else {
            $this->reset_search('module');
            $this->reset_page();

            $this->do_search(
                Modul::class,
                trim(Request::get('modul_suche_parameter')),
                Request::get('modul_suche'),
                $this->getSearchFilters()
            );
        }
        $this->redirect($this->indexURL());
    }

    /**
     * resets the search
     */
    public function reset_search_action()
    {
        $this->reset_search('module');
        $this->reset_page();
    }

    public function sort_action()
    {
        $target = explode('_', Request::option('list_id'));
        $success = false;
        if ($target[0] === 'modulteil') {
            $success = $this->sort_lvgruppen($target[1]);
        } else {
            $success = $this->sort_modulteile($target[0]);
        }
        if ($success) {
            $this->set_status(204);
        } else {
            $this->set_status(400);
        }
        $this->render_nothing();
    }

    /**
     * sorts Modulteile
     */
    private function sort_modulteile($modul_id)
    {
        $modul = Modul::find($modul_id);
        if ($modul) {
            $orderedIds = Request::getArray('newOrder');
            if (is_array($orderedIds)) {
                $i = 1;
                foreach ($orderedIds as $modulteil_id) {
                    $modulteil = $modul->modulteile->find($modulteil_id);
                    if ($modulteil->position !== $i) {
                        $modulteil->position = $i;
                        $modulteil->store(false);
                    }
                    $i++;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * sorts LVGruppen
     */
    private function sort_lvgruppen($modulteil_id)
    {
        $modulteil = Modulteil::find($modulteil_id);
        if ($modulteil) {
            $orderedIds = Request::getArray('newOrder');
            if (is_array($orderedIds)) {
                $i = 1;
                foreach ($orderedIds as $id) {
                    list($foo, $lvgruppe_id) = explode('_', $id);
                    $lvgruppe_modulteil = LvgruppeModulteil::find(
                        [
                            $lvgruppe_id,
                            $modulteil->getId()
                        ]
                    );
                    if ($lvgruppe_modulteil && $lvgruppe_modulteil->position !== $i) {
                        $lvgruppe_modulteil->position = $i;
                        $lvgruppe_modulteil->store(false);
                    }
                    $i++;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * sets filter parameters and store these in the session
     */
    public function set_filter_action()
    {
        // Semester
        $semester_id = Request::option('semester_filter', 'all');
        if ($semester_id !== 'all') {
            $semester = Semester::find($semester_id);
            $this->filter['start_sem.beginn'] = $semester->beginn;
            $this->filter['end_sem.ende'] = $semester->beginn;
        } else {
            $this->filter['start_sem.beginn'] = -1;
            $this->filter['end_sem.ende'] = -1;
        }

        // module status
        $this->filter['mvv_modul.stat'] = trim(Request::get('status_filter')) ? Request::option('status_filter') : null;

        // responsible Institutes
        $this->filter['mvv_modul_inst.gruppe'] = 'hauptverantwortlich';
        if (trim(Request::get('institut_filter'))) {
            $this->filter['mvv_modul_inst.institut_id'] = Request::option('institut_filter');
        } else {
            // only institutes the user has an assigned MVV role
            $this->filter['mvv_modul_inst.institut_id'] = MvvPerm::getOwnInstitutes();
        }

        // Fach
        $_SESSION['mvv_filter_module_fach_id'] = Request::option('fach_filter', '');

        // Abschluss
        $_SESSION['mvv_filter_module_abschluss_id'] = Request::option('abschluss_filter', '');

        // store filter
        $this->reset_page();
        $this->sessSet('filter', $this->filter);
        $this->redirect($this->indexURL());
    }

    public function reset_filter_action()
    {
        $this->reset_page();

        $_SESSION['mvv_filter_module_fach_id'] = '';
        $_SESSION['mvv_filter_module_abschluss_id'] = '';

        $this->sessSet('filter', []);
        $this->redirect($this->indexURL());
    }

    protected function setSidebar()
    {
        $sidebar = Sidebar::get();

        $widget  = new ActionsWidget();
        $widget->setTitle(_('Aktionen'));
        if (MvvPerm::havePermCreate('Modul')) {
            $widget->addLink(
                _('Neues Modul anlegen'),
                $this->select_module_languageURL(),
                Icon::create('add')
            )->asDialog('size=auto');
        }
        $sidebar->addWidget($widget, 'actions');

        if ($this->show_sidebar_search) {
            $this->sidebar_search();
        }
        if ($this->show_sidebar_filter) {
            $this->sidebar_filter();
        }
        $helpbar = Helpbar::get();
        $widget = new HelpbarWidget();
        $widget->addElement(new WidgetElement(_('Auf diesen Seiten können Sie die Module verwalten. Ein Modul kann ein oder mehrere Modulteil(e) haben.').'</br>'));
        $widget->addElement(new WidgetElement(_('Module können nur gelöscht werden, wenn Sie noch keinem Studiengang zugeordnet sind.')));
        $helpbar->addWidget($widget);
        $this->sidebar_rendered = true;
    }

    /**
     * adds the filter function to the sidebar
     */
    private function sidebar_filter()
    {
        // Nur Module von verantwortlichen Einrichtungen an denen der User
        // eine Rolle hat
        if (empty($this->filter['mvv_modul_inst.institut_id'])) {
            unset($this->filter['mvv_modul_inst.institut_id']);
        }
        $modul_filter = $this->filter;

        $template_factory = $this->get_template_factory();
        $template = $template_factory->open('shared/filter');

        // Status
        $modul_ids = Modul::findByFilter($modul_filter);
        $template->set_attribute('status', Modul::findStatusByIds($modul_ids));
        $template->set_attribute('selected_status', $this->filter['mvv_modul.stat'] ?? '');
        $template->set_attribute('status_array', $GLOBALS['MVV_MODUL']['STATUS']['values']);

        // Institutes
        $template->set_attribute('institute', Modul::getAllAssignedInstitutes('name', 'ASC', $modul_filter));
        $template->set_attribute('institute_count', 'count_objects');
        $template->set_attribute('selected_institut', $this->filter['mvv_modul_inst.institut_id'] ?? '');

        // Semesters
        $semesters = new SimpleCollection(Semester::getAll());
        $semesters = $semesters->orderBy('beginn desc');
        $selected_semester = null;
        if (!empty($this->filter['start_sem.beginn'])) {
            $selected_semester = $semesters->findOneBy('beginn', $this->filter['start_sem.beginn']);
        }

        // Fach
        $count_faecher = $this->countFaecher($modul_ids, $_SESSION['mvv_filter_module_fach_id'] ?? '');
        $template->set_attribute('faecher', SimpleORMapCollection::createFromArray(
            Fach::findMany(array_keys($count_faecher))
        )->orderBy('name'));
        $template->set_attribute('count_faecher', $count_faecher);
        $template->set_attribute('selected_fach', $_SESSION['mvv_filter_module_fach_id'] ?? '');

        // Abschluss
        $count_abschluesse = $this->countAbschluesse($modul_ids, $_SESSION['mvv_filter_module_abschluss_id'] ?? '');
        $template->set_attribute('abschluesse', SimpleORMapCollection::createFromArray(
             Abschluss::findMany(array_keys($count_abschluesse))
        )->orderBy('name'));
        $template->set_attribute('count_abschluesse', $count_abschluesse);
        $template->set_attribute('selected_abschluss', $_SESSION['mvv_filter_module_abschluss_id'] ?? '');

        $template->set_attribute('semester', $semesters);
        $template->set_attribute('selected_semester', $selected_semester->id ?? '');
        $template->set_attribute('default_semester', Semester::findCurrent()->id);

        $template->set_attribute('action', $this->set_filterURL());
        $template->set_attribute('action_reset', $this->reset_filterURL());

        $sidebar = Sidebar::get();
        $widget  = new TemplateWidget(
            _('Filter'),
            $template
        );
        $sidebar->addWidget($widget, 'filters');
    }

    /**
     * adds the search function to the sidebar
     */
    private function sidebar_search()
    {
        $query = "
            SELECT DISTINCT `mvv_modul`.`modul_id`,
                    CONCAT(`mvv_modul_deskriptor`.`bezeichnung`, ' (', `code`, ')')
                        AS `name` FROM `mvv_modul`
                LEFT JOIN `mvv_modul_deskriptor`
                    ON `mvv_modul`.`modul_id` = `mvv_modul_deskriptor`.`modul_id`
                LEFT JOIN `mvv_modul_inst`
                    ON `mvv_modul_deskriptor`.`modul_id` = `mvv_modul_inst`.`modul_id`
                LEFT JOIN semester_data as start_sem
                    ON (mvv_modul.start = start_sem.semester_id)
                LEFT JOIN semester_data as end_sem
                    ON (mvv_modul.end = end_sem.semester_id)
            WHERE (`code` LIKE :input OR `mvv_modul_deskriptor`.`bezeichnung` LIKE :input) "
                . ModuleManagementModel::getFilterSql($this->filter) . '
            ORDER BY `name` ASC';
        $search_term = $this->search_term ?: _('Modul suchen');

        $sidebar = Sidebar::get();
        $widget = new SearchWidget($this->searchURL());
        $widget->addNeedle(
            _('Modul suchen'),
            'modul_suche',
            true,
            new SQLSearch($query, $search_term, 'modul_id'),
            'function () { $(this).closest("form").submit(); }',
            $this->search_term
        );
        $sidebar->addWidget($widget, 'search');
    }

    /**
     * Returns the number of modules grouped by subjects for given module ids.
     *
     * @param array $module_ids The ids of the modules.
     * @param string $fach_id The id of the selected subject.
     * @return array Number of modules grouped by subjects.
     */
    private function countFaecher(array $module_ids, string $fach_id): array
    {
        if ($fach_id === '') {
            $params = [':module_ids' => $module_ids];
            $where = "`mvv_stgteilabschnitt_modul`.`modul_id` IN (:module_ids)";
        } else {
            $params = [
                ':fach_id' => $fach_id,
                ':module_ids' => $module_ids
            ];
            $where = "`fach`.`fach_id` = :fach_id
                      AND `mvv_stgteilabschnitt_modul`.`modul_id` IN (:module_ids)";
        }
        $query = "SELECT `fach`.`fach_id`, COUNT(DISTINCT `modul_id`) AS `count_faecher`
                  FROM `fach`
                  JOIN `mvv_stgteil` USING (`fach_id`)
                  JOIN `mvv_stgteilversion` USING (`stgteil_id`)
                  JOIN `mvv_stgteilabschnitt` USING (`version_id`)
                  JOIN `mvv_stgteilabschnitt_modul` USING (`abschnitt_id`)
                  WHERE {$where}
                  GROUP BY `fach_id`";
        return DBManager::get()->fetchPairs($query, $params);
    }

    /**
     * Returns the ids of the modules related to the given subject id.
     *
     * @param string $fach_id The id of the selected subject.
     * @return array The ids of the modules related to the subject.
     */
    private function findModuleIdsByFach(string $fach_id): array
    {
        $query = "SELECT `mvv_stgteilabschnitt_modul`.`modul_id`
                  FROM `mvv_stgteilabschnitt_modul`
                  JOIN `mvv_stgteilabschnitt` USING (`abschnitt_id`)
                  JOIN `mvv_stgteilversion` USING (`version_id`)
                  JOIN `mvv_stgteil` USING (`stgteil_id`)
                  WHERE `mvv_stgteil`.`fach_id` = ?";
        return DBManager::get()->fetchFirst($query, [$fach_id]);
    }

    /**
     * Returns the number of modules grouped by degrees for given module ids.
     *
     * @param array $module_ids The ids of the modules.
     * @param string $abschluss_id The id of the selected degree.
     * @return array Number of modules grouped by degrees.
     */
    private function countAbschluesse(array $module_ids, $abschluss_id): array
    {
        if ($abschluss_id === '') {
            $params = [':module_ids' => $module_ids];
            $where = "`mvv_stgteilabschnitt_modul`.`modul_id` IN (:module_ids)";
        } else {
            $params = [
                ':abschluss_ids' => $abschluss_id,
                ':module_ids' => $module_ids
            ];
            $where = "`abschluss`.`abschluss_id` = :abschluss_ids
                      AND `mvv_stgteilabschnitt_modul`.`modul_id` IN (:module_ids)";
        }
        $query = "SELECT `abschluss`.`abschluss_id`, COUNT(DISTINCT `modul_id`) AS `count_abschluesse`
                  FROM `abschluss`
                  JOIN `mvv_studiengang` USING (`abschluss_id`)
                  JOIN `mvv_stg_stgteil` USING (`studiengang_id`)
                  JOIN `mvv_stgteilversion` USING (`stgteil_id`)
                  JOIN `mvv_stgteilabschnitt` USING (`version_id`)
                  JOIN `mvv_stgteilabschnitt_modul` USING (`abschnitt_id`)
                  WHERE {$where}
                  GROUP BY `abschluss_id`";
        return DBManager::get()->fetchPairs($query, $params);
    }

    /**
     * Returns the ids of the modules related to the given degree id.
     *
     * @param string $abschluss_id The id of the selected degree.
     * @return array The ids of the modules related to the degree.
     */
    private function findModuleIdsByAbschluss(string $abschluss_id): array
    {
        $query = "SELECT `mvv_stgteilabschnitt_modul`.`modul_id`
                  FROM `mvv_stgteilabschnitt_modul`
                  JOIN `mvv_stgteilabschnitt` USING (`abschnitt_id`)
                  JOIN `mvv_stgteilversion` USING (`version_id`)
                  JOIN `mvv_stg_stgteil` USING (`stgteil_id`)
                  JOIN `mvv_studiengang` USING (`studiengang_id`)
                  WHERE `mvv_studiengang`.`abschluss_id` = ?";
        return DBManager::get()->fetchFirst($query, [$abschluss_id]);
    }

    /**
     * Sets the original language for the given module.
     *
     * @param Modul $module Sets the original language for this module.
     * @param string $original_language The original language as language code.
     * @return void
     */
    private function setOriginalLanguage(Modul $module, string $original_language): void
    {
        $content_language = $GLOBALS['CONTENT_LANGUAGES'][$original_language];
        if (empty($content_language)) {
            throw new InvalidArgumentException("Original language $original_language is not defined");
        }
        if ($module->deskriptoren) {
            $current_language = $module->original_language;
            $module->original_language = $original_language;
            DBManager::get()->execute("
                UPDATE `i18n`
                SET `lang` = ?
                WHERE `object_id` = ?
                  AND `table` = 'mvv_modul_deskriptor'
                  AND `lang` = ?",
                [
                    $original_language,
                    $module->deskriptoren->id,
                    $current_language
                ]);
            $module->modulteile->each(fn($component) => DBManager::get()->execute("
                    UPDATE `i18n`
                    SET `lang` = ?
                    WHERE `object_id` IN (?)
                      AND `table` = 'mvv_modulteil_deskriptor'
                      AND `lang` = ?",
                    [
                        $original_language,
                        $component->deskriptoren->id,
                        $current_language
                    ]
                )
            );
        }
    }

    /**
     * Add permission objects as properties to this controller to use in views.
     *
     * @param Modul|Modulteil $mvv_object Add a permission object for this object.
     * @param string $display_language The language to display in the view.
     * @return void
     */
    private function addPermissionObjects(Modul|Modulteil $mvv_object, string $display_language): void
    {
        $this->perm = MvvPerm::get($mvv_object);
        $this->perm_d = MvvPerm::get($mvv_object->getDeskriptor());
        if ($mvv_object->getDefaultLanguage() !== $display_language) {
            $perm_language = strtoupper(explode('_', $display_language)[0] ?? '');
            $this->perm_d->setVariant($perm_language);
        }
    }

    private function getFilters(): array
    {
        $filters = $this->sessGet('filter', []);

        // set default semester filter
        if (!isset($filters['start_sem.beginn'], $filters['end_sem.ende'])) {
            // switch semester according to time switch
            // (n weeks before next semester)
            $current_sem = Semester::findDefault();
            if ($current_sem) {
                $filters['start_sem.beginn'] = $current_sem->beginn;
                $filters['end_sem.ende']     = $current_sem->beginn;
            }
        }

        // Nur Module von verantwortlichen Einrichtungen an denen der User
        // eine Rolle hat
        if (empty($filters['mvv_modul_inst.institut_id'])) {
            unset($filters['mvv_modul_inst.institut_id']);
        }

        return [
            'mvv_modul_inst.gruppe'      => 'hauptverantwortlich',
            'mvv_modul_inst.institut_id' => MvvPerm::getOwnInstitutes(),

            ...$filters
        ];
    }

    private function getSearchFilters(): array
    {
        $search_result = $this->getSearchResult(Modul::class);

        $module_ids = [];
        if (count($search_result) > 0) {
            $module_ids = $search_result;
        } else {
            if (!empty($_SESSION['mvv_filter_module_fach_id'])) {
                $module_ids = $this->findModuleIdsByFach($_SESSION['mvv_filter_module_fach_id']);
            }
            if (!empty($_SESSION['mvv_filter_module_abschluss_id'])) {
                if (count($module_ids) > 0) {
                    $module_ids = array_intersect(
                        $module_ids,
                        $this->findModuleIdsByAbschluss($_SESSION['mvv_filter_module_abschluss_id'])
                    );
                } else {
                    $module_ids = $this->findModuleIdsByAbschluss($_SESSION['mvv_filter_module_abschluss_id']);
                }
            }
        }

        return [
            ...$this->getFilters(),
            ...($module_ids ? ['mvv_modul.modul_id' => $module_ids] : [])
        ];
    }
}
