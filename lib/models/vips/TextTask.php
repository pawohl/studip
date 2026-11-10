<?php
/*
 * TextTask.php - Vips plugin for Stud.IP
 * Copyright (c) 2006-2009  Elmar Ludwig, Martin Schröder
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

/**
 * @license GPL2 or any later version
 *
 * @property int $id database column
 * @property string $type database column
 * @property string $title database column
 * @property string $description database column
 * @property string $task database column
 * @property string $user_id database column
 * @property int $mkdate database column
 * @property int $chdate database column
 * @property JSONArrayObject $options database column
 * @property SimpleORMapCollection<VipsExerciseRef> $exercise_refs has_many VipsExerciseRef
 * @property SimpleORMapCollection<VipsSolution> $solutions has_many VipsSolution
 * @property User $user belongs_to User
 * @property Folder $folder has_one Folder
 * @property SimpleORMapCollection<VipsTest> $tests has_and_belongs_to_many VipsTest
 */
class TextTask extends Exercise
{
    /**
     * Get the icon of this exercise type.
     */
    public static function getTypeIcon(string $role = Icon::DEFAULT_ROLE): Icon
    {
        return Icon::create('task-text', $role);
    }

    /**
     * Get a description of this exercise type.
     */
    public static function getTypeDescription(): string
    {
        return _('Mehrzeilige Textantwort oder Dateiabgabe');
    }

    /**
     * Initialize this instance from the current request environment.
     */
    public function initFromRequest($request): void
    {
        parent::initFromRequest($request);

        $this->task['answers'][0] = [
            'text'  => Studip\Markup::purifyHtml(trim($request['answer_0'])),
            'score' => 1
        ];

        $this->task['template'] = trim($request['answer_default']);
        $this->task['compare']  = $request['compare'];

        if ($request['layout']) {
            $this->task['layout'] = $request['layout'];
        }

        if ($request['layout'] === 'markup') {
            $this->task['template'] = Studip\Markup::purifyHtml($this->task['template']);
        }

        if ($request['file_upload'] || $request['layout'] === 'none') {
            $this->options['file_upload'] = 1;
        }
    }

    /**
     * Exercise handler to be called when a solution is corrected.
     */
    public function correctSolutionAction(Trails\Controller $controller, VipsSolution $solution): void
    {
        $commented_solution = Request::get('commented_solution');

        if (isset($commented_solution)) {
            $solution->commented_solution = Studip\Markup::purifyHtml(trim($commented_solution));
        } else {
            $solution->commented_solution = null;
        }

        if (Request::submitted('delete_commented_solution')) {
            $solution->commented_solution = null;
            $solution->store();

            PageLayout::postSuccess(_('Die kommentierte Lösung wurde gelöscht.'));
        }
    }

    /**
     * Return the layout of this task (text, markup, code or none).
     */
    public function getLayout(): string
    {
        return $this->task['layout'] ?? 'text';
    }

    /**
     * Evaluates a student's solution for the individual items in this
     * exercise. Returns an array of ('points' => float, 'safe' => boolean).
     *
     * @param mixed $solution The solution XML string as returned by responseFromRequest().
     */
    public function evaluateItems($solution): array
    {
        $result = [];

        $answerDefault   = Studip\Markup::removeHtml($this->task['template'] ?? '');
        $musterLoesung   = Studip\Markup::removeHtml($this->task['answers'][0]['text']);
        $studentSolution = Studip\Markup::removeHtml($solution->response[0] ?? '');

        $answerDefault   = $this->normalizeText($answerDefault, true);
        $studentSolution = $this->normalizeText($studentSolution, true);
        $musterLoesung   = $this->normalizeText($musterLoesung, true);

        if ($studentSolution == '' || $studentSolution == $answerDefault) {
            $has_files = $solution->folder && count($solution->folder->file_refs);
            $result[] = ['points' => 0, 'safe' => !$has_files ? true : null];
        } else if ($musterLoesung == $studentSolution) {
            $result[] = ['points' => 1, 'safe' => true];
        } else if ($this->task['compare'] === 'levenshtein') {
            $string1 = mb_substr($studentSolution, 0, 500);
            $string2 = mb_substr($musterLoesung, 0, 500);
            $string3 = mb_substr($answerDefault, 0, 500);
            $divisor = $this->levenshtein($string3, $string2) ?: 1;

            $levenshtein = $this->levenshtein($string1, $string2) / $divisor;
            $similarity = max(1 - $levenshtein, 0);
            $result[] = ['points' => $similarity, 'safe' => false];
        } else {
            $result[] = ['points' => 0, 'safe' => null];
        }

        return $result;
    }

    /**
     * Return the default response when there is no existing solution.
     */
    public function defaultResponse(): array
    {
        return [$this->task['template']];
    }

    /**
     * Return the solution of the student from the request POST data.
     *
     * @param array $request array containing the postdata for the solution.
     * @return array containing the solutions of the student.
     */
    public function responseFromRequest(array|ArrayAccess $request): array
    {
        $result = parent::responseFromRequest($request);

        if ($this->getLayout() === 'markup') {
            $result = array_map('Studip\Markup::purifyHtml', $result);
        }

        return $result;
    }

    /**
     * Construct a new solution object from the request post data.
     */
    public function getSolutionFromRequest($request, ?array $files = null): VipsSolution
    {
        $solution = parent::getSolutionFromRequest($request, $files);
        $upload = $files['upload'] ?: ['name' => []];
        $solution_files = [];

        if ($this->options['file_upload']) {
            if ($files['upload']) {
                $solution->options['upload'] = $files['upload'];
            }

            $solution->assignment_id = $request['assignment_id'];
            $solution->store();
            $folder = Folder::findTopFolder($solution->id, 'ResponseFolder', 'response');

            if (is_array($request['file_ids'])) {
                foreach ($request['file_ids'] as $file_id) {
                    $file_ref = FileRef::find($file_id);
                    FileManager::copyFile($file_ref->getFileType(), $folder->getTypedFolder(), User::findCurrent());
                }
            }

            FileManager::handleFileUpload($upload, $folder->getTypedFolder());
        }

        return $solution;
    }

    /**
     * Return the list of keywords used for text export. The first keyword
     * in the list must be the keyword for the exercise type.
     */
    public static function getTextKeywords(): array
    {
        return ['Offene Frage', 'Eingabehilfe', 'Abgleich', 'Vorgabe', 'Antwort'];
    }

    /**
     * Initialize this instance from the given text data array.
     */
    public function initText(array $exercise): void
    {
        parent::initText($exercise);

        foreach ($exercise as $tag) {
            if (key($tag) === 'Abgleich') {
                if (current($tag) === 'Levenshtein') {
                    $this->task['compare'] = 'levenshtein';
                }
            }

            if (key($tag) === 'Vorgabe') {
                $this->task['template'] = Studip\Markup::purifyHtml(current($tag));
            }

            if (key($tag) === 'Antwort') {
                $this->task['answers'][0] = [
                    'text'  => Studip\Markup::purifyHtml(current($tag)),
                    'score' => 1
                ];
            }
        }
    }

    /**
     * Initialize this instance from the given SimpleXMLElement object.
     */
    public function initXML($exercise): void
    {
        parent::initXML($exercise);

        foreach ($exercise->items->item->answers->answer as $answer) {
            if ($answer['score'] == '1') {
                $this->task['answers'][0] = [
                    'text'  => Studip\Markup::purifyHtml(trim($answer)),
                    'score' => 1
                ];
            } else if ($answer['default'] == 'true') {
                $this->task['template'] = Studip\Markup::purifyHtml(trim($answer));
            }
        }

        if ($exercise->items->item->{'evaluation-hints'}) {
            switch ($exercise->items->item->{'evaluation-hints'}->similarity['type']) {
                case 'levenshtein':
                    $this->task['compare'] = 'levenshtein';
            }
        }

        if ($exercise->items->item->{'submission-hints'}->input) {
            switch ($exercise->items->item->{'submission-hints'}->input['type']) {
                case 'markup':
                    $this->task['layout'] = 'markup';
                    break;
                case 'code':
                    $this->task['layout'] = 'code';
                    break;
                case 'none':
                    $this->task['layout'] = 'none';
            }
        }

        if ($exercise->items->item->{'submission-hints'}->attachments) {
            if ($exercise->items->item->{'submission-hints'}->attachments['upload'] == 'true') {
                $this->options['file_upload'] = 1;
            }
        }
    }
}
