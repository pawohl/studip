<?php
/* Copyright (c) 1998-2014 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
* redirect script for studip-users
*
* @author Arne Schroeder <schroeder@data-quest.de>
* @author Andre Noack <noack@data-quest.de>
*
*/

/* ILIAS Version 10.x */

if (file_exists("../ilias.ini.php")) {
    require_once '../components/ILIAS/Init/classes/class.ilIniFile.php';
    $ilIliasIniFile = new ilIniFile('../ilias.ini.php');
    $ilIliasIniFile->read();
    $serverSettings = $ilIliasIniFile->readGroup("server");
    if (isset($serverSettings['studip']) && $serverSettings['studip'] != 1) {
        echo 'Option "studip" in ilias.ini.php is not enabled. You need to add studip = "1" to the server section.';
        exit();
    }

    $cookie_path = dirname($_SERVER['PHP_SELF']);
    if (!str_ends_with($cookie_path, '/')) {
        $cookie_path .= '/';
    }
    if (isset($_GET['sess_id'])) {
        setcookie('PHPSESSID', $_GET['sess_id'], 0, $cookie_path);
        $_COOKIE['PHPSESSID'] = $_GET['sess_id'];
    }

    if (isset($_GET['client_id'])) {
        setcookie('ilClientId', $_GET['client_id'], 0, $cookie_path);
        $_COOKIE['ilClientId'] = $_GET['client_id'];
    }
//echo "DONE";die();
//    require_once "./include/inc.header.php";

    $base_url= 'ilias.php?baseClass=ilDashboardGUI';


    // redirect to specified page
    $base_url = match($_GET['target']) {
        'edit' => match($_GET['type']) {
            'cat'   => 'ilias.php?baseClass=ilrepositorygui&cmd=edit',
            'crs'   => 'ilias.php?baseClass=ilrepositorygui&cmd=edit',
            'exc'   => 'ilias.php?baseClass=ilExerciseHandlerGUI&cmdClass=ilObjExerciseGUI&cmd=edit',
            'glo'   => 'ilias.php?baseClass=ilGlossaryEditorGUI',
            'htlm'  => 'ilias.php?baseClass=ilHTLMEditorGUI',
            'lm'    => 'ilias.php?baseClass=ilLMEditorGUI',
            'sahs'  => 'ilias.php?baseClass=ilSAHSEditGUI',
            'svy'   => 'ilias.php?baseClass=ilObjSurveyGUI',
            'tst'   => 'ilias.php?baseClass=ilrepositorygui&cmdClass=ILIAS\\Test\\Settings\\MainSettings\\SettingsMainGUI&cmd=showForm',
            'webr'  => 'ilias.php?baseClass=ilLinkResourceHandlerGUI',
            default => $base_url,
        },
        'new' => 'ilias.php?baseClass=ilRepositoryGUI&cmd=create&new_type=' . preg_replace('/[^a-z]/', '', $_GET['type']),
        'start' => match ($_GET['type']) {
            'cat'   => 'ilias.php?cmd=view&baseClass=ilRepositoryGUI&cmdClass=ilObjCategoryGUI',
            'crs'   => 'ilias.php?cmd=view&cmdClass=ilobjcoursegui&baseClass=ilRepositoryGUI',
            'exc'   => 'ilias.php?cmd=showOverview&cmdClass=ilExerciseHandlerGUI&baseClass=ilexercisehandlergui',
            'glo'   => 'ilias.php?baseClass=ilGlossaryPresentationGUI',
            'lm'    => 'ilias.php?baseClass=ilLMPresentationGUI',
            'htlm'  => 'ilias.php?baseClass=ilHTLMPresentationGUI',
            'sahs'  => 'ilias.php?baseClass=ilSAHSPresentationGUI',
            'svy'   => 'ilias.php?cmd=infoScreen&cmdClass=ilObjSurveyGUI&baseClass=ilRepositoryGUI',
            'tst'  => 'ilias.php?baseClass=ilrepositorygui&cmd=testScreen&cmdClass=ILIAS\\Test\\Presentation\\TestScreen',
            'webr'  => 'ilias.php?cmd=calldirectlink&baseClass=ilLinkResourceHandlerGUI',
            default => $base_url,
        },
        default => $base_url,
    };
    if ($base_url) {
        if (!empty($_GET['ref_id'])) {
            $base_url .= '&ref_id=' . (int) $_GET['ref_id'];
        }

//        $token_repository = new ilCtrlTokenRepository();
//        $token = $token_repository->getToken();
//        $base_url .= '&' . ilCtrlInterface::PARAM_CSRF_TOKEN . '=' . $token->getToken();


        header('Location: ' . $base_url);
        exit();
    }
}
